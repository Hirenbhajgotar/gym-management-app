<?php

namespace App\Services;

use App\Enums\WabaModeEnum;
use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Helpers\ParserHelper;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Rest\Messaging\V2\ChannelsSenderModels;

class TwilioAccountManager
{
    protected Client $masterClient;

    protected string $masterAccountSid;

    protected string $masterAuthToken;

    protected bool $useSandbox;

    protected string $sharedWabaId;

    protected bool $useIndividualWaba;

    public function __construct(?Client $masterClient = null)
    {
        $this->useSandbox = config('services.twilio.use_sandbox', true);
        $this->sharedWabaId = config('services.twilio.shared_waba_id', '');
        $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);

        if ($masterClient !== null) {
            $this->masterClient = $masterClient;
            $this->masterAccountSid = config('services.twilio.master_sid', '');
            $this->masterAuthToken = config('services.twilio.master_token', '');

            return;
        }

        if (function_exists('app') && app()->bound(Client::class)) {
            $this->masterClient = app(Client::class);
            $this->masterAccountSid = config('services.twilio.master_sid', '');
            $this->masterAuthToken = config('services.twilio.master_token', '');

            return;
        }

        $this->masterAccountSid = config('services.twilio.master_sid');
        $this->masterAuthToken = config('services.twilio.master_token');
        $this->masterClient = new Client(
            $this->masterAccountSid,
            $this->masterAuthToken,
        );
    }

    // =========================
    // Account Management
    // =========================

    public function createSubaccount(int $gymId, string $gymName, string $email): array
    {
        try {
            $existingAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

            if ($existingAccount?->twilio_account_sid) {
                return [
                    'success' => true,
                    'account_sid' => $existingAccount->twilio_account_sid,
                    'message' => 'Account already exists',
                ];
            }

            $subaccount = $this->masterClient->api->v2010->accounts->create([
                'friendlyName' => "Gym_{$gymId}_{$gymName}",
            ]);

            WhatsAppAccount::create([
                'gym_id' => $gymId,
                'twilio_account_sid' => $subaccount->sid,
                'twilio_auth_token' => $subaccount->authToken,
                'status' => WhatsAppAccountStatusEnum::PENDING,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
                'business_name' => $gymName,
                'business_email' => $email,
            ]);

            Log::info('Twilio subaccount created', [
                'gym_id' => $gymId,
                'account_sid' => $subaccount->sid,
            ]);

            return [
                'success' => true,
                'account_sid' => $subaccount->sid,
                'message' => 'Subaccount created successfully',
            ];
        } catch (RestException $e) {
            Log::error('Failed to create Twilio subaccount', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $message = $e->getCode() === 21217
                ? 'Maximum subaccounts reached. Please upgrade your Twilio plan.'
                : 'Failed to create Twilio subaccount';

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $message,
            ];
        }
    }

    public function createWaba(int $gymId, array $businessProfile): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);

            if ($account->waba_id && $account->messaging_service_sid) {
                return [
                    'success' => true,
                    'waba_id' => $account->waba_id,
                    'message' => 'WABA already exists',
                ];
            }

            $subClient = $this->makeSubaccountClient($account);

            if ($this->useIndividualWaba) {
                return $this->createIndividualWaba($account, $gymId, $subClient, $businessProfile);
            }

            return $this->createSharedWaba($account, $gymId, $subClient, $businessProfile);
        } catch (\Exception $e) {
            Log::error('Failed to create WABA', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create WABA',
            ];
        }
    }

    protected function createSharedWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    {
        if (! $account->waba_id) {
            $account->update([
                'waba_id' => $this->sharedWabaId,
                'waba_mode' => WabaModeEnum::SHARED->value,
            ]);
        }

        if (! $account->messaging_service_sid) {
            $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
            $account->update(['messaging_service_sid' => $service->sid]);
        }

        if (! empty($businessProfile['business_name'])) {
            $account->update([
                'business_name' => $businessProfile['business_name'],
                'business_email' => $businessProfile['business_email'] ?? $account->business_email,
                'business_description' => $businessProfile['business_description'] ?? '',
            ]);
        }

        Log::info('Shared WABA setup completed', [
            'gym_id' => $gymId,
            'waba_id' => $account->waba_id,
            'messaging_service_sid' => $account->messaging_service_sid,
        ]);

        return [
            'success' => true,
            'waba_id' => $account->waba_id,
            'messaging_service_sid' => $account->messaging_service_sid,
            'waba_mode' => 'shared',
            'message' => 'Shared WABA created successfully',
        ];
    }

    protected function createIndividualWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    {
        $account->update([
            'waba_mode' => WabaModeEnum::INDIVIDUAL->value,
            'waba_status' => WabaStatusEnum::PENDING->value,
        ]);

        if (! $account->messaging_service_sid) {
            $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
            $account->update(['messaging_service_sid' => $service->sid]);
        }

        if (! empty($businessProfile['business_name'])) {
            $account->update([
                'business_name' => $businessProfile['business_name'],
                'business_email' => $businessProfile['business_email'] ?? $account->business_email,
                'business_description' => $businessProfile['business_description'] ?? '',
            ]);
        }

        Log::info('Individual WABA setup initiated', [
            'gym_id' => $gymId,
            'messaging_service_sid' => $account->messaging_service_sid,
            'requires_embedded_signup' => true,
        ]);

        return [
            'success' => true,
            'messaging_service_sid' => $account->messaging_service_sid,
            'waba_mode' => 'individual',
            'requires_embedded_signup' => true,
            'message' => 'Please complete Embedded Signup to connect your WABA',
        ];
    }

    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        try {
            if (! $wabaId) {
                throw new \Exception('WABA ID is required');
            }

            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            $account = $this->getAccountOrFail($gymId);

            if (! $account) {
                throw new \Exception('Account not found');
            }

            $subClient = $this->makeSubaccountClient($account);

            $senderSid = $this->registerWhatsAppSender($subClient, $account, $phoneNumber, $wabaId);

            $account->update([
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::INDIVIDUAL->value,
                'waba_status' => WabaStatusEnum::PENDING->value,
                'embedded_signup_completed' => true,
            ]);

            if ($metaAccessToken) {
                $account->update(['meta_access_token' => $metaAccessToken]);
            }

            Log::info('Individual WABA connected', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
                'message' => 'Individual WABA connected successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect individual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to connect individual WABA',
            ];
        }
    }

    public function connectManualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);

            $subClient = $this->makeSubaccountClient($account);

            if (! $wabaId) {
                throw new \Exception('WABA ID is required');
            }

            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            // 1. Ensure Messaging Service exists
            if (! $account->messaging_service_sid) {
                $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
                $account->update(['messaging_service_sid' => $service->sid]);
                $account->refresh();
            }

            // 2 & 3. Register WhatsApp sender and attach to messaging service
            $senderSid = $this->registerWhatsAppSender($subClient, $account, $phoneNumber);

            if (! $senderSid) {
                return [
                    'success' => false,
                    'error' => 'Failed to register WhatsApp sender',
                    'message' => 'Failed to register WhatsApp sender',
                ];
            }

            // 4. Create local phone number record
            // Note: senderSid is the Channel Sender SID (CN...), not IncomingPhoneNumber SID
            // Since this is a manual/existing number, we may not have incoming phone number SID
            WhatsAppPhoneNumber::updateOrCreate(
                ['whatsapp_account_id' => $account->id],
                [
                    'phone_number' => $phoneNumber,
                    'channel_sender_sid' => $senderSid,
                    'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                    'status' => 'active', // Since it's existing, we assume it's active or will be handled via status cycles
                ]
            );

            // 5. Update account details
            $updateData = [
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::MANUAL,
                'waba_status' => WabaStatusEnum::PENDING,
                'embedded_signup_completed' => false,
                'status' => WhatsAppAccountStatusEnum::PENDING,
            ];

            if ($metaAccessToken) {
                $updateData['meta_access_token'] = $metaAccessToken;
            }

            $account->update($updateData);

            Log::info('Manual WABA and Phone Number connected', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'phone_number' => $phoneNumber,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
                'message' => 'Manual WABA connected successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect manual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to connect manual WABA',
            ];
        }
    }

    public function getWabaMode(int $gymId): string
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return 'not_created';
        }

        return $account->waba_mode?->value ?? 'shared';
    }

    public function requiresEmbeddedSignup(int $gymId): bool
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return false;
        }

        return $account->hasIndividualWaba() && ! $account->isEmbeddedSignupComplete();
    }

    public function searchNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        try {
            $numbers = $this->masterClient->api->v2010->availablePhoneNumbers($country)
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

    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        try {
            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            $account = $this->getAccountOrFail($gymId);

            $subClient = $this->makeSubaccountClient($account);

            $existingNumber = WhatsAppPhoneNumber::where('phone_number', $phoneNumber)->first();
            if ($existingNumber) {
                return [
                    'success' => true,
                    'phone_number' => $phoneNumber,
                    'message' => 'Number already purchased',
                ];
            }

            $existingNumbers = $subClient->incomingPhoneNumbers->read([], 1);
            if (! empty($existingNumbers)) {
                $existing = $existingNumbers[0];

                // Register as WhatsApp Sender - returns Channel Sender SID (CN...)
                $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $existing->phoneNumber);

                WhatsAppPhoneNumber::firstOrCreate(
                    [
                        'whatsapp_account_id' => $account->id,
                        'phone_number' => $existing->phoneNumber,
                    ],
                    [
                        'phone_number_sid' => $existing->sid, // IncomingPhoneNumber SID (PN...)
                        'channel_sender_sid' => $channelSenderSid, // Channel Sender SID (CN...)
                        'country_code' => $existing->isoCountry ?? 'US',
                        'friendly_name' => $existing->friendlyName,
                        'locality' => $existing->locality,
                        'region' => $existing->region,
                        'status' => 'active',
                        'type' => 'local',
                        'purchased_at' => now(),
                    ]
                );

                return [
                    'success' => true,
                    'phone_number' => $existing->phoneNumber,
                    'message' => 'Using existing number',
                ];
            }

            $incomingNumber = $subClient->incomingPhoneNumbers->create([
                'phoneNumber' => $phoneNumber,
            ]);

            if ($account->messaging_service_sid) {
                try {
                    $subClient->messaging->v1->services($account->messaging_service_sid)
                        ->phoneNumbers->create($incomingNumber->sid);
                } catch (RestException $e) {
                    if ($e->getStatusCode() !== 409) {
                        throw $e;
                    }
                }
            }

            // Register as WhatsApp Sender - returns Channel Sender SID (CN...)
            $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $incomingNumber->phoneNumber);

            WhatsAppPhoneNumber::create([
                'whatsapp_account_id' => $account->id,
                'phone_number' => $incomingNumber->phoneNumber,
                'phone_number_sid' => $incomingNumber->sid, // IncomingPhoneNumber SID (PN...)
                'channel_sender_sid' => $channelSenderSid, // Channel Sender SID (CN...)
                'country_code' => $incomingNumber->isoCountry ?? 'US',
                'friendly_name' => $incomingNumber->friendlyName,
                'locality' => $incomingNumber->locality,
                'region' => $incomingNumber->region,
                'status' => 'active',
                'type' => 'local',
                'purchased_at' => now(),
            ]);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            Log::info('Phone number purchased', [
                'gym_id' => $gymId,
                'phone_number' => $incomingNumber->phoneNumber,
            ]);

            return [
                'success' => true,
                'phone_number' => $incomingNumber->phoneNumber,
                'message' => 'Number purchased successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to purchase number', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to purchase number',
            ];
        }
    }

    /**
     * Register an external (BYOD) phone number as a WhatsApp sender.
     *
     * Creates the sender via Twilio Messaging v2 Channels API,
     * associates it with the subaccount's Messaging Service,
     * and triggers OTP verification.
     *
     * @return array{success: bool, sender_sid?: string, status?: string, message: string, error?: string}
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClient($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $request = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
                'profile' => ChannelsSenderModels::createMessagingV2ChannelsSenderProfile([
                    'name' => $account->business_name ?? $account->waba_name ?? 'Gym Business',
                ]),
            ]);

            // creates a WhatsApp sender in Twilio's system, adds it to the WABA connected to Twilio account, and triggers the OTP from Meta.
            $sender = $subClient->messaging->v2->channelsSenders->create($request);

            try {
                $subClient->messaging->v1->services($account->messaging_service_sid)
                    ->channelSenders
                    ->create($sender->sid);
            } catch (RestException $e) {
                Log::error('Failed to register own number', [
                    'gym_id' => $gymId,
                    'phone_number' => $phoneNumber,
                    'error' => $e->getMessage(),
                ]);
                if ($e->getStatusCode() !== 409) {
                    throw $e;
                }
            }

            // $subClient->messaging->v2->channels->senders($sender->sid)
            //     ->verifications
            //     ->create('sms');

            // Store Channel Sender SID separately - this is BYOD (Bring Your Own Number)
            // so there's no IncomingPhoneNumber SID
            WhatsAppPhoneNumber::updateOrCreate(
                ['whatsapp_account_id' => $account->id],
                [
                    'phone_number' => $phoneNumber,
                    'channel_sender_sid' => $sender->sid, // Channel Sender SID (CN...)
                    // phone_number_sid remains empty for BYOD since it's not a purchased Twilio number
                    'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                    'status' => 'purchased',
                ]
            );

            Log::info('BYOD number registered, OTP sent', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
                'sender_sid' => $sender->sid,
            ]);

            return [
                'success' => true,
                'sender_sid' => $sender->sid,
                'status' => 'purchased',
                'message' => 'Number registered. Please enter the OTP sent to your phone.',
            ];
        } catch (\Exception $e) {
            Log::error('BYOD registration failed', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
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
     * Verify a BYOD phone number using the OTP code.
     *
     * @return array{success: bool, message: string, error?: string}
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClient($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $subClient->messaging->v2->channelsSenders($senderSid)
                ->update([
                    'configuration' => [
                        'verification_code' => $otpCode,
                    ],
                ]);

            WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->update(['status' => 'active']);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            Log::info('BYOD number verified', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ];
        } catch (RestException $e) {
            Log::error('BYOD OTP verification failed', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
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
                'sender_sid' => $senderSid,
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
     * Resend the OTP code for a BYOD phone number verification.
     *
     * @return array{success: bool, message: string, error?: string}
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClient($account);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            $phoneNumberRecord = WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->first();

            if (! $phoneNumberRecord) {
                throw new \Exception('Phone number record not found.');
            }

            $phoneNumber = $phoneNumberRecord->phone_number;

            // For BYOD, re-registering the sender triggers a new OTP from Meta
            $request = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
            ]);

            $subClient->messaging->v2->channelsSenders->create($request);

            Log::info('BYOD OTP resent', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'channel' => $channel,
            ]);

            return [
                'success' => true,
                'message' => 'Verification code has been resent.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to resend BYOD OTP', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to resend verification code',
            ];
        }
    }

    // =========================
    // Tenant Cleanup (Deletion)
    // =========================

    /**
     * Clean up all Twilio resources for a tenant being permanently removed.
     *
     * Orchestrates the full cleanup sequence:
     * 1. Remove phone numbers from messaging service
     * 2. Deregister phone numbers from Meta/WhatsApp (handled by Twilio when sender is removed)
     * 3. Release purchased phone numbers from Twilio
     * 4. Delete the messaging service
     * 5. Close the Twilio subaccount
     */
    public function cleanupTenantResources(int $gymId): array
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account || ! $account->twilio_account_sid) {
            Log::info('No Twilio resources to clean up for gym', ['gym_id' => $gymId]);

            return [
                'success' => true,
                'message' => 'No Twilio resources to clean up',
                'cleaned' => [],
            ];
        }

        $cleaned = [];
        $errors = [];

        Log::info('Starting Twilio resource cleanup for gym', [
            'gym_id' => $gymId,
            'twilio_account_sid' => $account->twilio_account_sid,
            'waba_mode' => $account->waba_mode?->value,
        ]);

        // Step 1: Remove phone numbers from messaging service and release them
        $phoneResult = $this->releasePhoneNumbers($account);
        $cleaned['phone_numbers'] = $phoneResult;
        if (! $phoneResult['success']) {
            $errors[] = $phoneResult['error'];
        }

        // Step 2: Delete the messaging service
        $serviceResult = $this->deleteMessagingService($account);
        $cleaned['messaging_service'] = $serviceResult;
        if (! $serviceResult['success']) {
            $errors[] = $serviceResult['error'];
        }

        // Step 3: Close the Twilio subaccount
        $accountResult = $this->closeSubaccount($account);
        $cleaned['subaccount'] = $accountResult;
        if (! $accountResult['success']) {
            $errors[] = $accountResult['error'];
        }

        // Update local account status
        $account->update([
            'status' => WhatsAppAccountStatusEnum::SUSPENDED,
        ]);

        $success = empty($errors);

        Log::info('Twilio resource cleanup completed', [
            'gym_id' => $gymId,
            'success' => $success,
            'cleaned' => $cleaned,
            'errors' => $errors,
        ]);

        return [
            'success' => $success,
            'message' => $success
                ? 'All Twilio resources cleaned up successfully'
                : 'Twilio cleanup completed with errors',
            'cleaned' => $cleaned,
            'errors' => $errors,
        ];
    }

    /**
     * Release all purchased phone numbers for a WhatsApp account.
     *
     * This removes the phone number from the messaging service (which deregisters
     * it from Meta/WhatsApp) and then releases the number from Twilio.
     */
    protected function releasePhoneNumbers(WhatsAppAccount $account): array
    {
        $phoneNumbers = $account->phoneNumber()->get();

        if ($phoneNumbers->isEmpty()) {
            return ['success' => true, 'message' => 'No phone numbers to release', 'released' => 0];
        }

        $released = 0;
        $failed = 0;
        $lastError = null;

        try {
            $subClient = $this->makeSubaccountClient($account);

            foreach ($phoneNumbers as $phoneNumber) {
                try {
                    // Step 1: Remove Channel Sender from messaging service (deregisters from Meta/WhatsApp)
                    // Use channel_sender_sid if available, which is the correct SID for v2 Channel Senders
                    $channelSenderSid = $phoneNumber->getChannelSenderSid();
                    if ($account->messaging_service_sid && $channelSenderSid) {
                        try {
                            // Try to remove as Channel Sender first (v2)
                            $subClient->messaging->v1->services($account->messaging_service_sid)
                                ->channelSenders($channelSenderSid)
                                ->delete();

                            Log::info('Channel sender removed from messaging service', [
                                'phone_number' => $phoneNumber->phone_number,
                                'channel_sender_sid' => $channelSenderSid,
                                'messaging_service_sid' => $account->messaging_service_sid,
                            ]);
                        } catch (RestException $e) {
                            // If not found as channel sender, try as phone number (legacy v1)
                            if ($e->getStatusCode() === 404) {
                                try {
                                    $incomingPhoneSid = $phoneNumber->getIncomingPhoneNumberSid();
                                    if ($incomingPhoneSid) {
                                        $subClient->messaging->v1->services($account->messaging_service_sid)
                                            ->phoneNumbers($incomingPhoneSid)
                                            ->delete();

                                        Log::info('Phone number removed from messaging service (legacy)', [
                                            'phone_number' => $phoneNumber->phone_number,
                                            'phone_number_sid' => $incomingPhoneSid,
                                        ]);
                                    }
                                } catch (RestException $e2) {
                                    if ($e2->getStatusCode() !== 404) {
                                        Log::warning('Failed to remove phone from messaging service', [
                                            'phone_number' => $phoneNumber->phone_number,
                                            'error' => $e2->getMessage(),
                                        ]);
                                    }
                                }
                            } else {
                                Log::warning('Failed to remove sender from messaging service', [
                                    'phone_number' => $phoneNumber->phone_number,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    // Step 2: Release the incoming phone number from Twilio
                    // Only release if this is a purchased Twilio number (has incoming phone number SID)
                    $incomingPhoneSid = $phoneNumber->getIncomingPhoneNumberSid();
                    if ($incomingPhoneSid && $phoneNumber->isValidIncomingPhoneNumberSid()) {
                        try {
                            $subClient->incomingPhoneNumbers($incomingPhoneSid)->delete();

                            Log::info('Phone number released from Twilio', [
                                'phone_number' => $phoneNumber->phone_number,
                                'incoming_phone_sid' => $incomingPhoneSid,
                            ]);
                        } catch (RestException $e) {
                            if ($e->getStatusCode() !== 404) {
                                throw $e;
                            }
                            Log::info('Phone number already released or not found in Twilio', [
                                'phone_number' => $phoneNumber->phone_number,
                            ]);
                        }
                    } else {
                        // BYOD number - only has channel sender, no Twilio phone number to release
                        Log::info('BYOD number - skipping Twilio release', [
                            'phone_number' => $phoneNumber->phone_number,
                        ]);
                    }

                    // Update local record
                    $phoneNumber->update(['status' => 'released']);
                    $released++;
                } catch (\Exception $e) {
                    $failed++;
                    $lastError = $e->getMessage();

                    Log::error('Failed to release phone number', [
                        'phone_number' => $phoneNumber->phone_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'released' => $released,
                'failed' => $phoneNumbers->count(),
            ];
        }

        return [
            'success' => $failed === 0,
            'message' => "Released {$released} phone number(s), {$failed} failed",
            'released' => $released,
            'failed' => $failed,
            'error' => $lastError,
        ];
    }

    /**
     * Delete the messaging service for a WhatsApp account.
     */
    protected function deleteMessagingService(WhatsAppAccount $account): array
    {
        if (! $account->messaging_service_sid) {
            return ['success' => true, 'message' => 'No messaging service to delete'];
        }

        try {
            $subClient = $this->makeSubaccountClient($account);

            $subClient->messaging->v1
                ->services($account->messaging_service_sid)
                ->delete();

            Log::info('Messaging service deleted', [
                'messaging_service_sid' => $account->messaging_service_sid,
                'gym_id' => $account->gym_id,
            ]);

            return ['success' => true, 'message' => 'Messaging service deleted'];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 404) {
                return ['success' => true, 'message' => 'Messaging service already deleted'];
            }

            Log::error('Failed to delete messaging service', [
                'messaging_service_sid' => $account->messaging_service_sid,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Close a Twilio subaccount.
     *
     * Closing a subaccount releases all resources associated with it.
     * The account status is set to 'closed' and cannot be reopened.
     */
    protected function closeSubaccount(WhatsAppAccount $account): array
    {
        if (! $account->twilio_account_sid) {
            return ['success' => true, 'message' => 'No subaccount to close'];
        }

        try {
            $this->masterClient->api->v2010
                ->accounts($account->twilio_account_sid)
                ->update(['status' => 'closed']);

            Log::info('Twilio subaccount closed', [
                'account_sid' => $account->twilio_account_sid,
                'gym_id' => $account->gym_id,
            ]);

            return ['success' => true, 'message' => 'Subaccount closed'];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 404) {
                return ['success' => true, 'message' => 'Subaccount already closed or not found'];
            }

            Log::error('Failed to close Twilio subaccount', [
                'account_sid' => $account->twilio_account_sid,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================
    // Account Status
    // =========================

    public function getAccountStatus(string $tenantId): array
    {
        $account = WhatsAppAccount::where('gym_id', $tenantId)->first();

        if (! $account || ! $account->twilio_account_sid) {
            return [
                'status' => 'not_created',
                'message' => 'No WhatsApp account found',
            ];
        }

        try {
            $client = $this->makeSubaccountClient($account);

            $usage = $client->usage->records->read([
                'category' => 'totalprice',
                'startDate' => now()->startOfMonth()->format('Y-m-d'),
                'endDate' => now()->format('Y-m-d'),
            ]);

            $messageCount = 0;
            $cost = 0;
            foreach ($usage as $record) {
                $messageCount += $record->count;
                $cost += (float) $record->price;
            }

            return [
                'status' => $account->status->value,
                'verification_status' => $account->verification_status->value,
                'messages_sent' => $messageCount,
                'monthly_cost' => $cost,
                'account_created' => $account->created_at,
                'last_activity' => now(),
            ];
        } catch (RestException $e) {
            Log::error('Failed to fetch account status', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================
    // Test Messages
    // =========================

    public function sendTestWhatsAppMessage(WhatsAppAccount $account, ?string $to = null): array
    {
        try {
            if (empty($account->twilio_account_sid) || empty($account->twilio_auth_token)) {
                return [
                    'success' => false,
                    'error' => 'Twilio credentials are not configured.',
                ];
            }

            $whatsAppService = new TwilioWhatsAppService((string) $account->gym_id);

            $gym = Gym::find($account->gym_id);
            $gymName = $gym ? $gym->name : 'Your Gym';

            $phoneNumber = $account->phoneNumber()->first();
            $recipientNumber = $to ?? $phoneNumber?->phone_number;

            if (! $recipientNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured.',
                ];
            }

            if ($this->useSandbox) {
                $messageBody = "🎉 *WhatsApp Integration Active!*\n\n";
                $messageBody .= "Hello from {$gymName}! 👋\n\n";
                $messageBody .= "Your WhatsApp Business integration is now successfully set up.\n\n";
                $messageBody .= '_Sent at: ' . now()->format('M d, Y H:i A') . '_';

                $result = $whatsAppService->sendMessage($recipientNumber, $messageBody);
            } else {
                $templateSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

                if (! $templateSid) {
                    return [
                        'success' => false,
                        'error' => 'No approved WhatsApp template found.',
                        'requires_template' => true,
                    ];
                }

                $result = $whatsAppService->sendMessage($recipientNumber, '', [
                    'template_sid' => $templateSid,
                    'template_variables' => ['1' => $gymName],
                ]);
            }

            Log::info('Test WhatsApp message sent', [
                'gym_id' => $account->gym_id,
                'success' => $result['success'] ?? false,
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
            ]);

            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['status'] ?? null,
                'to' => $recipientNumber,
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
                'error' => $result['error'] ?? null,
                'requires_template' => $result['requires_template'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send test WhatsApp message', [
                'gym_id' => $account->gym_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================
    // Template Management
    // =========================

    public function submitTemplate(WhatsAppTemplate $template): array
    {
        return app(WhatsAppTemplateService::class)->submitForVerification($template);
    }

    // =========================
    // Helper Methods
    // =========================

    protected function getAccountOrFail(int $gymId): WhatsAppAccount
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            throw new \Exception('WhatsApp account not found for this gym');
        }

        return $account;
    }

    protected function makeSubaccountClient(WhatsAppAccount $account): Client
    {
        if ($this->useSandbox) {
            return new Client($this->masterAccountSid, $this->masterAuthToken);
        }

        return new Client(
            $account->twilio_account_sid,
            $account->twilio_auth_token
        );
    }

    protected function makeClient(string $sid, string $token): Client
    {
        if ($this->useSandbox) {
            return new Client($this->masterAccountSid, $this->masterAuthToken);
        }

        return new Client($sid, $token);
    }

    // =========================
    // Deprecated Methods (Backward Compatibility)
    // =========================

    /**
     * @deprecated Use createSubaccount() instead
     */
    public function createAccountForGymOwner(string $tenantId, string $gymOwnerName, string $gymName, string $whatsappNumber, string $email): array
    {
        return $this->createSubaccount((int) $tenantId, $gymName, $email);
    }

    /**
     * @deprecated Use searchNumbers() instead
     */
    public function searchAvailablePhoneNumbers(string $countryCode = 'US', string $type = 'local', int $limit = 20): array
    {
        return $this->searchNumbers($countryCode, $type, $limit);
    }

    /**
     * @deprecated Use purchaseNumber() instead
     */
    public function setupAccountWithPurchasedNumber(string $tenantId, string $gymOwnerName, string $gymName, string $phoneNumber, string $email): array
    {
        $subaccountResult = $this->createSubaccount((int) $tenantId, $gymName, $email);

        if (! $subaccountResult['success']) {
            return $subaccountResult;
        }

        $wabaResult = $this->createWaba((int) $tenantId, [
            'business_name' => $gymName,
            'business_email' => $email,
        ]);

        if (! $wabaResult['success']) {
            return $wabaResult;
        }

        return $this->purchaseNumber((int) $tenantId, $phoneNumber);
    }

    /**
     * @deprecated Use TwilioWhatsAppService directly
     */
    public function purchasePhoneNumber(string $phoneNumber, string $accountSid, string $authToken, string $messagingServiceSid): array
    {
        try {
            $client = new Client($accountSid, $authToken);

            $existingNumbers = $client->incomingPhoneNumbers->read([
                'phoneNumber' => $phoneNumber,
            ], 1);

            if (! empty($existingNumbers)) {
                $incomingPhoneNumber = $existingNumbers[0];
            } else {
                $incomingPhoneNumber = $client->incomingPhoneNumbers->create([
                    'phoneNumber' => $phoneNumber,
                ]);
            }

            try {
                $client->messaging->v1->services($messagingServiceSid)
                    ->phoneNumbers->create($incomingPhoneNumber->sid);
            } catch (RestException $e) {
                if ($e->getStatusCode() !== 409) {
                    throw $e;
                }
            }

            return [
                'success' => true,
                'sid' => $incomingPhoneNumber->sid,
                'phoneNumber' => $incomingPhoneNumber->phoneNumber,
            ];
        } catch (RestException $e) {
            Log::error('Number purchase failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @deprecated WhatsApp verification is now handled differently
     */
    public function startWhatsAppVerification(string $accountSid, string $authToken, string $whatsappNumber): array
    {
        return [
            'success' => true,
            'message' => 'Verification handled through Meta Business Suite',
        ];
    }

    /**
     * @deprecated WhatsApp verification is now handled differently
     */
    public function completeWhatsAppVerification(string $tenantId, string $verificationCode): array
    {
        $account = WhatsAppAccount::where('gym_id', $tenantId)->first();

        if (! $account) {
            return [
                'success' => false,
                'error' => 'No WhatsApp account found',
            ];
        }

        $account->update([
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verified_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'WhatsApp number verified successfully',
        ];
    }

    /**
     * @deprecated Not used in new implementation
     */
    public function fetchLatestIncomingMessage(string $tenantId, string $phoneNumber): ?string
    {
        try {
            $account = WhatsAppAccount::where('gym_id', $tenantId)->first();

            if (! $account || ! $account->twilio_account_sid) {
                return null;
            }

            $client = $this->makeSubaccountClient($account);

            $messages = $client->messages->read([
                'to' => $phoneNumber,
                'limit' => 1,
            ]);

            return ! empty($messages) ? $messages[0]->body : null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch incoming messages', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @deprecated Not used in new implementation
     */
    public function assignNumberToWABA(string $accountSid, string $authToken, string $phoneNumberSid, string $messagingServiceSid): array
    {
        try {
            $client = $this->makeClient($accountSid, $authToken);

            $phoneNumber = $client->messaging->v1
                ->services($messagingServiceSid)
                ->phoneNumbers
                ->create($phoneNumberSid);

            return [
                'success' => true,
                'sid' => $phoneNumber->sid,
                'phone_number' => $phoneNumber->phoneNumber ?? null,
            ];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 409) {
                return [
                    'success' => true,
                    'already_assigned' => true,
                ];
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getOrCreateVerifyService(Client $client, string $accountSid): string
    {
        $tenantId = WhatsAppAccount::where('twilio_account_sid', $accountSid)->value('gym_id')
            ?? (Filament::getTenant()->id ?? 'unknown');

        $friendlyName = "Verify Service for {$tenantId}";

        $services = $client->verify->v2->services->read();
        foreach ($services as $service) {
            if ($service->friendlyName === $friendlyName) {
                return $service->sid;
            }
        }

        $service = $client->verify->v2->services->create($friendlyName);

        return $service->sid;
    }

    private function cacheVerifyServiceSid(string $accountSid, string $serviceSid): void
    {
        Cache::put("twilio_verify_service:{$accountSid}", $serviceSid, now()->addDays(30));
    }

    private function getCachedVerifyServiceSid(string $accountSid): ?string
    {
        return Cache::get("twilio_verify_service:{$accountSid}");
    }

    /**
     * Register a phone number as a WhatsApp sender and attach it to the messaging service.
     */
    public function registerWhatsAppSender(Client $subClient, WhatsAppAccount $account, string $phoneNumber, ?string $wabaId = null): ?string
    {
        $whatsAppBusinessId = $wabaId ?? $account->waba_id;
        if (! $whatsAppBusinessId) {
            return null;
        }

        try {
            $sender = $subClient->messaging->v2->channelsSenders->create(
                ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                    'sender_id' => 'whatsapp:' . $phoneNumber,
                    'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                        'waba_id' => $whatsAppBusinessId,
                    ]),
                ])
            );

            // Attach to messaging service if available
            if ($account->messaging_service_sid) {
                try {
                    $subClient->messaging->v1->services($account->messaging_service_sid)
                        ->channelSenders
                        ->create($sender->sid);
                } catch (RestException $e) {
                    // Ignore if already attached (409)
                    if ($e->getStatusCode() !== 409) {
                        Log::error('Failed to attach WhatsApp sender to messaging service', [
                            'sender_sid' => $sender->sid,
                            'messaging_service_sid' => $account->messaging_service_sid,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return $sender->sid;
        } catch (\Exception $e) {
            Log::warning('Failed to register WhatsApp sender', [
                'phone_number' => $phoneNumber,
                'waba_id' => $whatsAppBusinessId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
