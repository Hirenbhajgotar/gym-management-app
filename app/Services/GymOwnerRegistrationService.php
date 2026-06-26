<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Auth\Events\Registered;
use App\Enums\SubscriptionStatus;
use Spatie\Permission\Models\Role;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Helpers\FeedbackHelper;
use Illuminate\Support\Facades\Cache;
use App\Notifications\UserActionNotification;
use Filament\Notifications\Notification;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use App\Notifications\GymOwnerWelcomeNotification;
use App\Enums\DefaultRole;
use App\Enums\UserStatus;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for handling complete gym owner registration process
 * This includes user creation, gym setup, subscription management, and notifications
 */
class GymOwnerRegistrationService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected RolesAndPermissionsService $rolesAndPermissionsService,
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Create gym owner, gym, and subscription from registration data
     * This is the main entry point called by both redirect verification and webhook processing
     *
     * @return array{user: User, gym: Gym, subscription: Subscription, subscription_status: string, already_exists: bool}
     */
    public function createFromRegistrationData(array $registrationData, ?Payment $payment = null, ?Plan $confirmedPlan = null): array
    {
        try {
            DB::beginTransaction();

            // Use confirmed active plan if provided, otherwise validate from registration data
            $plan = $confirmedPlan ?? $this->validateActivePlan($registrationData['selected_plan_id'] ?? null);

            // Check if user already exists (idempotency)
            $existingUser = User::query()->where('email', $registrationData['email'])->first();

            if ($existingUser) {
                Log::info('User already exists, skipping creation', [
                    'email' => $registrationData['email'],
                ]);

                // Find the gym and subscription
                $gym = $existingUser->gyms()->first();
                $subscription = $existingUser->subscriptions()->first();

                $this->updatePayment($payment, $subscription, [
                    'gym_id' => $gym->id,
                    'plan_id' => $plan->id,
                    'user_id' => $existingUser->id,
                ]);

                return [
                    'user' => $existingUser,
                    'gym' => $gym,
                    'subscription' => $subscription,
                    'already_exists' => true,
                ];
            }

            // Create core entities
            $user = $this->createUser($registrationData);
            $gym = $this->createGym($registrationData);

            // Create subscription
            $subscriptionResult = $this->createSubscription($user, $plan, $payment);

            $subscription = $subscriptionResult['subscription'];

            $this->updatePayment($payment, $subscription, [
                'gym_id' => $gym->id,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);

            // Setup roles and permissions
            $this->setupRolesAndPermissions($user, $gym);

            // Create related entities
            $this->createGymAddress($gym, $registrationData);
            $this->createBusinessDetails($gym, $registrationData);

            // Attach user to gym
            $user->gyms()->attach($gym->id, ['role' => 'gym_owner']);

            DB::commit();
            // Post-creation tasks
            $this->storeFeedbackCache($user);

            // Generate invoice if payment is successful
            // We do this synchronously here to ensure it's available for the welcome email attachment
            if ($payment && $payment->hasValidSignature() && $payment->isCompleted()) {
                $this->invoiceService->generateInvoice($payment);
            }

            $this->sendNotifications($user, $gym, $subscriptionResult['status'], $payment);

            // Fire registered event to trigger email verification
            event(new Registered($user));

            return [
                'user' => $user,
                'gym' => $gym,
                'subscription' => $subscriptionResult['subscription'],
                'subscription_status' => $subscriptionResult['status'],
                'already_exists' => false,
            ];
        } catch (Exception $e) {
            Log::error('Error creating gym owner', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            DB::rollBack();

            return [
                'user' => null,
                'gym' => null,
                'subscription' => null,
                'subscription_status' => null,
                'already_exists' => false,
            ];
        }
    }

    public function updatePayment(Payment $payment, Subscription $subscription, $data = []): ?Payment
    {
        $metadata = $payment->metadata;
        $metadata['subscription_id'] = $subscription->id ?? null;
        $metadata['plan_id'] = $data['plan_id'] ?? null;

        Payment::query()->where('id', $payment->id)->update([
            'user_id' => $data['user_id'] ?? null,
            'gym_id' => $data['gym_id'] ?? null,
            'metadata' => $metadata,
        ]);

        return $payment->fresh();
    }

    /**
     * Validate that a plan is active
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpResponseException
     */
    protected function validateActivePlan(?int $planId): Plan
    {
        abort_unless($planId, 422, 'No plan selected');

        $plan = Plan::active()->find($planId);

        abort_unless($plan, 422, 'Selected plan is no longer available');

        return $plan;
    }

    /**
     * Create user account
     */
    protected function createUser(array $registrationData): User
    {
        return User::query()->create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'phone' => $registrationData['phone'],
            'password' => $registrationData['password'],
            'status' => UserStatus::UNDER_REVIEW,
        ]);
    }

    /**
     * Create gym with unique slug
     */
    protected function createGym(array $registrationData): Gym
    {
        return Gym::query()->create([
            'name' => $registrationData['gym_name'],
            'slug' => Gym::generateUniqueSlug($registrationData['gym_name']),
        ]);
    }

    /**
     * Create subscription based on payment verification status
     *
     * @return array{subscription: Subscription, status: string}
     */
    protected function createSubscription(User $user, Plan $plan, ?Payment $payment): array
    {
        // Default to pending status if no payment provided
        if (! $payment) {
            $subscription = $this->subscriptionService->createSubscription($user, $plan, [
                'billing_cycle' => 'yearly',
                'force_status' => SubscriptionStatus::PENDING,
            ]);

            return [
                'subscription' => $subscription,
                'status' => 'pending',
            ];
        }

        // Verify payment if payment data is available
        $isVerified = false;
        if ($payment->razorpay_order_id) {
            // Enhanced verification logic:
            // 1. Check if signature is stored and valid (fast path)
            // 2. OR if payment status is explicitly completed (trusting DB state)
            // 3. Fallback to API verification if signature is missing but we want to double check

            if ($payment->hasValidSignature() && $payment->isCompleted()) {
                $isVerified = true;
            } elseif ($payment->isCompleted()) {
                // Trust the database status if completed (could be from webhook)
                $isVerified = true;
            } else {
                // Fallback: Verify directly with Razorpay API
                // This handles cases where signature wasn't stored or webhook hasn't arrived yet
                $verificationResult = resolve(RazorpayService::class)->verifyPaymentByOrderId($payment->razorpay_order_id);
                $isVerified = $verificationResult['verified'];

                if ($isVerified) {
                    Log::info('Payment verified via API fallback during registration', [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->razorpay_order_id,
                    ]);
                }
            }
        }

        // Create subscription based on verification result
        $subscriptionStatus = $isVerified
            ? SubscriptionStatus::ACTIVE
            : SubscriptionStatus::PENDING;

        $subscription = $this->subscriptionService->createSubscription($user, $plan, [
            'billing_cycle' => 'yearly',
            'force_status' => $subscriptionStatus,
        ]);

        return [
            'subscription' => $subscription,
            'status' => $subscriptionStatus->value,
        ];
    }

    /**
     * Setup roles and permissions for gym owner
     */
    protected function setupRolesAndPermissions(User $user, Gym $gym): void
    {
        // Create default roles and permissions for the gym
        $this->rolesAndPermissionsService->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assign the gym owner role to the user
        $this->rolesAndPermissionsService->assignRoleToTenant($gym, $user, DefaultRole::GYM_OWNER);

        // Grant ALL permissions to gym_owner for their gym
        $this->grantAllPermissionsToGymOwner($user, $gym);
    }

    /**
     * Grant all available permissions to the gym owner
     */
    protected function grantAllPermissionsToGymOwner(User $user, Gym $gym): void
    {
        // Set the permission team context to the gym
        setPermissionsTeamId($gym->id);

        // Get the gym_owner role for this specific gym
        $gymOwnerRole = Role::query()->where('name', DefaultRole::GYM_OWNER->value)
            ->where('gym_id', $gym->id)
            ->first();

        if ($gymOwnerRole) {
            // Get ALL permissions available in the system
            $allPermissions = Permission::all();

            // Assign all permissions to the gym_owner role
            $gymOwnerRole->syncPermissions($allPermissions);

            // Clear permission cache to ensure changes take effect immediately
            resolve(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Create gym address
     */
    protected function createGymAddress(Gym $gym, array $registrationData): void
    {
        $gym->address()->create([
            'street' => $registrationData['address']['street'] ?? null,
            'city_id' => $registrationData['address']['city_id'] ?? null,
            'state_id' => $registrationData['address']['state_id'] ?? null,
            'country_id' => $registrationData['address']['country_id'] ?? null,
            'postal_code' => $registrationData['address']['postal_code'] ?? null,
            'latitude' => $registrationData['address']['latitude'] ?? null,
            'longitude' => $registrationData['address']['longitude'] ?? null,
        ]);
    }

    /**
     * Create business details for gym
     */
    protected function createBusinessDetails(Gym $gym, array $registrationData): void
    {
        $gym->businessDetail()->create([
            'gym_id' => $gym->id,
            'business_type_id' => $registrationData['business_type'] ?: null,
            'years_in_business' => $registrationData['years_in_business'],
            'number_of_locations' => $registrationData['number_of_locations'],
            'website' => $registrationData['website'],
            'gym_size' => $registrationData['gym_size'],
            'business_description' => $registrationData['business_description'],
            'special_requirements' => $registrationData['special_requirements'],
        ]);
    }

    /**
     * Store feedback cache for user
     */
    protected function storeFeedbackCache(User $user): void
    {
        FeedbackHelper::storeFeedbackCache($user->id, 15);
    }

    /**
     * Send notifications to super-admins and the new user
     */
    public function sendNotifications(User $user, Gym $gym, string $subscriptionStatus, ?Payment $payment = null): void
    {
        try {
            $superAdmins = Cache::flexible(User::ALL_SUPER_ADMINS_CACHE_KEY, [86400, 604800], function () {
                return User::query()->role('super_admin')->get();
            });

            foreach ($superAdmins as $superAdmin) {
                try {
                    $superAdmin->notify(
                        new UserActionNotification(
                            'created',
                            $user,
                            "Gym: {$gym->name}",
                        ),
                    );

                    Notification::make()
                        ->title('New Business Added - ' . $gym->name)
                        ->body(
                            "A new business '{$gym->name}' has been registered by '{$user->name}'. Subscription status: {$subscriptionStatus}",
                        )
                        ->success()
                        ->sendToDatabase($superAdmin)
                        ->send();

                    event(new DatabaseNotificationsSent($superAdmin));
                } catch (\Exception $e) {
                    Log::error('Failed to notify super admin', [
                        'admin_id' => $superAdmin->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send welcome notification to the new gym owner
            try {
                $user->notify(new GymOwnerWelcomeNotification($user, null, $payment));
            } catch (\Exception $e) {
                Log::error('Failed to send welcome notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in sendNotifications wrapper', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
