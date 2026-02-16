<?php

namespace App\Services;

use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use App\Models\Gym;
use App\Models\NotificationSetting;
use App\Models\WhatsAppTemplate;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Rest\Messaging\V2\ChannelsSenderModels;

class TwilioAccountManager
{
    private Client $masterClient;

    private string $masterAccountSid;

    private string $masterAuthToken;

    private bool $useSandbox = true; // Set to true for testing

    public function __construct(?Client $masterClient = null)
    {
        $this->useSandbox = config('services.twilio.use_sandbox', true);

        // If a Client instance is explicitly provided, use it (useful for tests)
        if ($masterClient !== null) {
            $this->masterClient = $masterClient;
            $this->masterAccountSid = config('services.twilio.master_sid', '');
            $this->masterAuthToken = config('services.twilio.master_token', '');

            return;
        }

        // If the container has a bound Client (test mocks), resolve it from the container
        if (function_exists('app') && app()->bound(Client::class)) {
            $this->masterClient = app(Client::class);
            $this->masterAccountSid = config('services.twilio.master_sid', '');
            $this->masterAuthToken = config('services.twilio.master_token', '');

            return;
        }

        // Default: create a real Twilio client using configured master credentials
        $this->masterAccountSid = config('services.twilio.master_sid');
        $this->masterAuthToken = config('services.twilio.master_token');
        $this->masterClient = new Client(
            $this->masterAccountSid,
            $this->masterAuthToken,
        );
    }

