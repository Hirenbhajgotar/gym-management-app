<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Twilio\Rest\Client;

class TwilioAccountManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;
    protected WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MS123',
        ]);
    }

    
    public function it_registers_whatsapp_sender_when_purchasing_number(): void
    {
        // Mock Twilio Client and its nested resources
        $mockClient = Mockery::mock(Client::class);
        $mockSubClient = Mockery::mock(Client::class);
        
        // Mock IncomingPhoneNumbers
        $incomingPhoneNumbersMock = Mockery::mock();
        $mockSubClient->incomingPhoneNumbers = $incomingPhoneNumbersMock;
        $incomingPhoneNumbersMock->shouldReceive('read')->andReturn([]);
        
        $purchasedNumberMock = (object)[
            'sid' => 'PN123',
            'phoneNumber' => '+14155551234',
            'isoCountry' => 'US',
            'friendlyName' => 'Test Number',
            'locality' => 'Test City',
            'region' => 'Test Region',
        ];
        $incomingPhoneNumbersMock->shouldReceive('create')->andReturn($purchasedNumberMock);

        // Mock Messaging Service Attachment (PN)
        $msgServiceMock = Mockery::mock();
        $mockSubClient->messaging = $msgServiceMock;
        $v1Mock = Mockery::mock();
        $msgServiceMock->v1 = $v1Mock;
        $serviceInstanceMock = Mockery::mock();
        $v1Mock->shouldReceive('services')->with('MS123')->andReturn($serviceInstanceMock);
        $phoneNumbersResourceMock = Mockery::mock();
        $serviceInstanceMock->phoneNumbers = $phoneNumbersResourceMock;
        $phoneNumbersResourceMock->shouldReceive('create')->with('PN123');

        // Mock Messaging V2 Channels Senders (WhatsApp Registration)
        $v2Mock = Mockery::mock();
        $msgServiceMock->v2 = $v2Mock;
        $channelsSendersMock = Mockery::mock();
        $v2Mock->channelsSenders = $channelsSendersMock;
        
        $senderMock = (object)['sid' => 'XE123'];
        $channelsSendersMock->shouldReceive('create')->once()->andReturn($senderMock);

        // Mock Messaging Service Attachment (XE)
        $channelSendersResourceMock = Mockery::mock();
        $serviceInstanceMock->channelSenders = $channelSendersResourceMock;
        $channelSendersResourceMock->shouldReceive('create')->with('XE123')->once();

        // Inject the mocked subaccount client
        $manager = Mockery::mock(TwilioAccountManager::class)->makePartial();
        $manager->shouldAllowMockingProtectedMethods();
        $manager->shouldReceive('makeSubaccountClient')->andReturn($mockSubClient);
        $manager->shouldReceive('getAccountOrFail')->andReturn($this->account);

        // Execute
        $result = $manager->purchaseNumber($this->gym->id, '+14155551234');

        // Assertions
        $this->assertTrue($result['success']);
        
        $phoneNumberRecord = WhatsAppPhoneNumber::where('phone_number', '+14155551234')->first();
        $this->assertNotNull($phoneNumberRecord);
        $this->assertEquals('XE123', $phoneNumberRecord->phone_number_sid); // Should store Sender SID
    }
}
