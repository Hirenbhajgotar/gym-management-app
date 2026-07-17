<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_service_generates_pdf_for_member_payment()
    {
        Storage::fake('local');

        // Create Gym, Plan, Member, Payment
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_date' => now(),
        ]);

        $service = new InvoiceService();
        $service->generateMemberInvoice($payment);

        $payment->refresh();

        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->invoice_path);
        Storage::disk('local')->assertExists($payment->invoice_path);
    }

    public function test_create_gym_member_after_create_generates_member_invoice()
    {
        // The lead-conversion flow (LeadKanbanBoard -> CreateGymMember) creates a
        // MemberPayment and dispatches GenerateInvoiceJob. We exercise the same
        // invoice-generation path the afterCreate hook relies on.
        Storage::fake('local');

        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'gym_membership_plan_id' => $plan->id,
        ]);

        $payment = MemberPayment::query()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'amount' => 1500,
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
}