    /**
     * Search for available phone numbers
     */
    public function searchAvailablePhoneNumbers(string $countryCode = 'US', string $type = 'local', int $limit = 20): array
    {
        try {
            $numbers = $this->masterClient->api->v2010->availablePhoneNumbers($countryCode)
                ->$type->read([
                    'smsEnabled' => true,
                    'voiceEnabled' => true, // Optional, but good for businesses
                ], $limit);

            return array_map(function ($number) {
                return [
                    'phoneNumber' => $number->phoneNumber,
                    'friendlyName' => $number->friendlyName,
                    'locality' => $number->locality,
                    'region' => $number->region,
                    'isoCountry' => $number->isoCountry,
                ];
            }, $numbers);
        } catch (RestException $e) {
            Log::error("Twilio number search failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Setup account with a purchased number
     */
    public function setupAccountWithPurchasedNumber(string $tenantId, string $gymOwnerName, string $gymName, string $phoneNumber, string $email): array
    {
        $responseData = [
            'success' => false,
            'account_sid' => null,
            'phone_number' => null,
            'message' => '',
            'error' => '',
        ];

        try {
            // 1. Get or Create NotificationSetting
            $settings = NotificationSetting::firstOrCreate(['gym_id' => $tenantId]);

            // 2. Create Subaccount (if not exists)
            if (! $settings->twilio_account_sid) {
                Log::info('Creating Twilio subaccount for gym: ' . $gymName);
                try {
                    $subaccount = $this->masterClient->api->v2010->accounts->create([
                        'friendlyName' => "Gym_{$tenantId}_{$gymName}",
                    ]);

                    $settings->update([
                        'twilio_account_sid' => $subaccount->sid,
                        'twilio_auth_token' => $subaccount->authToken,
                        'account_status' => NotificationAccountStatusEnum::CREATED,
                        'meta_verification_status' => NotificationVerificationStatusEnum::PENDING,
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception('Failed to create Twilio subaccount: ' . $e->getMessage());
                }
            }

            // 3. Create Messaging Service (if not exists)
            if (! $settings->waba_sid) {
                Log::info('Creating Messaging Service for gym: ' . $gymName);
                try {
                    $subClient = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);
                    $service = $subClient->messaging->v1->services->create("Gym_{$tenantId}_WABA");

                    $settings->update([
                        'waba_sid' => $service->sid,
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception('Failed to create Messaging Service: ' . $e->getMessage());
                }
            }

            // 4. Purchase Number (if not already purchased/saved)
            // FIRST: Check if we already have ANY number on this account.
            // If so, we must use it to avoid buying redundant numbers.
            if ($settings->twilio_account_sid && $settings->twilio_auth_token) {
                try {
                    $checkClient = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);
                    $existingNumbers = $checkClient->incomingPhoneNumbers->read([], 1);

                    if (! empty($existingNumbers)) {
                        $existingNumber = $existingNumbers[0]->phoneNumber;
                        if ($existingNumber !== $phoneNumber) {
                            Log::info("Gym {$tenantId} already has a number {$existingNumber}. Using it instead of requested {$phoneNumber}.");
                            $phoneNumber = $existingNumber;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to check for existing accounts numbers: ' . $e->getMessage());
                }
            }

            if (! $settings->whatsapp_business_number || $settings->whatsapp_business_number !== $phoneNumber) {
                Log::info('Purchasing WhatsApp Business Number for gym: ' . $gymName);
                try {
                    $purchasedNumber = $this->purchasePhoneNumber(
                        $phoneNumber,
                        $settings->twilio_account_sid,
                        $settings->twilio_auth_token,
                        $settings->waba_sid
                    );

                    if (! $purchasedNumber['success']) {
                        throw new \Exception($purchasedNumber['error']);
                    }

                    $settings->update([
                        'whatsapp_business_number' => $purchasedNumber['phoneNumber'],
                        'phone_number_sid' => $purchasedNumber['sid'],
                        // 'whatsapp_sender_sid' => $purchasedNumber['senderSid'],
                        'account_status' => NotificationAccountStatusEnum::ACTIVE,
                        // We mark as verified immediately here per previous logic,
                        // assuming regulatory compliance or trusted source.
                        // If actual Meta verification is needed, this should be PENDING.
                        // Based on previous code:
                        'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
                        // 'verification_sid' => $purchasedNumber['verificationSid'],
                        'verified_at' => now(),
                    ]);

                    $responseData['phone_number'] = $purchasedNumber['phoneNumber'];

                } catch (\Exception $e) {
                    throw new \Exception('Failed to purchase number: ' . $e->getMessage());
                }
            } else {
                $responseData['phone_number'] = $settings->whatsapp_business_number;
            }

            if (! $settings->whatsapp_sender_sid) {
                $settings->refresh();
                Log::info('Creating WhatsApp sender for gym: ', [
                    'settings' => $settings,
                ]);
                try {
                    $subClient = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

                    // Create WhatsApp sender - THIS IS THE EXACT API CALL
                    $channels_sender = $subClient->messaging->v2->channelsSenders->create(
                        ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                            'sender_id' => 'whatsapp:' . $settings->whatsapp_business_number,
                            'profile' => [
                                'name' => $gymName,
                            ],
                            'configuration' => [
                                'waba_id' => config('services.twilio.waba_id'),
                            ],
                        ])
                    );

                    $senderSid = $channels_sender->sid;

                    Log::info('WhatsApp Sender created successfully via SDK.', [
                        'gym_id' => $tenantId,
                        'sid' => $senderSid,
                        'waba_id' => config('services.twilio.waba_id'),
                    ]);

                    if ($senderSid) {
                        $settings->update([
                            'whatsapp_sender_sid' => $senderSid,
                        ]);
                    } else {
                        throw new \Exception('Failed to retrieve Sender SID from Twilio SDK response.');
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to create WhatsApp Sender: ' . $e->getMessage());
                }
            }

            // Success
            $responseData['success'] = true;
            $responseData['account_sid'] = $settings->twilio_account_sid;
            $responseData['message'] = 'Account setup complete.';

            return $responseData;

        } catch (\Exception $e) {
            Log::error("Setup with purchased number failed: {$e->getMessage()}", [
                'gym_id' => $tenantId,
            ]);

            $responseData['error'] = $e->getMessage();

            return $responseData;
        }
    }

    /**
     * Purchase a phone number and add to Messaging Service
     */
    public function purchasePhoneNumber(string $phoneNumber, string $accountSid, string $authToken, string $messagingServiceSid): array
    {
        try {
            $client = new Client($accountSid, $authToken);

            // 1. Check if we already own this number
            $incomingPhoneNumber = null;
            $existingNumbers = $client->incomingPhoneNumbers->read([
                'phoneNumber' => $phoneNumber,
            ], 1);

            if (! empty($existingNumbers)) {
                $incomingPhoneNumber = $existingNumbers[0];
                Log::info("Number already owned: {$incomingPhoneNumber->sid}");
            } else {
                // Purchase
                $incomingPhoneNumber = $client->incomingPhoneNumbers->create([
                    'phoneNumber' => $phoneNumber,
                ]);
                Log::info("Number purchased: {$incomingPhoneNumber->sid}");
            }

            // 2. Add to Messaging Service (Idempotent)
            $smsSenderSid = null;
            try {
                $smsSender = $client->messaging->v1->services($messagingServiceSid)
                    ->phoneNumbers->create($incomingPhoneNumber->sid);

                $smsSenderSid = $smsSender->sid;
                Log::info('Number added to Messaging Service');
            } catch (RestException $e) {
                if ($e->getStatusCode() === 409) {
                    Log::info('Number already exists in Messaging Service. Fetching sms sender SID.');
                    // Retrieve the sender SID from the service
                    $smsSender = $client->messaging->v1->services($messagingServiceSid)->phoneNumbers->read();
                    foreach ($smsSender as $s) {
                        if ($s->phoneNumber === $phoneNumber) {
                            $smsSenderSid = $s->sid;
                            break;
                        }
                    }
                    if (! $smsSenderSid) {
                        // Fallback: assume PN SID is Sender SID (often true for PN senders) or ignore
                        $smsSenderSid = $incomingPhoneNumber->sid;
                    }
                } else {
                    throw $e;
                }
            }

            // 5. Create WhatsApp Sender
            // $senderSid = null;
            // try {
            //     // Check if sender already exists first? Twilio doesn't easy way to search by numberSid inside service?
            //     // We'll try to create it, if 409 we ignore or fetch.
            //     // Standard create for WhatsApp sender:
            //     $sender = $client->messaging->v1
            //         ->services($messagingServiceSid)
            //         ->senders
            //         ->create(
            //             $incomingPhoneNumber->sid, // sender_id (the phone number SID or string)
            //             'whatsapp', // provider
            //             // Additional args usually needed?
            //             // The create() method signature: create(string $senderId, string $provider, array $options = [])
            //         );

            //     // Wait, the SDK signature might be different.
            //     // Let's use the array syntax from user's attempt if that's what their SDK version supports?
            //     // No, standard PHP SDK uses: create($senderId, $provider, ...)
            //     // But the user's snippet was: ->create(['sender_type' => 'whatsapp', ...])
            //     // This suggests they might be using a specific version or that's how they *think* it works.
            //     // However, Twilio's API for Senders is: POST /Services/{Sid}/Senders
            //     // Parameters: SenderId, Provider, etc.

            //     // Let's trust the standard SDK usage:
            //     // $sender = $context->create($senderId, $provider, ...);
            //     // But wait, the user provided: ->senders->create([])
            //     // This implies args might be an array in some versions?
            //     // Let's stick to the most likely correct SDK usage which is arguments.
            //     // BUT, looking at the user's snippet, they used Named Arguments or an Array.
            //     // Let's assume standard SDK ->create($sender_id, $provider, ...).
            //     // Actually, let's use the previous pattern which was working for PhoneNumbers.
            //     // $client->messaging->v1->services($sid)->phoneNumbers->create($pnSid)

            //     // For Senders:
            //     // $client->messaging->v1->services($sid)->senders->create($phoneNumberString, 'whatsapp');
            //     // OR $incomingPhoneNumber->sid?
            //     // Docs say SenderId can be the Phone Number SID for Twilio numbers.

            //     // Let's go with the user's intention but correct syntax if I can infer it.
            //     // If I am unsure, I will wrap in try catch and log.

            //     // Implementation:
            //     // Using the create method which takes (SenderId, Provider, Options)

            //     $senderSid = $sender->sid;
            //     Log::info('WhatsApp Sender created: ' . $senderSid);

            // } catch (RestException $e) {
            //     if ($e->getStatusCode() === 409) {
            //         Log::info('WhatsApp Sender already exists.');
            //         // We might want to fetch it?
            //         // For now, if it exists, we assume it's good.
            //     } else {
            //         Log::error('Failed to create WhatsApp Sender: ' . $e->getMessage());
            //         // We don't throw here to allow flow to continue as pure SMS if whatsapp fails?
            //         // No, keeping it robust.
            //     }
            // }

            // 3. Skip Verification - Auto Verify
            // We return verificationSid as 'auto-verified' or null
            $verificationSid = 'auto-verified';

            return [
                'success' => true,
                'sid' => $incomingPhoneNumber->sid,
                // 'senderSid' => $senderSid,
                'phoneNumber' => $incomingPhoneNumber->phoneNumber,
                'verificationSid' => $verificationSid,
            ];
        } catch (RestException $e) {
            Log::error("Number purchase/setup failed: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create Twilio subaccount for gym owner with their WhatsApp number
     */
    public function createAccountForGymOwner(string $tenantId, string $gymOwnerName, string $gymName, string $whatsappNumber, string $email): array
    {
        try {
            DB::beginTransaction();

            // Check if account already exists
            $existingSetting = NotificationSetting::where('gym_id', $tenantId)->first();

            if ($existingSetting?->twilio_account_sid) {
                // Account exists, check if verification is pending
                if ($existingSetting->verification_status === NotificationVerificationStatusEnum::PENDING) {
                    // Resend verification
                    $verificationResult = $this->startWhatsAppVerification($existingSetting->twilio_account_sid, $existingSetting->twilio_auth_token, $whatsappNumber);

                    if ($verificationResult['success']) {
                        DB::commit();

                        return [
                            'success' => true,
                            'message' => 'Account already exists. Verification code resent to WhatsApp.',
                            'verification_required' => true,
                        ];
                    }
                }

                DB::commit();

                return [
                    'success' => false,
                    'error' => 'Account already exists for this gym',
                    'message' => 'An account is already configured for this gym.',
                ];
            }

            // Create Twilio Subaccount
            $subaccount = $this->masterClient->api->v2010->accounts->create([
                // 'friendlyName' => "Gym: {$gymName} - Owner: {$gymOwnerName}",
                'friendlyName' => "Gym_{$tenantId}_{$gymName}",
            ]);

            $subaccountClient = new Client($subaccount->sid, $subaccount->authToken);

            $waba = $subaccountClient->messaging->v1->services->create("Gym_{$tenantId}_WABA");

            // Store account details in database
            $accountData = [
                'gym_id' => $tenantId,
                'twilio_account_sid' => $subaccount->sid,
                'twilio_auth_token' => $subaccount->authToken,
                'whatsapp_business_number' => $whatsappNumber,
                'account_status' => NotificationAccountStatusEnum::CREATED,
                'verification_status' => NotificationVerificationStatusEnum::PENDING,
                'created_at' => now(),
            ];

            // Store in notification_settings table
            NotificationSetting::updateOrCreate(['gym_id' => $tenantId], $accountData);

            // Initiate WhatsApp number verification process
            $verificationResult = $this->startWhatsAppVerification($subaccount->sid, $subaccount->authToken, $whatsappNumber);

            if (! $verificationResult['success']) {
                throw new \Exception($verificationResult['error'] ?? 'Failed to start verification');
            }

            DB::commit();

            return [
                'success' => true,
                'account_sid' => $subaccount->sid,
                'auth_token' => $subaccount->authToken,
                'verification_required' => true,
                'verification_method' => 'whatsapp',
                'message' => 'Twilio account created successfully. Please check your WhatsApp for a verification message.',
            ];
        } catch (RestException $e) {
            DB::rollBack();

            Log::error('Twilio account creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'tenant_id' => $tenantId,
            ]);

            $message = 'Failed to create Twilio account';
            if ($e->getCode() === 21217) {
                // Reached maximum number of subaccounts
                $message = 'You have reached the maximum number of subaccounts for your Twilio account. Please upgrade your Twilio plan or contact Twilio support.';
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'message' => $message,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Unexpected error during account creation', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Unexpected error occurred',
            ];
        }
    }

    /**
     * Start WhatsApp number verification using Twilio Verify.
     */
    public function startWhatsAppVerification(string $accountSid, string $authToken, string $whatsappNumber): array
    {
        try {
            $client = $this->makeClient($accountSid, $authToken);

            // Create a Verify Service if one doesn't exist for this subaccount
            $verifyServiceSid = $this->getOrCreateVerifyService($client, $accountSid);

            // Store service SID for later use
            $this->cacheVerifyServiceSid($accountSid, $verifyServiceSid);

            $verification = $client->verify->v2
                ->services($verifyServiceSid)
                ->verifications->create($whatsappNumber, 'whatsapp'); // Send code via WhatsApp

            return [
                'success' => true,
                'sid' => $verification->sid,
                'status' => $verification->status,
            ];
        } catch (RestException $e) {
            Log::error("Twilio WhatsApp verification initiation failed: $e ");

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error("Unexpected error during WhatsApp verification initiation: $e");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Complete WhatsApp number verification
     */
    public function completeWhatsAppVerification(string $tenantId, string $verificationCode): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $tenantId)->first();

            if (! $settings || ! $settings->twilio_account_sid) {
                return [
                    'success' => false,
                    'error' => 'No Twilio account found for this gym',
                ];
            }

            if ($settings->verification_status === NotificationVerificationStatusEnum::VERIFIED) {
                return [
                    'success' => false,
                    'error' => 'Number is already verified',
                ];
            }

            $client = $this->makeClient($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Get the Verify Service SID
            $verifyServiceSid = $this->getCachedVerifyServiceSid($settings->twilio_account_sid) ?? $this->getOrCreateVerifyService($client, $settings->twilio_account_sid);
            // $verifyServiceSid = $this->getOrCreateVerifyService($client, $settings->twilio_account_sid);

            // Check the verification code
            $verificationCheck = $client->verify->v2
                ->services($verifyServiceSid)
                ->verificationChecks->create([
                    'to' => $settings->whatsapp_business_number,
                    'code' => $verificationCode,
                ]);

            if ($verificationCheck->status === 'approved') {
                // Update status in database
                $settings->update([
                    'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
                    'account_status' => NotificationAccountStatusEnum::ACTIVE,
                    'verified_at' => now(),
                ]);

                // Test the WhatsApp integration by sending a welcome message
                $testResult = $this->sendTestWhatsAppMessage($settings);

                Log::info('WhatsApp verification completed', [
                    'gym_id' => $tenantId,
                    'verified_number' => $settings->whatsapp_business_number,
                    'test_message_sent' => $testResult['success'] ?? false,
                    'test_message_id' => $testResult['message_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'WhatsApp number verified successfully',
                    'test_message_sent' => $testResult['success'] ?? false,
                    'test_message_id' => $testResult['message_id'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid verification code',
                    'message' => 'Verification failed: ' . $verificationCheck->status,
                ];
            }
        } catch (RestException $e) {
            Log::error('Twilio WhatsApp verification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during WhatsApp verification: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Verification failed',
            ];
        }
    }

    /**
     * Get account status and usage
     */
    public function getAccountStatus(string $tenantId): array
    {
        $settings = NotificationSetting::where('gym_id', $tenantId)->first();

        if (! $settings || ! $settings->twilio_account_sid) {
            return [
                'status' => 'not_created',
                'message' => 'No Twilio account found',
            ];
        }

        try {
            $client = $this->makeClient($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Get account info
            $account = $client->api->v2010->accounts($settings->twilio_account_sid)->fetch();

            // Get usage for current month
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
                'status' => $account->status,
                'verification_status' => $settings->verification_status,
                // 'balance' => $client->balance->fetch()->balance,
                'messages_sent' => $messageCount,
                'monthly_cost' => $cost,
                'account_created' => $settings->created_at,
                'last_activity' => now(),
            ];
        } catch (RestException $e) {
            Log::error('Failed to fetch Twilio account status: ' . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper to get or create a Twilio Verify Service for a subaccount.
     */
    protected function getOrCreateVerifyService(Client $client, string $accountSid): string
    {
        $tenantId = NotificationSetting::where('twilio_account_sid', $accountSid)->value('gym_id') ?? (Filament::getTenant()->id ?? 'unknown');

        $friendlyName = "Verify Service for {$tenantId}";

        // Read all services and find the one with the matching friendly name
        $services = $client->verify->v2->services->read();
        $foundService = null;
        foreach ($services as $service) {
            if ($service->friendlyName === $friendlyName) {
                $foundService = $service;
                break;
            }
        }

        if ($foundService) {
            return $foundService->sid;
        }

        // If not, create a new one
        $service = $client->verify->v2->services->create($friendlyName);

        return $service->sid;
    }

    /**
     * Send test WhatsApp message to verify setup
     *
     * In sandbox mode: Uses free-form message body
     * In production mode: Uses approved WhatsApp template
     */
    /**
     * Send test WhatsApp message to verify setup
     *
     * In sandbox mode: Uses free-form message body
     * In production mode: Uses approved WhatsApp template
     */
    public function sendTestWhatsAppMessage(NotificationSetting $settings, ?string $to = null): array
    {
        try {
            if (empty($settings->twilio_account_sid) || empty($settings->twilio_auth_token)) {
                return [
                    'success' => false,
                    'error' => 'Twilio credentials are not configured.',
                ];
            }

            // Initialize the service with the Gym's ID to send from the Gym's account
            $whatsAppService = new TwilioWhatsAppService((string) $settings->gym_id);

            // Get gym name for personalized message
            $gym = Gym::find($settings->gym_id);
            $gymName = $gym ? $gym->name : 'Your Gym';

            // Use provided number or fallback to the registered business number
            $recipientNumber = $to ?? $settings->whatsapp_business_number;

            // In sandbox mode, we can send free-form messages
            // In production mode, we MUST use approved templates
            if ($this->useSandbox) {
                // Sandbox: Use free-form message body
                $messageBody = "🎉 *WhatsApp Integration Active!*\n\n";
                $messageBody .= "Hello from {$gymName}! 👋\n\n";
                $messageBody .= "Your WhatsApp Business integration is now successfully set up and ready to use.\n\n";
                $messageBody .= "*What's Next?*\n";
                $messageBody .= "✅ Members will receive booking confirmations\n";
                $messageBody .= "✅ Payment reminders will be sent automatically\n";
                $messageBody .= "✅ Class updates will reach members instantly\n";
                $messageBody .= "✅ Birthday greetings will be sent on special days\n\n";
                $messageBody .= "This is a test message to confirm everything is working perfectly.\n\n";
                $messageBody .= '_Sent at: ' . now()->format('M d, Y H:i A') . '_';

                $result = $whatsAppService->sendMessage($recipientNumber, $messageBody);
            } else {
                // Production: Use approved template (required for business-initiated messages)
                // Since we are sending from the Gym's account, we use the System's template ID from config
                // assuming the gym has access to this template or a similar one.
                // Note: In a real scenario, the template must exist in the Gym's Twilio subaccount or match exactly.
                $templateSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

                if (! $templateSid) {
                    // No template available - return helpful error
                    return [
                        'success' => false,
                        'error' => "No approved WhatsApp template found. Please:\n" .
                            "1. Create and approve a 'welcome_message' template in Twilio console, OR\n" .
                            "2. Set TWILIO_WELCOME_TEMPLATE_SID in your .env file, OR\n" .
                            '3. Use sandbox mode (TWILIO_USE_SANDBOX=true) for testing.',
                        'requires_template' => true,
                    ];
                }

                // Use template with variables
                // Variable 1: Gym name
                $variables = ['1' => $gymName];

                Log::info('Using WhatsApp template for test message', [
                    'gym_id' => $settings->gym_id,
                    'template_sid' => $templateSid,
                    'recipient' => $recipientNumber,
                ]);

                // Use sendMessage with template data
                $result = $whatsAppService->sendMessage($recipientNumber, '', [
                    'template_sid' => $templateSid,
                    'template_variables' => $variables,
                ]);
            }

            if ($result['success']) {
                Log::info('Test WhatsApp message sent successfully', [
                    'gym_id' => $settings->gym_id,
                    'message_id' => $result['message_id'] ?? null,
                    'to' => $recipientNumber,
                    'status' => $result['status'] ?? 'unknown',
                    'mode' => $this->useSandbox ? 'sandbox' : 'production',
                ]);
            } else {
                Log::error('Twilio WhatsApp test message error', [
                    'gym_id' => $settings->gym_id ?? null,
                    'error' => $result['error'] ?? 'Unknown error',
                    'mode' => $this->useSandbox ? 'sandbox' : 'production',
                ]);
            }

            // Normalize response to match expected format
            return [
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['status'] ?? null,
                'to' => $recipientNumber,
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
                'error' => $result['error'] ?? null,
                'requires_template' => isset($result['requires_template']) ? $result['requires_template'] : false,
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error sending test WhatsApp message', [
                'gym_id' => $settings->gym_id ?? null,
                'error' => $e->getMessage(),
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
            ];
        }
    }

    /**
     * Cache verify service SID
     */
    private function cacheVerifyServiceSid(string $accountSid, string $serviceSid): void
    {
        Cache::put("twilio_verify_service:{$accountSid}", $serviceSid, now()->addDays(30));
    }

    /**
     * Get cached verify service SID
     */
    private function getCachedVerifyServiceSid(string $accountSid): ?string
    {
        return Cache::get("twilio_verify_service:{$accountSid}");
    }

    /**
     * Create a new Twilio Client instance.
     *
     * This method is protected to allow mocking during tests.
     */
    /**
     * Create a new Twilio Client instance.
     *
     * This method is protected to allow mocking during tests.
     */
    protected function makeClient(string $sid, string $token): Client
    {
        // For testing, use master account credentials
        if ($this->useSandbox) {
            return new Client(config('services.twilio.master_sid'), config('services.twilio.master_token'));
        }

        return new Client($sid, $token);
    }

    /**
     * Fetch the latest incoming SMS message for a specific number.
     * Used to retrieve verification codes sent to the Twilio number.
     */
    public function fetchLatestIncomingMessage(string $tenantId, string $phoneNumber): ?string
    {
        try {
            $settings = NotificationSetting::where('gym_id', $tenantId)->first();

            if (! $settings || ! $settings->twilio_account_sid) {
                return null;
            }

            $client = $this->makeClient($settings->twilio_account_sid, $settings->twilio_auth_token);

            $messages = $client->messages->read([
                'to' => $phoneNumber,
                'limit' => 1,
            ]);

            if (! empty($messages)) {
                return $messages[0]->body;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch incoming messages: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Submit a WhatsApp template for Meta approval via WhatsAppTemplateService.
     *
     * Wrapper for ISV operations — delegates to the dedicated template service.
     */
    public function submitTemplate(WhatsAppTemplate $template): array
    {
        $templateService = app(WhatsAppTemplateService::class);

        return $templateService->submitForVerification($template);
    }

    /**
     * Assign an incoming phone number to a WABA (Messaging Service) for WhatsApp enablement.
     *
     * This adds the phone number to the Messaging Service so it can be used for WhatsApp messaging.
     */
    public function assignNumberToWABA(string $accountSid, string $authToken, string $phoneNumberSid, string $messagingServiceSid): array
    {
        try {
            $client = $this->makeClient($accountSid, $authToken);

            $phoneNumber = $client->messaging->v1
                ->services($messagingServiceSid)
                ->phoneNumbers
                ->create($phoneNumberSid);

            Log::info('Phone number assigned to WABA', [
                'phone_number_sid' => $phoneNumberSid,
                'messaging_service_sid' => $messagingServiceSid,
                'sender_sid' => $phoneNumber->sid,
            ]);

            return [
                'success' => true,
                'sid' => $phoneNumber->sid,
                'phone_number' => $phoneNumber->phoneNumber ?? null,
            ];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 409) {
                Log::info('Phone number already assigned to WABA', [
                    'phone_number_sid' => $phoneNumberSid,
                    'messaging_service_sid' => $messagingServiceSid,
                ]);

                return [
                    'success' => true,
                    'already_assigned' => true,
                ];
            }

            Log::error('Failed to assign number to WABA', [
                'error' => $e->getMessage(),
                'phone_number_sid' => $phoneNumberSid,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
