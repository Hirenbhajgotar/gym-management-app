<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Jobs\ProcessTwilioTemplateWebhook;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessTwilioTemplateWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    #[Test]
    public function test_job_updates_template_to_approved(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_approved_sid',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'rejection_reason' => null,
        ]);

        $job = new ProcessTwilioTemplateWebhook('HXtest_approved_sid', 'approved');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template->status);
        $this->assertNull($template->rejection_reason);
    }

    #[Test]
    public function test_job_updates_template_to_rejected_with_error_details(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_rejected_sid',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $job = new ProcessTwilioTemplateWebhook(
            'HXtest_rejected_sid',
            'rejected',
            '1234',
            'Template contains prohibited content'
        );
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template->status);
        $this->assertEquals('[Error 1234] Template contains prohibited content', $template->rejection_reason);
    }

    #[Test]
    public function test_job_updates_template_to_rejected_without_error_details(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_rejected_no_error',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $job = new ProcessTwilioTemplateWebhook('HXtest_rejected_no_error', 'rejected');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template->status);
        $this->assertEquals('Template rejected by Twilio', $template->rejection_reason);
    }

    #[Test]
    public function test_job_clears_rejection_reason_on_approval(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_resubmit_sid',
            'status' => WhatsAppTemplateStatusEnum::REJECTED,
            'rejection_reason' => 'Previous rejection',
        ]);

        $job = new ProcessTwilioTemplateWebhook('HXtest_resubmit_sid', 'approved');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template->status);
        $this->assertNull($template->rejection_reason);
    }

    #[Test]
    public function test_job_handles_unknown_template_gracefully(): void
    {
        $job = new ProcessTwilioTemplateWebhook('HXnonexistent_sid', 'approved');

        // Should not throw an exception
        $job->handle();

        $this->assertTrue(true);
    }

    #[Test]
    public function test_job_maps_all_status_types(): void
    {
        // Test approved
        $template1 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_approved',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);
        (new ProcessTwilioTemplateWebhook('HXtest_approved', 'approved'))->handle();
        $template1->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template1->status);

        // Test rejected
        $template2 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_rejected',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);
        (new ProcessTwilioTemplateWebhook('HXtest_rejected', 'rejected'))->handle();
        $template2->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $template2->status);

        // Test pending
        $template3 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_pending',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessTwilioTemplateWebhook('HXtest_pending', 'pending'))->handle();
        $template3->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $template3->status);

        // Test disabled
        $template4 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_disabled',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessTwilioTemplateWebhook('HXtest_disabled', 'disabled'))->handle();
        $template4->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DISABLED, $template4->status);

        // Test paused -> disabled
        $template5 = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_paused',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);
        (new ProcessTwilioTemplateWebhook('HXtest_paused', 'paused'))->handle();
        $template5->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DISABLED, $template5->status);
    }

    #[Test]
    public function test_job_skips_unchanged_status(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_same_status',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $updatedAtBefore = $template->updated_at;

        Sleep::usleep(1000);

        $job = new ProcessTwilioTemplateWebhook('HXtest_same_status', 'approved');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $template->status);
        $this->assertEquals($updatedAtBefore, $template->updated_at);
    }

    #[Test]
    public function test_job_skips_unknown_status(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'twilio_template_sid' => 'HXtest_unknown',
            'status' => WhatsAppTemplateStatusEnum::PENDING,
        ]);

        $job = new ProcessTwilioTemplateWebhook('HXtest_unknown', 'UNKNOWN_STATUS');
        $job->handle();

        $template->refresh();

        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $template->status);
    }

    #[Test]
    public function test_job_is_unique_by_content_sid(): void
    {
        $job = new ProcessTwilioTemplateWebhook('HXtest_unique_sid', 'approved');

        $this->assertEquals('HXtest_unique_sid', $job->uniqueId());
    }

    #[Test]
    public function test_job_has_correct_queue_configuration(): void
    {
        $job = new ProcessTwilioTemplateWebhook('HXtest_config_sid', 'approved');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->backoff);
        $this->assertEquals(120, $job->timeout);
    }
}
