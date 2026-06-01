<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Jobs\ProcessMetaTemplateWebhook;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessMetaTemplateWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_unit_sid',
            'twilio_auth_token' => 'test_unit_auth_token',
            'messaging_service_sid' => 'MGtest_unit_service',
            'waba_id' => 'test_waba_123',
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Test Unit Gym',
            'business_email' => 'testunit@gym.com',
        ]);
    }

    #[Test]
    public function test_job_updates_template_to_approved(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_mti_1',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'rejection_reason' => null,
        ]);

        $job = new ProcessMetaTemplateWebhook('test_waba_123', 'test_mti_1', 'APPROVED', null, 'PENDING');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template->status);
        $this->assertNull($template->rejection_reason);
    }

    #[Test]
    public function test_job_updates_template_to_rejected_with_reason(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_mti_2',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $job = new ProcessMetaTemplateWebhook('test_waba_123', 'test_mti_2', 'REJECTED', 'Contains spam content', 'PENDING');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template->status);
        $this->assertEquals('Contains spam content', $template->rejection_reason);
    }

    #[Test]
    public function test_job_handles_unknown_template_gracefully(): void
    {
        $job = new ProcessMetaTemplateWebhook('test_waba_123', 'nonexistent_id', 'APPROVED', null, 'PENDING');

        // Should not throw an exception
        $job->handle();

        $this->assertTrue(true);
    }

    #[Test]
    public function test_job_maps_all_event_types(): void
    {
        // Test APPROVED
        $template1 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_approved',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_approved', 'APPROVED', null, 'PENDING'))->handle();
        $template1->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template1->status);

        // Test REJECTED
        $template2 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_rejected',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_rejected', 'REJECTED', 'Test reason', 'PENDING'))->handle();
        $template2->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template2->status);

        // Test PENDING_DELETION -> PENDING
        $template3 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_pending_deletion',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_pending_deletion', 'PENDING_DELETION', null, 'APPROVED'))->handle();
        $template3->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $template3->status);

        // Test FLAGGED -> REJECTED
        $template4 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_flagged',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_flagged', 'FLAGGED', 'Policy violation', 'PENDING'))->handle();
        $template4->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template4->status);

        // Test PAUSED -> DISABLED
        $template5 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_paused',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_paused', 'PAUSED', null, 'APPROVED'))->handle();
        $template5->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DISABLED, $template5->status);

        // Test DISABLED -> DISABLED
        $template6 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_disabled',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessMetaTemplateWebhook('test_waba_123', 'test_disabled', 'DISABLED', null, 'APPROVED'))->handle();
        $template6->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DISABLED, $template6->status);
    }

    #[Test]
    public function test_job_skips_unchanged_status(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_same_status',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $updatedAtBefore = $template->updated_at;

        // Sleep briefly to ensure timestamp difference would be detectable
        usleep(1000);

        $job = new ProcessMetaTemplateWebhook('test_waba_123', 'test_same_status', 'APPROVED', null, 'APPROVED');
        $job->handle();

        $template->refresh();

        // Status should still be APPROVED
        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template->status);

        // updated_at should not change since we skipped the update
        $this->assertEquals($updatedAtBefore, $template->updated_at);
    }

    #[Test]
    public function test_job_skips_unknown_event(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'meta_template_id' => 'test_unknown',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $job = new ProcessMetaTemplateWebhook('test_waba_123', 'test_unknown', 'UNKNOWN_EVENT', null, 'PENDING');
        $job->handle();

        $template->refresh();

        // Status should remain PENDING
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $template->status);
    }
}
