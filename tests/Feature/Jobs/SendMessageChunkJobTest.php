<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Jobs\SendMessageChunkJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SendMessageChunkJob::class)]
final class SendMessageChunkJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_messages_to_the_phone_number_id_endpoint_not_the_waba_id(): void
    {
        // Arrange
        $gym = Gym::factory()->create();
        $wabaId = 'WABA_123456';
        $phoneNumberId = 'PHONE_999888';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => $wabaId,
            'meta_access_token' => 'EAABTEST',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'friendly_name' => '+918469290865',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => '+918469290865',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        /** @var array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<string, string>}> $chunk */
        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => '+918469290865',
            'member_name' => 'Test Member',
            'resolved_variables' => ['1' => 'Hello'],
        ]];

        Http::fake(function () {
            return Http::response(['messages' => [['id' => 'wamid.123']]], 200);
        });

        $job = new SendMessageChunkJob(
            broadcastId: $broadcast->id,
            recipientChunk: $chunk,
            templateData: [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ],
            gymId: $gym->id,
            skipCreditDeduction: true,
        );

        // Act
        $job->handle();

        // Assert
        Http::assertSent(function (Request $request) use ($phoneNumberId, $wabaId): bool {
            $url = $request->url();

            return str_contains($url, "/{$phoneNumberId}/messages")
                && ! str_contains($url, "/{$wabaId}/messages");
        });

        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function it_skips_sending_messages_to_opted_out_or_archived_members(): void
    {
        // Arrange
        $gym = Gym::factory()->create();
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'WABA_123456',
            'meta_access_token' => 'EAABTEST',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => 'PHONE_999888',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'friendly_name' => '+918469290865',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $optedOutMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $optedOutMember->id,
            'phone_number' => '+918469290865',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        /** @var array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<string, string>}> $chunk */
        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => '+918469290865',
            'member_name' => 'Opted Out Member',
            'resolved_variables' => [],
        ]];

        Http::fake();

        $job = new SendMessageChunkJob(
            broadcastId: $broadcast->id,
            recipientChunk: $chunk,
            templateData: [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ],
            gymId: $gym->id,
            skipCreditDeduction: true,
        );

        // Act
        $job->handle();

        // Assert
        Http::assertNothingSent();

        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Member opted out or was archived before send',
        ]);
    }
}
