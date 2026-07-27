<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportWhatsAppTemplatesJobTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_unit_sid',
            'twilio_auth_token' => 'test_unit_auth_token',
            'waba_id' => 'test_waba_123',
            'meta_access_token' => 'test_meta_token_123',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'business_name' => 'Test Unit Gym',
            'business_email' => 'testunit@gym.com',
        ]);
    }

    #[Test]
    public function test_job_requires_gym_id(): void
    {
        $job = new ImportWhatsAppTemplatesJob(1);

        $this->assertEquals(1, $job->gymId);
        $this->assertNull($job->providerType);
    }

    #[Test]
    public function test_job_is_unique_per_gym(): void
    {
        $job1 = new ImportWhatsAppTemplatesJob(1);
        $job2 = new ImportWhatsAppTemplatesJob(1);
        $job3 = new ImportWhatsAppTemplatesJob(2);

        $this->assertEquals($job1->uniqueId(), $job2->uniqueId());
        $this->assertNotEquals($job1->uniqueId(), $job3->uniqueId());
    }

    #[Test]
    public function test_handles_no_whatsapp_account(): void
    {
        $gymWithoutAccount = Gym::factory()->create();

        $job = new ImportWhatsAppTemplatesJob($gymWithoutAccount->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $gymWithoutAccount->id,
        ]);
    }

    #[Test]
    public function test_skips_inactive_account(): void
    {
        $this->whatsappAccount->status = WhatsAppAccountStatusEnum::SUSPENDED;
        $this->whatsappAccount->save();

        $job = new ImportWhatsAppTemplatesJob($this->gym->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function test_skips_restricted_account(): void
    {
        $this->whatsappAccount->status = WhatsAppAccountStatusEnum::RESTRICTED;
        $this->whatsappAccount->save();

        $job = new ImportWhatsAppTemplatesJob($this->gym->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function test_skips_banned_account(): void
    {
        $this->whatsappAccount->status = WhatsAppAccountStatusEnum::BANNED;
        $this->whatsappAccount->save();

        $job = new ImportWhatsAppTemplatesJob($this->gym->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function test_imports_templates_for_unverified_account(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->verification_status = WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED;
        $this->whatsappAccount->save();

        $twilioTemplates = [
            [
                'sid' => 'HXunverified_sid',
                'friendly_name' => 'Unverified Template',
                'types' => [
                    'twilio/text' => [
                        'body' => 'Template body for unverified account.',
                    ],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(1, $templates);
        $this->assertEquals('Unverified Template', $templates->first()?->name);
    }

    #[Test]
    public function test_imports_templates_for_pending_account(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::WABA;
        $this->whatsappAccount->status = WhatsAppAccountStatusEnum::PENDING;
        $this->whatsappAccount->verification_status = WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED;
        $this->whatsappAccount->save();

        $metaTemplates = [
            [
                'id' => 'meta_pending_1',
                'name' => 'Pending Account Template',
                'status' => 'APPROVED',
                'category' => 'UTILITY',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'Template from pending account.',
                    ],
                ],
            ],
        ];

        $this->mockMetaApiTemplates($metaTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::WABA);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(1, $templates);
        $this->assertEquals('Pending Account Template', $templates->first()?->name);
    }

    #[Test]
    public function test_syncs_status_from_meta_templates(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::WABA;
        $this->whatsappAccount->waba_id = 'test_waba_456';
        $this->whatsappAccount->save();

        $metaTemplates = [
            [
                'id' => 'meta_template_1',
                'name' => 'Appointment Confirmation',
                'status' => 'APPROVED',
                'category' => 'APPOINTMENT_UPDATE',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'Your appointment is confirmed for {{1}}.',
                    ],
                ],
            ],
            [
                'id' => 'meta_template_2',
                'name' => 'Welcome Message',
                'status' => 'PENDING',
                'category' => 'MARKETING',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'Welcome to our gym!',
                    ],
                ],
            ],
            [
                'id' => 'meta_template_3',
                'name' => 'Rejected Template',
                'status' => 'REJECTED',
                'category' => 'UTILITY',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'This was rejected.',
                    ],
                ],
            ],
            [
                'id' => 'meta_template_4',
                'name' => 'Disabled Template',
                'status' => 'DISABLED',
                'category' => 'UTILITY',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'This was disabled.',
                    ],
                ],
            ],
        ];

        $this->mockMetaApiTemplates($metaTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::WABA);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(4, $templates);

        $approved = $templates->firstWhere('name', 'Appointment Confirmation');
        $this->assertNotNull($approved);
        $this->assertEquals(WhatsAppTemplateStatusEnum::APPROVED, $approved->status);

        $pending = $templates->firstWhere('name', 'Welcome Message');
        $this->assertNotNull($pending);
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $pending->status);

        $rejected = $templates->firstWhere('name', 'Rejected Template');
        $this->assertNotNull($rejected);
        $this->assertEquals(WhatsAppTemplateStatusEnum::REJECTED, $rejected->status);

        $disabled = $templates->firstWhere('name', 'Disabled Template');
        $this->assertNotNull($disabled);
        $this->assertEquals(WhatsAppTemplateStatusEnum::DISABLED, $disabled->status);
    }

    #[Test]
    public function test_meta_template_falls_back_to_draft_when_status_missing(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::WABA;
        $this->whatsappAccount->waba_id = 'test_waba_456';
        $this->whatsappAccount->save();

        $metaTemplates = [
            [
                'id' => 'meta_template_no_status',
                'name' => 'No Status Template',
                'category' => 'UTILITY',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'No status field provided.',
                    ],
                ],
            ],
        ];

        $this->mockMetaApiTemplates($metaTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::WABA);
        $job->handle();

        $template = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->first();

        $this->assertNotNull($template);
        $this->assertEquals(WhatsAppTemplateStatusEnum::DRAFT, $template->status);
    }

    #[Test]
    public function test_imports_templates_as_draft_from_twilio(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        $twilioTemplates = [
            [
                'sid' => 'HXtest_template_sid',
                'friendly_name' => 'Welcome Template',
                'language' => 'en_US',
                'types' => [
                    'twilio/text' => [
                        'body' => 'Hello {{1}}, welcome to our gym!',
                    ],
                ],
            ],
            [
                'sid' => 'HXtest_template_sid_2',
                'friendly_name' => 'Reminder Template',
                'language' => 'en_US',
                'types' => [
                    'twilio/text' => [
                        'body' => 'Reminder: {{1}} at {{2}}',
                    ],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(2, $templates);

        foreach ($templates as $template) {
            $this->assertEquals(WhatsAppTemplateStatusEnum::DRAFT, $template->status);
            $this->assertTrue($template->is_active);
        }
    }

    #[Test]
    public function test_upserts_existing_templates(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        // Create existing template
        $existingTemplate = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Existing Template',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $twilioTemplates = [
            [
                'sid' => 'HXupdated_sid',
                'friendly_name' => 'Existing Template',
                'types' => [
                    'twilio/text' => [
                        'body' => 'Updated body content',
                    ],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        // Should still be 1 template (updated)
        $this->assertCount(1, WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get());

        // Template should be updated to DRAFT
        $existingTemplate->refresh();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DRAFT, $existingTemplate->status);
        $this->assertEquals('HXupdated_sid', $existingTemplate->twilio_template_sid);
    }

    #[Test]
    public function test_sets_provider_specific_ids(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        $twilioTemplates = [
            [
                'sid' => 'HXtemplate_sid_123',
                'friendly_name' => 'Test Template',
                'types' => [
                    'twilio/text' => ['body' => 'Test body'],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $template = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->first();

        $this->assertNotNull($template);
        $this->assertEquals('HXtemplate_sid_123', $template->twilio_template_sid);
        $this->assertNull($template->meta_template_id);
    }

    #[Test]
    public function test_handles_api_failure(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        Http::fake([
            'https://content.twilio.com/v1/Contents*' => Http::response([], 500),
        ]);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function test_extracts_variables_from_body(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        $twilioTemplates = [
            [
                'sid' => 'HXvar_template_sid',
                'friendly_name' => 'Variable Template',
                'types' => [
                    'twilio/text' => [
                        'body' => 'Hello {{1}}, your appointment at {{2}} is confirmed.',
                    ],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $template = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->first();

        $this->assertNotNull($template);
        $this->assertNotNull($template->variables);
        $this->assertIsArray($template->variables);
        $this->assertGreaterThanOrEqual(1, count($template->variables));
        $this->assertEquals(2, $template->variable_count);
    }

    #[Test]
    public function test_skips_templates_without_name(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        $twilioTemplates = [
            [
                'sid' => 'HXno_name_sid',
                // No friendly_name or name
                'types' => [
                    'twilio/text' => ['body' => 'Body without name'],
                ],
            ],
            [
                'sid' => 'HXwith_name_sid',
                'friendly_name' => 'Valid Template',
                'types' => [
                    'twilio/text' => ['body' => 'Valid body'],
                ],
            ],
        ];

        $this->mockTwilioApiTemplates($twilioTemplates);

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        // Only the template with a name should be imported
        $this->assertCount(1, $templates);
        $firstTemplate = $templates->first();
        $this->assertNotNull($firstTemplate);
        $this->assertEquals('Valid Template', $firstTemplate->name);
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function mockMetaApiTemplates(array $templates): void
    {
        $wabaId = $this->whatsappAccount->waba_id;
        $apiVersion = config()->string('services.whatsapp.api_version', 'v25.0');

        Http::fake([
            "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates*" => Http::response([
                'data' => $templates,
            ], 200),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function mockTwilioApiTemplates(array $templates): void
    {
        Http::fake([
            'https://content.twilio.com/v1/Contents*' => Http::response([
                'contents' => $templates,
            ], 200),
        ]);
    }
}
