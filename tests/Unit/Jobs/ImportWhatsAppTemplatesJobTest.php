<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaTemplateService;
use App\Services\Twilio\TwilioTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
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
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Test Unit Gym',
            'business_email' => 'testunit@gym.com',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
        $this->whatsappAccount->status = 'inactive';
        $this->whatsappAccount->save();

        $job = new ImportWhatsAppTemplatesJob($this->gym->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function test_skips_unverified_account(): void
    {
        $this->whatsappAccount->verification_status = 'unverified';
        $this->whatsappAccount->save();

        $job = new ImportWhatsAppTemplatesJob($this->gym->id);
        $job->handle();

        $this->assertDatabaseMissing('whatsapp_templates', [
            'gym_id' => $this->gym->id,
        ]);
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

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) use ($twilioTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->with($this->gym->id)
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $twilioTemplates,
                    count: 2,
                ));
        });

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
    public function test_imports_templates_as_draft_from_meta(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::WABA;
        $this->whatsappAccount->waba_id = 'test_waba_456';
        $this->whatsappAccount->save();

        $metaTemplates = [
            [
                'id' => 'meta_template_1',
                'name' => 'Appointment Confirmation',
                'category' => 'APPOINTMENT_UPDATE',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => 'Your appointment is confirmed for {{1}}.',
                    ],
                ],
            ],
        ];

        $this->mock(MetaTemplateService::class, function (MockInterface $mock) use ($metaTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->with($this->gym->id)
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $metaTemplates,
                    count: 1,
                ));
        });

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::WABA);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(1, $templates);

        $template = $templates->first();
        $this->assertEquals(WhatsAppTemplateStatusEnum::DRAFT, $template->status);
        $this->assertEquals('Appointment Confirmation', $template->name);
        $this->assertEquals('meta_template_1', $template->meta_template_id);
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

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) use ($twilioTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $twilioTemplates,
                    count: 1,
                ));
        });

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

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) use ($twilioTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $twilioTemplates,
                    count: 1,
                ));
        });

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $template = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->first();

        $this->assertEquals('HXtemplate_sid_123', $template->twilio_template_sid);
        $this->assertNull($template->meta_template_id);
    }

    #[Test]
    public function test_handles_api_failure(): void
    {
        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::TWILIO;
        $this->whatsappAccount->save();

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->andReturn(new TemplateCollectionResult(
                    success: false,
                    templates: [],
                    error: 'API connection failed',
                ));
        });

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

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) use ($twilioTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $twilioTemplates,
                    count: 1,
                ));
        });

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $template = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->first();

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

        $this->mock(TwilioTemplateService::class, function (MockInterface $mock) use ($twilioTemplates) {
            $mock->shouldReceive('getTemplates')
                ->once()
                ->andReturn(new TemplateCollectionResult(
                    success: true,
                    templates: $twilioTemplates,
                    count: 2,
                ));
        });

        $job = new ImportWhatsAppTemplatesJob($this->gym->id, WhatsAppProviderTypeEnum::TWILIO);
        $job->handle();

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        // Only the template with a name should be imported
        $this->assertCount(1, $templates);
        $this->assertEquals('Valid Template', $templates->first()->name);
    }
}
