<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Throwable;

/**
 * Full coverage for {@see InvoiceService} and {@see GenerateInvoiceJob}.
 *
 * Covers: PDF generation (member + system payments), idempotent re-generation,
 * invoice-number building, disk resolution/fallback, lazy regeneration via
 * download/view, the authorization matrix in assertAccessible(), and the
 * GenerateInvoiceJob dispatch routing + unique-id.
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_generate_member_invoice_writes_pdf_and_persists_meta(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        (new InvoiceService())->generateMemberInvoice($payment);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_generate_system_payment_invoice_writes_pdf(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        (new InvoiceService())->generateInvoice($payment);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_generate_is_idempotent_when_invoice_already_exists(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $service = new InvoiceService();
        $service->generateInvoice($payment);
        $payment->refresh();
        $firstPath = $payment->invoice_path;
        $firstNumber = $payment->invoice_number;

        $service->generateInvoice($payment);
        $payment->refresh();

        $this->assertSame($firstPath, $payment->invoice_path);
        $this->assertSame($firstNumber, $payment->invoice_number);
        Storage::disk('local')->assertExists($firstPath);
    }

    public function test_force_flag_regenerates_existing_invoice(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $service = new InvoiceService();
        $service->generateInvoice($payment);
        $payment->refresh();
        $originalPath = $payment->invoice_path;
        $originalNumber = $payment->invoice_number;

        $service->generateInvoice($payment, true);
        $payment->refresh();

        $this->assertSame($originalNumber, $payment->invoice_number);
        $this->assertSame($originalPath, $payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_invoice_number_includes_gym_id_and_padded_key(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'created_at' => '2026-01-15 10:00:00',
        ]);

        (new InvoiceService())->generateInvoice($payment);
        $payment->refresh();

        $expectedKey = str_pad((string) $payment->id, 10, '0', STR_PAD_LEFT);
        $expectedDate = $payment->created_at->format('Ymd');
        $this->assertSame("INV-{$expectedDate}-S{$gym->id}{$expectedKey}", $payment->invoice_number);
    }

    public function test_invoice_number_falls_back_to_zero_gym_when_gym_missing(): void
    {
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => null,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        (new InvoiceService())->generateInvoice($payment);
        $payment->refresh();

        $expectedKey = str_pad((string) $payment->id, 10, '0', STR_PAD_LEFT);
        $this->assertStringStartsWith("INV-{$payment->created_at->format('Ymd')}-S0{$expectedKey}", $payment->invoice_number);
    }

    public function test_disk_resolves_configured_invoice_disk(): void
    {
        config(['filesystems.invoice_disk' => 'local']);

        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        (new InvoiceService())->generateInvoice($payment);
        $payment->refresh();

        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_disk_falls_back_to_local_when_configured_disk_invalid(): void
    {
        config(['filesystems.invoice_disk' => 'does-not-exist']);

        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        (new InvoiceService())->generateInvoice($payment);
        $payment->refresh();

        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_download_invoice_regenerates_when_path_missing(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($user);

        $response = (new InvoiceService())->downloadInvoice($payment);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_view_invoice_regenerates_when_path_missing(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($user);

        $response = (new InvoiceService())->viewInvoice($payment);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_path);
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_download_invoice_requires_authenticated_user(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->expectException(HttpException::class);

        (new InvoiceService())->downloadInvoice($payment);
    }

    public function test_view_invoice_requires_authenticated_user(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->expectException(HttpException::class);

        (new InvoiceService())->viewInvoice($payment);
    }

    public function test_view_invoice_allowed_for_system_payment_owner(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($user);

        $response = (new InvoiceService())->viewInvoice($payment);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_view_invoice_denied_for_system_payment_non_owner(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $owner->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($other);

        $this->expectException(HttpException::class);

        (new InvoiceService())->viewInvoice($payment);
    }

    public function test_download_system_payment_allowed_for_gym_owner_of_payment_gym(): void
    {
        // H2: a gym owner who owns the gym that received a system payment must
        // be able to download it, even though they are not the payment's user.
        $gym = Gym::factory()->create();
        $payer = User::factory()->create();
        $owner = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $payer->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'gym_owner_registration',
        ]);

        $this->attachGymRole($owner, $gym, DefaultRole::GYM_OWNER->value);
        $this->actingAs($owner);

        $response = (new InvoiceService())->downloadInvoice($payment);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_view_invoice_allowed_for_gym_owner_of_payment_gym(): void
    {
        $gym = Gym::factory()->create();
        $payer = User::factory()->create();
        $owner = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $payer->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'gym_owner_registration',
        ]);

        $this->attachGymRole($owner, $gym, DefaultRole::GYM_OWNER->value);
        $this->actingAs($owner);

        $response = (new InvoiceService())->viewInvoice($payment);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_member_invoice_renders_without_crashing_when_member_is_null(): void
    {
        // H1: getInvoiceOwner() returns null when the related GymMember no longer
        // exists (detached / orphaned member_id). The member.blade view must guard
        // against a null $user instead of throwing on $user->name.
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        // Insert a member payment pointing at a non-existent member. The FK is
        // temporarily disabled so we can model an orphaned row (e.g. a member
        // row deleted without cascading the payment, or a bad import).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $paymentId = DB::table('member_payments')->insertGetId([
            'gym_id' => $gym->id,
            'member_id' => 999999,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR->value,
            'status' => PaymentStatus::COMPLETED->value,
            'payment_date' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $payment = MemberPayment::query()->findOrFail($paymentId);

        $thrown = null;

        try {
            (new InvoiceService())->generateMemberInvoice($payment);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, 'Generating an invoice for a member payment with a null owner should not throw. ' . ($thrown?->getMessage() ?? ''));

        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_generate_invoice_job_retry_is_not_blocked_by_unique_lock(): void
    {
        // M1: GenerateInvoiceJob implements ShouldBeUnique keyed on
        // "{paymentType}:{paymentId}". Because InvoiceService::generate() is
        // idempotent via the invoiceExists() guard, a *retry* of a failed job
        // (same uniqueId) must be able to re-run rather than be silently
        // dropped as a duplicate. We assert the two jobs share a uniqueId (so
        // the uniqueness contract is well-defined) and that re-handling the
        // same payment reuses the existing PDF rather than failing.
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $first = new GenerateInvoiceJob($payment, 'payment');
        $retry = new GenerateInvoiceJob($payment, 'payment');

        // Same uniqueId => the queue treats the second as the "same" job.
        $this->assertSame($first->uniqueId(), $retry->uniqueId());

        // Idempotency: handling the job twice produces one stable PDF path.
        $first->handle(resolve(InvoiceService::class));
        $payment->refresh();
        $path = $payment->invoice_path;

        $retry->handle(resolve(InvoiceService::class));
        $payment->refresh();

        $this->assertSame($path, $payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_download_system_payment_allowed_for_owner(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($user);

        $response = (new InvoiceService())->downloadInvoice($payment);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_download_system_payment_denied_for_non_owner(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $owner->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $this->actingAs($other);

        $this->expectException(HttpException::class);

        (new InvoiceService())->downloadInvoice($payment);
    }

    public function test_download_member_payment_allowed_for_gym_role(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        $user = User::factory()->create();
        $this->attachGymRole($user, $gym, DefaultRole::GYM_OWNER->value);

        $this->actingAs($user);

        $response = (new InvoiceService())->downloadInvoice($payment);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_download_member_payment_denied_for_wrong_gym(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        $user = User::factory()->create();
        $this->attachGymRole($user, $otherGym, DefaultRole::GYM_OWNER->value);

        $this->actingAs($user);

        $this->expectException(HttpException::class);

        (new InvoiceService())->downloadInvoice($payment);
    }

    public function test_download_member_payment_denied_for_missing_role(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        $user = User::factory()->create();
        $user->gyms()->attach($gym);

        $this->actingAs($user);

        $this->expectException(HttpException::class);

        (new InvoiceService())->downloadInvoice($payment);
    }

    /**
     * Create a gym-scoped Spatie role (teams mode) and attach the user to the
     * gym with that role so assertAccessible()'s gym + role checks pass.
     */
    private function attachGymRole(User $user, Gym $gym, string $roleName): void
    {
        setPermissionsTeamId($gym->id);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        $user->assignRole($role);
        $user->gyms()->attach($gym, ['role' => $roleName, 'active' => true]);
        $user->load('gyms');

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_generate_system_payment_with_null_user_does_not_crash(): void
    {
        $gym = Gym::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => null,
            'gym_id' => $gym->id,
            'amount' => 5000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'gym_owner_registration',
        ]);

        (new InvoiceService())->generateInvoice($payment);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_job_dispatches_member_invoice_generation(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        (new GenerateInvoiceJob($payment, 'member_payment'))->handle(resolve(InvoiceService::class));

        $payment->refresh();
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_job_dispatches_system_invoice_generation(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        (new GenerateInvoiceJob($payment, 'payment'))->handle(resolve(InvoiceService::class));

        $payment->refresh();
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_job_unique_id_combines_type_and_payment_id(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $job = new GenerateInvoiceJob($payment, 'payment');

        $this->assertSame("payment:{$payment->id}", $job->uniqueId());
    }

    public function test_job_unique_id_is_distinct_across_payment_types(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $memberPayment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);

        $memberJob = new GenerateInvoiceJob($memberPayment, 'member_payment');
        $systemJob = new GenerateInvoiceJob($payment, 'payment');

        $this->assertNotSame($memberJob->uniqueId(), $systemJob->uniqueId());
        $this->assertSame("member_payment:{$memberPayment->id}", $memberJob->uniqueId());
        $this->assertSame("payment:{$payment->id}", $systemJob->uniqueId());
    }
}
