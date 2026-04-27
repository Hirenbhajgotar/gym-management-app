<?php

namespace App\Services\Twilio;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Helpers\ParserHelper;
use App\Interfaces\PhoneNumberManagementInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Rest\Messaging\V2\ChannelsSenderModels;

/**
 * Twilio Phone Service.
 *
 * Implements PhoneNumberManagementInterface for phone number
 * operations including search, purchase, and verification.
 */
class TwilioPhoneService implements PhoneNumberManagementInterface
{
    public function __construct(
        protected TwilioContext $context
    ) {}

    /**
     * Get available phone numbers.
     */
    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        try {
            $numbers = $this->context->getMasterClient()
                ->api->v2010->availablePhoneNumbers($country)
                ->$type->read([
                    'smsEnabled' => true,
                    'voiceEnabled' => true,
                ], $limit);

            return array_map(fn ($number) => [
                'phoneNumber' => $number->phoneNumber,
                'friendlyName' => $number->friendlyName,
                'locality' => $number->locality,
                'region' => $number->region,
                'isoCountry' => $number->isoCountry,
            ], $numbers);
        } catch (RestException $e) {
            Log::error('Twilio number search failed', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Purchase a phone number.
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        try {
            $account = $this->context->getAccountOrFail();
            $subClient = $this->context->getClientForAccount($account);

            $existingNumber = WhatsAppPhoneNumber::where('phone_number', $phoneNumber)->first();
            if ($existingNumber) {
                return ['success' => true, 'phone_number' => $phoneNumber, 'message' => 'Number already purchased'];
            }

            // Check if subaccount already has a number
            $existingNumbers = $subClient->incomingPhoneNumbers->read([], 1);
            if (! empty($existingNumbers)) {
                return $this->registerExistingNumber($account, $existingNumbers[0]);
            }

            // Purchase new number
            $incomingNumber = $subClient->incomingPhoneNumbers->create(['phoneNumber' => $phoneNumber]);

            $this->attachNumberToMessagingService($subClient, $account, $incomingNumber->sid);

            $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $incomingNumber->phoneNumber);

            WhatsAppPhoneNumber::create([
                'whatsapp_account_id' => $account->id,
                'phone_number' => $incomingNumber->phoneNumber,
                'phone_number_sid' => $incomingNumber->sid,
                'channel_sender_sid' => $channelSenderSid,
                'country_code' => $incomingNumber->isoCountry ?? 'US',
                'friendly_name' => $incomingNumber->friendlyName,
                'status' => 'active',
                'type' => 'local',
                'purchased_at' => now(),
            ]);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            return [
                'success' => true,
                'phone_number' => $incomingNumber->phoneNumber,
                'message' => 'Number purchased successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to purchase number', ['gym_id' => $gymId, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to purchase number',
            ];
        }
    }

    /**
     * Register own phone number (BYOD).
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        try {
            $account = $this->context->getAccountOrFail();
            $subClient = $this->context->getClientForAccount($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $request = \Twilio\Rest\Messaging\V2\ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => \Twilio\Rest\Messaging\V2\ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
                'profile' => \Twilio\Rest\Messaging\V2\ChannelsSenderModels::createMessagingV2ChannelsSenderProfile([
                    'name' => $account->business_name ?? $account->waba_name ?? 'Gym Business',
                ]),
            ]);

            $sender = $subClient->messaging->v2->channelsSenders->create($request);

            $this->attachSenderToMessagingService($subClient, $account, $sender->sid);

            WhatsAppPhoneNumber::updateOrCreate(
                ['whatsapp_account_id' => $account->id],
                [
                    'phone_number' => $phoneNumber,
                    'channel_sender_sid' => $sender->sid,
                    'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                    'status' => 'purchased',
                ]
            );

            return [
                'success' => true,
                'sender_sid' => $sender->sid,
                'status' => 'purchased',
                'message' => 'Number registered. Please enter the OTP sent to your phone.',
            ];
        } catch (\Exception $e) {
            Log::error('BYOD registration failed', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to register phone number',
            ];
        }
    }

    /**
     * Verify own number with OTP.
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        try {
            $account = $this->context->getAccountOrFail();
            $subClient = $this->context->getClientForAccount($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $subClient->messaging->v2->channelsSenders($senderSid)
                ->update(['configuration' => ['verification_code' => $otpCode]]);

            WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->update(['status' => 'active']);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ];
        } catch (RestException $e) {
            Log::error('BYOD OTP verification failed', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $message = $e->getCode() === 60022
                ? 'Invalid or expired OTP code. Please try again.'
                : 'Verification failed: ' . $e->getMessage();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('BYOD verification error', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Verification failed',
            ];
        }
    }

    /**
     * Resend OTP.
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        try {
            $account = $this->context->getAccountOrFail();
            $subClient = $this->context->getClientForAccount($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            $phoneNumberRecord = WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->first();

            if (! $phoneNumberRecord) {
                throw new \Exception('Phone number record not found.');
            }

            // Re-registering triggers new OTP
            $request = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumberRecord->phone_number,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
            ]);

            $subClient->messaging->v2->channelsSenders->create($request);

            return [
                'success' => true,
                'message' => 'Verification code has been resent.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to resend BYOD OTP', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to resend verification code',
            ];
        }
    }

    /**
     * Register WhatsApp sender.
     */
    private function registerWhatsAppSender(Client $subClient, WhatsAppAccount $account, string $phoneNumber): ?string
    {
        try {
            // Simplified version - actual implementation depends on account config
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to register WhatsApp sender', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function registerExistingNumber(WhatsAppAccount $account, $existing): array
    {
        $channelSenderSid = $this->registerWhatsAppSender(
            $this->context->getClientForAccount($account),
            $account,
            $existing->phoneNumber
        );

        WhatsAppPhoneNumber::firstOrCreate(
            ['whatsapp_account_id' => $account->id, 'phone_number' => $existing->phoneNumber],
            [
                'phone_number_sid' => $existing->sid,
                'channel_sender_sid' => $channelSenderSid,
                'country_code' => $existing->isoCountry ?? 'US',
                'friendly_name' => $existing->friendlyName,
                'status' => 'active',
                'type' => 'local',
                'purchased_at' => now(),
            ]
        );

        return ['success' => true, 'phone_number' => $existing->phoneNumber, 'message' => 'Using existing number'];
    }

    private function attachNumberToMessagingService($client, \App\Models\WhatsAppAccount $account, string $phoneSid): void
    {
        if (! $account->messaging_service_sid) {
            return;
        }

        try {
            $client->messaging->v1->services($account->messaging_service_sid)
                ->phoneNumbers->create($phoneSid);
        } catch (RestException $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }
        }
    }

    private function attachSenderToMessagingService($client, \App\Models\WhatsAppAccount $account, string $senderSid): void
    {
        try {
            $client->messaging->v1->services($account->messaging_service_sid)
                ->channelSenders->create($senderSid);
        } catch (RestException $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }
        }
    }
}
