<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\TwilioWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Twilio\Rest\Client;

class TwilioWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected WhatsAppPhoneNumber $phoneNumber;

    protected WhatsAppTemplate $template;

    protected WhatsAppMessageCredit $credits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_sid',
            'twilio_auth_token' => 'test_auth_token',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->phoneNumber = WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'phone_number' => '+14155551234',
            'formatted_number' => '(415) 555-1234',
            'twilio_phone_number_sid' => 'PNtest123',
            'verification_status' => 'verified',
        ]);

        $this->template = WhatsAppTemplate::create([
            'gym_id' => $this->gym->id,
            'name' => 'welcome_message',
            'display_name' => 'Welcome Message',
            'body' => 'Hello {{1}}, welcome to {{2}}!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name'], ['name' => 'gym_name']],
            'variable_count' => 2,
            'twilio_template_sid' => 'HX_template_123',
            'status' => 'approved',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->credits = WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== SEND TEMPLATE MESSAGE ==========

    #[Test]
    public function it_sends_template_message_successfully(): void
    {
        $messageMock = Mockery::mock();
        $messageMock->sid = 'SM_test_message_sid';
        $messageMock->status = 'queued';

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->once()
            ->andReturn($messageMock);

        $clientMock = Mockery::mock(Client::class);
        $clientMock->messages = $messagesMock;

        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $result = $service->sendTemplateMessage(
            '+19876543210',
            'welcome_message',
            ['1' => 'John', '2' => 'FitZone']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('SM_test_message_sid', $result['message_id']);
    }

    #[Test]
    public function it_fails_when_template_not_found(): void
    {
        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $result = $service->sendTemplateMessage(
            '+19876543210',
            'nonexistent_template',
            []
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('not found or not approved', $result['error']);
    }

    #[Test]
    public function it_fails_when_template_is_not_approved(): void
    {
        $this->template->update(['status' => 'pending']);

        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $result = $service->sendTemplateMessage(
            '+19876543210',
            'welcome_message',
            []
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('not found or not approved', $result['error']);
    }

    #[Test]
    public function it_fails_when_no_phone_number_configured(): void
    {
        $this->phoneNumber->delete();

        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $result = $service->sendTemplateMessage(
            '+19876543210',
            'welcome_message',
            ['1' => 'John', '2' => 'FitZone']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('No phone number', $result['error']);
    }

    #[Test]
    public function it_fails_when_insufficient_credits(): void
    {
        $this->credits->update([
            'remaining_credits' => 0,
            'used_credits' => 100,
        ]);

        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $result = $service->sendTemplateMessage(
            '+19876543210',
            'welcome_message',
            ['1' => 'John', '2' => 'FitZone']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('Insufficient', $result['error']);
    }

    // ========== CREDIT BALANCE ==========

    #[Test]
    public function it_returns_credit_balance(): void
    {
        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $balance = $service->getCreditBalance();

        $this->assertEquals(100, $balance['total_credits']);
        $this->assertEquals(0, $balance['used_credits']);
        $this->assertEquals(100, $balance['remaining_credits']);
        $this->assertTrue($balance['can_send']);
    }

    #[Test]
    public function it_returns_zero_balance_when_no_credits(): void
    {
        $this->credits->delete();

        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $balance = $service->getCreditBalance();

        $this->assertEquals(0, $balance['total_credits']);
        $this->assertFalse($balance['can_send']);
    }

    // ========== MONTHLY USAGE ==========

    #[Test]
    public function it_returns_monthly_usage(): void
    {
        $clientMock = Mockery::mock(Client::class);
        $service = new TwilioWhatsAppService((string) $this->gym->id, $clientMock);

        $usage = $service->getMonthlyUsage(now()->year, now()->month);

        $this->assertIsArray($usage);
        $this->assertArrayHasKey('total_messages', $usage);
        $this->assertArrayHasKey('delivered', $usage);
        $this->assertArrayHasKey('failed', $usage);
        $this->assertArrayHasKey('total_cost', $usage);
    }

    // ========== HELPER METHOD ==========

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
