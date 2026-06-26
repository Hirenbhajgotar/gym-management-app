<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportTenantWhatsAppTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create([
            'name' => 'Test Gym',
        ]);

        $this->whatsappAccount = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_feature_sid',
            'twilio_auth_token' => 'test_feature_auth_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'business_name' => 'Test Feature Gym',
            'business_email' => 'testfeature@gym.com',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            $this->artisan('whatsapp:import-templates', ['--help' => true])
                ->expectsOutputToContain('whatsapp:import-templates')
                ->isSuccessful()
        );
    }

    #[Test]
    public function test_help_shows_options(): void
    {
        $this->artisan('whatsapp:import-templates', ['--help' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('--gym')
            ->expectsOutputToContain('--sync');
    }

    #[Test]
    public function test_imports_single_gym(): void
    {
        Queue::fake();

        $this->artisan('whatsapp:import-templates', ['--gym' => $this->gym->id])
            ->assertSuccessful()
            ->expectsOutputToContain("Gym ID: {$this->gym->id}")
            ->expectsOutputToContain('Job dispatched');

        Queue::assertPushed(ImportWhatsAppTemplatesJob::class, function ($job) {
            return $job->gymId === $this->gym->id;
        });
    }

    #[Test]
    public function test_imports_single_gym_sync(): void
    {
        Queue::fake();

        $this->artisan('whatsapp:import-templates', [
            '--gym' => $this->gym->id,
            '--sync' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Running synchronously');
    }

    #[Test]
    public function test_imports_all_gyms_with_accounts(): void
    {
        Queue::fake();

        // Create additional gyms with WhatsApp accounts
        $gym2 = Gym::factory()->create(['name' => 'Second Gym']);
        WhatsAppAccount::query()->create([
            'gym_id' => $gym2->id,
            'twilio_account_sid' => 'ACtest_gym2_sid',
            'twilio_auth_token' => 'test_gym2_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
        ]);

        $this->artisan('whatsapp:import-templates')
            ->assertSuccessful()
            ->expectsOutputToContain('Found 2 gym(s)')
            ->expectsOutputToContain('Jobs dispatched/synced: 2');

        Queue::assertPushed(ImportWhatsAppTemplatesJob::class, 2);
    }

    #[Test]
    public function test_shows_error_for_nonexistent_gym(): void
    {
        $this->artisan('whatsapp:import-templates', ['--gym' => 99999])
            ->assertSuccessful()
            ->expectsOutputToContain('not found');
    }

    #[Test]
    public function test_shows_error_for_gym_without_account(): void
    {
        $gymWithoutAccount = Gym::factory()->create();

        $this->artisan('whatsapp:import-templates', ['--gym' => $gymWithoutAccount->id])
            ->assertSuccessful()
            ->expectsOutputToContain('does not have a WhatsApp account');
    }

    #[Test]
    public function test_skips_inactive_gyms(): void
    {
        Queue::fake();

        $inactiveGym = Gym::factory()->create(['name' => 'Inactive Gym']);
        WhatsAppAccount::query()->create([
            'gym_id' => $inactiveGym->id,
            'twilio_account_sid' => 'ACinactive_sid',
            'twilio_auth_token' => 'inactive_token',
            'status' => WhatsAppAccountStatusEnum::INACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
        ]);

        $this->artisan('whatsapp:import-templates', ['--gym' => $inactiveGym->id])
            ->assertSuccessful()
            ->expectsOutputToContain('does not have a WhatsApp account');
    }

    #[Test]
    public function test_shows_provider_type_in_output(): void
    {
        Queue::fake();

        $this->whatsappAccount->provider_type = WhatsAppProviderTypeEnum::WABA;
        $this->whatsappAccount->waba_id = 'test_waba_abc';
        $this->whatsappAccount->save();

        $this->artisan('whatsapp:import-templates', ['--gym' => $this->gym->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Provider:')
            ->expectsOutputToContain('Meta WABA');
    }

    #[Test]
    public function test_shows_warning_for_sync_mode(): void
    {
        Queue::fake();

        $this->artisan('whatsapp:import-templates', ['--gym' => $this->gym->id, '--sync' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Running in synchronous mode');
    }

    #[Test]
    public function test_no_gyms_found_message(): void
    {
        // Delete the account so no gyms have WhatsApp accounts
        $this->whatsappAccount->delete();

        $this->artisan('whatsapp:import-templates')
            ->assertSuccessful()
            ->expectsOutputToContain('No gyms with WhatsApp accounts found');
    }

    #[Test]
    public function test_job_is_dispatched_to_correct_queue(): void
    {
        Queue::fake();

        $this->artisan('whatsapp:import-templates', ['--gym' => $this->gym->id])
            ->assertSuccessful()
            ->expectsOutputToContain('whatsapp-templates');

        Queue::assertPushed(ImportWhatsAppTemplatesJob::class, function ($job) {
            return $job->queue() === 'whatsapp-templates';
        });
    }
}
