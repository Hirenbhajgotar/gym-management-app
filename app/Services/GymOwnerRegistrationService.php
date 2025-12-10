<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\Plan;
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
        protected RolesAndPermissionsService $rolesAndPermissionsService
    ) {}

    /**
     * Create gym owner, gym, and subscription from registration data
     * This is the main entry point called by both redirect verification and webhook processing
     *
     * @return array{user: User, gym: Gym, subscription: \App\Models\Subscription, subscription_status: string, already_exists: bool}
     */
    public function createFromRegistrationData(array $registrationData, ?Payment $payment = null): array
    {
        try {
            DB::beginTransaction();

            $plan = Plan::findOrFail($registrationData['selected_plan_id']);

            // Check if user already exists (idempotency)
            $existingUser = User::where('email', $registrationData['email'])->first();

            if ($existingUser) {
                Log::info('User already exists, skipping creation', [
                    'email' => $registrationData['email'],
                ]);

                // Find the gym and subscription
                $gym = $existingUser->gyms()->first();
                $subscription = $existingUser->subscriptions()->first();

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
            $this->sendNotifications($user, $gym, $subscriptionResult['status']);

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

    /**
     * Create user account
     */
    protected function createUser(array $registrationData): User
    {
        return User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'phone' => $registrationData['phone'],
            'password' => $registrationData['password'],
        ]);
    }

    /**
     * Create gym with unique slug
     */
    protected function createGym(array $registrationData): Gym
    {
        return Gym::create([
            'name' => $registrationData['gym_name'],
            'slug' => Gym::generateUniqueSlug($registrationData['gym_name']),
        ]);
    }

    /**
     * Create subscription based on payment verification status
     *
     * @return array{subscription: \App\Models\Subscription, status: string}
     */
    protected function createSubscription(User $user, Plan $plan, ?Payment $payment): array
    {
        // Default to pending status if no payment provided
        if (! $payment) {
            $subscription = $this->subscriptionService->createSubscription($user, $plan, [
                'billing_cycle' => 'yearly',
                'force_status' => \App\Enums\SubscriptionStatus::PENDING,
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
                $verificationResult = app(RazorpayService::class)->verifyPaymentByOrderId($payment->razorpay_order_id);
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
            ? \App\Enums\SubscriptionStatus::ACTIVE
            : \App\Enums\SubscriptionStatus::PENDING;

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
        $gymOwnerRole = \Spatie\Permission\Models\Role::where('name', DefaultRole::GYM_OWNER->value)
            ->where('gym_id', $gym->id)
            ->first();

        if ($gymOwnerRole) {
            // Get ALL permissions available in the system
            $allPermissions = \App\Models\Permission::all();

            // Assign all permissions to the gym_owner role
            $gymOwnerRole->syncPermissions($allPermissions);

            // Clear permission cache to ensure changes take effect immediately
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Create gym address
     */
    protected function createGymAddress(Gym $gym, array $registrationData): void
    {
        $gym->address()->create([
            'street' => $registrationData['address']['street'],
            'city_id' => $registrationData['address']['city_id'],
            'state_id' => $registrationData['address']['state_id'],
            'country_id' => $registrationData['address']['country_id'],
            'postal_code' => $registrationData['address']['postal_code'],
            'latitude' => $registrationData['address']['latitude'] &&
                ! empty($registrationData['address']['latitude'])
                    ? $registrationData['address']['latitude']
                    : null,
            'longitude' => $registrationData['address']['longitude'] &&
                ! empty($registrationData['address']['longitude'])
                    ? $registrationData['address']['longitude']
                    : null,
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
        \App\Helpers\FeedbackHelper::storeFeedbackCache($user->id, 15);
    }

    /**
     * Send notifications to super-admins about new gym owner registration
     */
    protected function sendNotifications(User $user, Gym $gym, string $subscriptionStatus): void
    {
        $superAdmins = User::role('super_admin')->get();

        foreach ($superAdmins as $superAdmin) {
            $superAdmin->notify(
                new \App\Notifications\UserActionNotification(
                    'created',
                    $user,
                    "Gym: {$gym->name}",
                ),
            );

            \Filament\Notifications\Notification::make()
                ->title('New Business Added - ' . $gym->name)
                ->body(
                    "A new business '{$gym->name}' has been registered by '{$user->name}'. Subscription status: {$subscriptionStatus}",
                )
                ->success()
                ->sendToDatabase($superAdmin)
                ->send();

            event(new \Filament\Notifications\Events\DatabaseNotificationsSent($superAdmin));
        }
    }
}
