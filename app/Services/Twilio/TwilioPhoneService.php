<?php

namespace App\Services\Twilio;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Helpers\ParserHelper;
use App\Interfaces\PhoneNumberManagementInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            $numbersList = array_map(fn ($number) => [
                'phoneNumber' => $number->phoneNumber,
                'friendlyName' => $number->friendlyName,
                'locality' => $number->locality,
                'region' => $number->region,
                'isoCountry' => $number->isoCountry,
            ], $numbers);

            return [
                'success' => true,
                'numbers' => $numbersList,
            ];
        } catch (RestException $e) {
            Log::error('Twilio number search failed', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'numbers' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Purchase a phone number.
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        $lockKey = "twilio:purchase-number:{$gymId}";

        try {
            return Cache::lock($lockKey, 120)->block(30, function () use ($phoneNumber) {
                $account = $this->context->getAccountOrFail();
                $subClient = $this->context->getClientForAccount($account);

                if ($this->hasActiveNumber($account->id, $phoneNumber)) {
                    return [
                        'success' => true,
                        'phone_number' => $phoneNumber,
                        'message' => 'Number already purchased',
                    ];
                }

                // Check if subaccount already has a number
                $incomingNumber = $this->fetchOrCreateTwilioNumber($subClient, $account, $phoneNumber);

                $this->attachNumberToMessagingService($subClient, $account, $incomingNumber->sid);

                $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $incomingNumber->phoneNumber);

                return DB::transaction(function () use ($incomingNumber, $channelSenderSid, $account) {
                    WhatsAppPhoneNumber::create([
                        'whatsapp_account_id' => $account->id,
                        'phone_number' => $incomingNumber->phoneNumber,
                        'phone_number_sid' => $incomingNumber->sid,
                        'channel_sender_sid' => $channelSenderSid,
                        'country_code' => $incomingNumber->isoCountry ?? 'US',
                        'friendly_name' => $incomingNumber->friendlyName,
                        'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                        'type' => WhatsAppPhoneNumberTypesEnum::LOCAL,
                        'purchased_at' => now(),
                        'number_cost' => (float) ($incomingNumber->price ?: config('services.twilio.number_cost', 136.41)),
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
                });
            });

        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'message' => 'Number purchase in progress. Please wait and try again.',
            ];
        } catch (RestException $e) {
            Log::error('Failed to purchase number', [
                'gym_id' => $gymId,
                'phone' => $phoneNumber,
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to purchase number from Twilio.',
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
        $lockKey = "twilio:register-number:{$gymId}";

        try {
            return Cache::lock($lockKey, 60)->block(15, function () use ($phoneNumber) {
                $account = $this->context->getAccountOrFail();
                $subClient = $this->context->getClientForAccount($account);

                $this->validateByodPrerequisites($account);

                if ($this->hasActiveNumber($account->id, $phoneNumber)) {
                    return [
                        'success' => true,
                        'phone_number' => $phoneNumber,
                        'message' => 'Number already registered',
                    ];
                }

                $senderSid = $this->registerWhatsAppSender($subClient, $account, $phoneNumber);

                if (! $senderSid) {
                    throw new \Exception('Failed to register sender with Twilio.');
                }

                $this->attachSenderToMessagingService($subClient, $account, $senderSid);

                WhatsAppPhoneNumber::updateOrCreate(
                    ['whatsapp_account_id' => $account->id],
                    [
                        'phone_number' => $phoneNumber,
                        'channel_sender_sid' => $senderSid,
                        'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                        'status' => 'purchased',
                    ]
                );

                return [
                    'success' => true,
                    'sender_sid' => $senderSid,
                    'status' => 'purchased',
                    'message' => 'Number registered. Please enter the OTP sent to your phone.',
                ];
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'message' => 'Registration in progress. Please wait and try again.',
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

            $this->validateByodPrerequisites($account);

            $subClient->messaging->v2->channelsSenders($senderSid)
                ->update(ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsUpdate([
                    'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration(
                        [
                            'verificationMethod' => 'sms',
                            'verificationCode' => $otpCode,
                        ]
                    ),
                ]));
            // ->update(['configuration' => ['verification_code' => $otpCode]]);

            return DB::transaction(function () use ($account, $senderSid) {
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
            });
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

    public function getPhoneNumberPricing(string $country = 'US'): ?array
    {
        try {
            $countryData = $this->context->getMasterClient()
                ->pricing->v1->phoneNumbers->countries($country)->fetch();

            return [
                'country' => $countryData->country,
                'iso_country' => $countryData->isoCountry,
                'price_unit' => $countryData->priceUnit,
                'phone_number_prices' => array_map(fn ($price) => [
                    'number_type' => $price['number_type'],
                    'base_price' => (float) $price['base_price'],
                    'current_price' => (float) $price['current_price'],
                ], $countryData->phone_number_prices),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch Twilio phone number pricing', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Check if number already exists in DB.
     */
    private function hasActiveNumber(int $accountId, string $phoneNumber): bool
    {
        $key = "twilio:has-active-number:{$phoneNumber}:{$accountId}";

        return Cache::flexible($key, [now()->addHour(), now()->addHours(5)], function () use ($accountId, $phoneNumber) {
            return WhatsAppPhoneNumber::where('whatsapp_account_id', $accountId)
                ->where('phone_number', $phoneNumber)
                ->exists();
        });
    }

    /**
     * Validate WABA and Messaging Service are configured.
     */
    private function validateByodPrerequisites(WhatsAppAccount $account): void
    {
        if (! $account->waba_id) {
            throw new \Exception('WABA ID is not configured. Please complete setup first.');
        }

        if (! $account->messaging_service_sid) {
            throw new \Exception('Messaging Service is not configured. Please complete setup first.');
        }
    }

    /**
     * Either use existing subaccount number or purchase a new one.
     */
    private function fetchOrCreateTwilioNumber(Client $subClient, WhatsAppAccount $account, string $phoneNumber): object
    {
        // Check if subaccount already has a number
        $existingNumbers = $subClient->incomingPhoneNumbers->read([], 1);

        if (! empty($existingNumbers)) {
            return $existingNumbers[0];
        }

        // Purchase new
        return $subClient->incomingPhoneNumbers->create([
            'phoneNumber' => $phoneNumber,
        ]);
    }

    /**
     * Register WhatsApp sender.
     */
    private function registerWhatsAppSender(Client $subClient, WhatsAppAccount $account, string $phoneNumber): ?string
    {
        try {
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

            return $sender->sid;
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
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => WhatsAppPhoneNumberTypesEnum::LOCAL,
                'purchased_at' => now(),
            ]
        );

        return ['success' => true, 'phone_number' => $existing->phoneNumber, 'message' => 'Using existing number'];
    }

    private function attachNumberToMessagingService(Client $client, \App\Models\WhatsAppAccount $account, string $phoneSid): void
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

    private function attachSenderToMessagingService(Client $client, \App\Models\WhatsAppAccount $account, string $senderSid): void
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
