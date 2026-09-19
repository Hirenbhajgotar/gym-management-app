<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\InvoiceablePayment;
use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Jobs\GenerateInvoiceJob;
use App\Models\City;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[CoversClass(InvoiceService::class)]
#[UsesClass(GenerateInvoiceJob::class)]
final class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.invoice_disk' => 'invoices']);
        Storage::fake('invoices');
    }

    private function getInvoiceDisk(): string
    {
        return config()->string('filesystems.invoice_disk', 'invoices');
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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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

        $this->assertNotNull($firstPath);
        $this->assertSame($firstPath, $payment->invoice_path);
        $this->assertSame($firstNumber, $payment->invoice_number);
        Storage::disk($this->getInvoiceDisk())->assertExists($firstPath);
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

        $this->assertNotNull($payment->invoice_path);
        $this->assertSame($originalNumber, $payment->invoice_number);
        $this->assertSame($originalPath, $payment->invoice_path);
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
    }

    public function test_invoice_number_includes_gym_id_and_delimited_padded_key(): void
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

        $this->assertNotNull($payment->created_at);
        $expectedKey = str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
        $expectedDate = $payment->created_at->format('Ymd');
        $this->assertSame("INV-{$expectedDate}-S{$gym->id}-{$expectedKey}", $payment->invoice_number);
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

        $this->assertNotNull($payment->created_at);
        $this->assertNotNull($payment->invoice_number);
        $expectedKey = str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
        $this->assertStringStartsWith("INV-{$payment->created_at->format('Ymd')}-S0-{$expectedKey}", $payment->invoice_number);
    }

    public function test_invoice_number_collision_prevention(): void
    {
        $service = new InvoiceService();

        $gym1 = Gym::factory()->create();
        $user1 = User::factory()->create();

        // Simulate Gym 1 with ID 10001
        $payment1 = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user1->id,
            'gym_id' => $gym1->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'created_at' => '2026-09-18 10:00:00',
        ]);
        // Force ID to simulate high key
        $mockPayment1 = new class() implements InvoiceablePayment
        {
            public function getInvoiceKey(): int
            {
                return 10001;
            }

            public function getInvoiceNumber(): ?string
            {
                return null;
            }

            public function getInvoicePath(): ?string
            {
                return null;
            }

            public function getGym(): Gym
            {
                $g = new Gym();
                $g->id = 1;

                return $g;
            }

            public function getInvoiceOwner(): ?User
            {
                return null;
            }

            public function getOwnerId(): ?int
            {
                return null;
            }

            public function getCreatedAt(): Carbon
            {
                return Date::parse('2026-09-18');
            }

            public function setInvoiceNumber(string $number): void {}

            public function setInvoicePath(string $path): void {}

            public function saveInvoiceMetaQuietly(): void {}
        };

        // Simulate Gym 11 with ID 1
        $mockPayment2 = new class() implements InvoiceablePayment
        {
            public function getInvoiceKey(): int
            {
                return 1;
            }

            public function getInvoiceNumber(): ?string
            {
                return null;
            }

            public function getInvoicePath(): ?string
            {
                return null;
            }

            public function getGym(): Gym
            {
                $g = new Gym();
                $g->id = 11;

                return $g;
            }

            public function getInvoiceOwner(): ?User
            {
                return null;
            }

            public function getOwnerId(): ?int
            {
                return null;
            }

            public function getCreatedAt(): Carbon
            {
                return Date::parse('2026-09-18');
            }

            public function setInvoiceNumber(string $number): void {}

            public function setInvoicePath(string $path): void {}

            public function saveInvoiceMetaQuietly(): void {}
        };

        $num1 = $service->ensureInvoiceNumber($mockPayment1);
        $num2 = $service->ensureInvoiceNumber($mockPayment2);

        $this->assertSame('INV-20260918-S1-10001', $num1);
        $this->assertSame('INV-20260918-S11-0001', $num2);
        $this->assertNotSame($num1, $num2);
    }

    public function test_invoice_number_boundary_keys(): void
    {
        $service = new InvoiceService();

        foreach ([9999 => '9999', 10000 => '10000', 10001 => '10001'] as $key => $expectedKeySuffix) {
            $mock = new class($key) implements InvoiceablePayment
            {
                public function __construct(private int $key) {}

                public function getInvoiceKey(): int
                {
                    return $this->key;
                }

                public function getInvoiceNumber(): ?string
                {
                    return null;
                }

                public function getInvoicePath(): ?string
                {
                    return null;
                }

                public function getGym(): Gym
                {
                    $g = new Gym();
                    $g->id = 5;

                    return $g;
                }

                public function getInvoiceOwner(): ?User
                {
                    return null;
                }

                public function getOwnerId(): ?int
                {
                    return null;
                }

                public function getCreatedAt(): Carbon
                {
                    return Date::parse('2026-09-18');
                }

                public function setInvoiceNumber(string $number): void {}

                public function setInvoicePath(string $path): void {}

                public function saveInvoiceMetaQuietly(): void {}
            };

            $num = $service->ensureInvoiceNumber($mock);
            $this->assertSame("INV-20260918-S5-{$expectedKeySuffix}", $num);
            $this->assertMatchesRegularExpression('/^INV-\d{8}-[SM]\d+-\d+$/', $num);
        }
    }

    public function test_legacy_invoice_number_backward_compatibility(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);

        $legacyNumber = 'INV-20260115-S10000000001';
        $legacyPath = "invoices/{$legacyNumber}.pdf";

        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
        ]);
        $payment->setInvoiceNumber($legacyNumber);
        $payment->setInvoicePath($legacyPath);
        $payment->saveInvoiceMetaQuietly();

        Storage::disk($this->getInvoiceDisk())->put($legacyPath, 'fake-pdf-bytes');

        $this->actingAs($user);

        $response = (new InvoiceService())->downloadInvoice($payment);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        $payment->refresh();
        $this->assertSame($legacyNumber, $payment->invoice_number);
        $this->assertSame($legacyPath, $payment->invoice_path);
    }

    public function test_disk_throws_when_configured_disk_invalid(): void
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

        $this->expectException(InvalidArgumentException::class);

        try {
            (new InvoiceService())->generateInvoice($payment);
        } finally {
            config(['filesystems.invoice_disk' => 'invoices']);
        }
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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $paymentId = null;
        Schema::withoutForeignKeyConstraints(function () use ($gym, $plan, &$paymentId) {
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
        });

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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
    }

    public function test_generate_invoice_job_retry_is_not_blocked_by_unique_lock(): void
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

        $first = new GenerateInvoiceJob($payment, 'payment');
        $retry = new GenerateInvoiceJob($payment, 'payment');

        $this->assertSame($first->uniqueId(), $retry->uniqueId());

        $first->handle(resolve(InvoiceService::class));
        $payment->refresh();
        $path = $payment->invoice_path;

        $retry->handle(resolve(InvoiceService::class));
        $payment->refresh();

        $this->assertNotNull($payment->invoice_path);
        $this->assertSame($path, $payment->invoice_path);
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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

    public function test_download_member_payment_allowed_for_member_owner(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $memberUser = User::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'user_id' => $memberUser->id,
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

        $this->actingAs($memberUser);

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

    public function test_invoice_blade_content_rendering(): void
    {
        $gym = Gym::factory()->create(['name' => 'Elite Power Gym']);
        $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Gold Annual']);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'user_id' => $user->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $sysPayment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'razorpay_payment_id' => 'pay_123456789',
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 5000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_pro',
            'created_at' => '2026-09-18 12:00:00',
        ]);
        $sysPayment->setInvoiceNumber('INV-20260918-S1-0001');

        $sysHtml = view('invoices.default', [
            'payment' => $sysPayment,
            'owner' => $user,
            'user' => $user,
            'gym' => $gym,
            'gym_owner_email' => 'owner@gym.com',
            'invoice_number' => 'INV-20260918-S1-0001',
            'date' => 'Sep 18, 2026',
        ])->render();

        $this->assertStringContainsString('INVOICE', $sysHtml);
        $this->assertStringContainsString('INV-20260918-S1-0001', $sysHtml);
        $this->assertStringContainsString('John Doe', $sysHtml);
        $this->assertStringContainsString('Elite Power Gym', $sysHtml);
        $this->assertStringContainsString('pay_123456789', $sysHtml);
        $this->assertStringNotContainsString('TAX INVOICE', $sysHtml);
        $this->assertStringNotContainsString('Taxes (GST Included)', $sysHtml);
        $this->assertStringNotContainsString('&#8377;0.00', $sysHtml);

        $memberPayment = MemberPayment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1500,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
            'created_at' => '2026-09-18 12:00:00',
        ]);
        $memberPayment->setInvoiceNumber('INV-20260918-M1-0001');
        $memberPayment->loadMissing(['membershipPlan']);

        $memberHtml = view('invoices.member', [
            'payment' => $memberPayment,
            'owner' => $member,
            'user' => $member,
            'gym' => $gym,
            'gym_owner_email' => 'owner@gym.com',
            'invoice_number' => 'INV-20260918-M1-0001',
            'date' => 'Sep 18, 2026',
        ])->render();

        $this->assertStringContainsString('RECEIPT', $memberHtml);
        $this->assertStringContainsString('INV-20260918-M1-0001', $memberHtml);
        $this->assertStringContainsString('Jane Smith', $memberHtml);
        $this->assertStringContainsString('Gold Annual', $memberHtml);
        $this->assertStringNotContainsString('TAX INVOICE', $memberHtml);
        $this->assertStringNotContainsString('Taxes:', $memberHtml);
    }

    public function test_invoice_renders_address_when_present(): void
    {
        $gym = Gym::factory()->create(['name' => 'Crossfit Alpha']);
        $state = State::factory()->create(['name' => 'Gujarat']);
        $city = City::factory()->create(['name' => 'Ahmedabad', 'state_id' => $state->id]);

        $gym->address()->create([
            'street' => '123 SG Highway',
            'city_id' => $city->id,
            'state_id' => $state->id,
            'postal_code' => '380054',
        ]);
        $gym->refresh();

        $user = User::factory()->create(['name' => 'Alex Member']);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_' . fake()->uuid(),
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'amount' => 2500,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_monthly',
        ]);

        (new InvoiceService())->generateInvoice($payment);
        $payment->refresh();

        $html = view('invoices.default', [
            'payment' => $payment,
            'owner' => $user,
            'user' => $user,
            'gym' => $gym,
            'gym_owner_email' => null,
            'invoice_number' => $payment->invoice_number,
            'date' => 'Sep 18, 2026',
        ])->render();

        $this->assertStringContainsString('123 SG Highway', $html);
        $this->assertStringContainsString('Ahmedabad', $html);
        $this->assertStringContainsString('Gujarat', $html);
        $this->assertStringContainsString('380054', $html);
    }

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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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
        Storage::disk($this->getInvoiceDisk())->assertExists($payment->invoice_path);
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
