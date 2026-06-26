<?php

namespace App\Services\Twilio;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Interfaces\AccountManagementInterface;
use App\Models\WhatsAppAccount;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;

/**
 * Twilio Account Service.
 *
 * Implements AccountManagementInterface for subaccount creation,
 * WABA setup, and account cleanup via Twilio API.
 */
class TwilioAccountService implements AccountManagementInterface
{
    protected bool $useIndividualWaba;

    protected string $sharedWabaId;

    public function __construct(
        protected TwilioContext $context
        // protected int $gymId,
        // protected TwilioClientFactory $clientFactory,
    ) {
        // $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);
        // $this->sharedWabaId = config('services.twilio.shared_waba_id', '');
    }

    /**
     * Create a subaccount for a gym.
     */
    public function createSubAccount(int $gymId, string $gymName, string $email): array
    {
        try {
            $gymName = strip_tags($gymName);
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;

            return Cache::lock('twilio:subaccount:setup:' . $gymId, 120)->block(30, function () use ($gymId, $gymName, $email) {
                // Load account for this gym
                $account = $this->context->getAccount();

                if ($account?->twilio_account_sid) {
                    return [
                        'success' => true,
                        'account_sid' => $account->twilio_account_sid,
                        'message' => 'Account already exists',
                    ];
                }

                $subaccount = $this->context->getMasterClient()
                    ->api->v2010->accounts->create([
                        'friendlyName' => substr(preg_replace(pattern: "/([^A-Za-z0-9\-_]/", replacement: '', subject: "{$gymId}-{$gymName}"), 0, 100),
                    ]);

                // Create a Messaging Service on the subaccount for WhatsApp Channel Sender registration
                $subClient = $this->context->getClientForAccount(
                    new WhatsAppAccount([
                        'twilio_account_sid' => $subaccount->sid,
                        'twilio_auth_token' => $subaccount->authToken,
                    ])
                );

                $messagingService = $subClient->messaging->v1->services->create("Gym_{$gymId}_SERVICE");

                WhatsAppAccount::query()->updateOrCreate([
                    'gym_id' => $gymId,
                ], [
                    'twilio_account_sid' => $subaccount->sid,
                    'twilio_auth_token' => $subaccount->authToken,
                    'messaging_service_sid' => $messagingService->sid,
                    'status' => WhatsAppAccountStatusEnum::PENDING,
                    'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
                    'business_name' => $gymName,
                    'business_email' => $email,
                ]);

                return [
                    'success' => true,
                    'account_sid' => $subaccount->sid,
                    'message' => 'Subaccount created successfully',
                ];
            });

        } catch (LockTimeoutException $e) {
            Log::warning('Twilio setup lock timeout', ['gym_id' => $gymId]);

            return [
                'success' => false,
                'message' => 'Account setup is currently in progress. Please wait a moment and try again.',
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

    /**
     * Get account status.
     */
    public function getAccountStatus(int $gymId): array
    {
        $account = $this->context->getAccount();

        if (! $account || ! $account->twilio_account_sid) {
            return [
                'status' => 'not_created',
                'message' => 'No WhatsApp account found',
            ];
        }

        try {
            $cacheKey = "twilio:account_status:{$gymId}";

            return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addHours(15)], function () use ($account) {
                $client = $this->context->getClientForAccount($account);

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
            });
        } catch (RestException $e) {
            Log::error('Failed to fetch account status', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if embedded signup is required.
     */
    public function requiresEmbeddedSignup(int $gymId): bool
    {
        $account = $this->context->getAccount();

        return $account && $account->isWabaProvider() && ! $account->isEmbeddedSignupComplete();
    }

    /**
     * Cleanup account resources.
     */
    public function cleanupAccount(int $gymId): array
    {
        $lockKey = "twilio:cleanup:{$gymId}";

        try {
            return Cache::lock($lockKey, 60)->block(10, function () use ($gymId) {
                $account = $this->context->getAccount();

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

                // Delegate to specialized cleanup methods
                $phoneResult = $this->cleanupPhoneNumbers($account);
                $cleaned['phone_numbers'] = $phoneResult;
                if (! $phoneResult['success']) {
                    $errors[] = $phoneResult['error'];
                }

                $serviceResult = $this->cleanupMessagingService($account);
                $cleaned['messaging_service'] = $serviceResult;
                if (! $serviceResult['success']) {
                    $errors[] = $serviceResult['error'];
                }

                $accountResult = $this->closeSubaccount($account);
                $cleaned['subaccount'] = $accountResult;
                if (! $accountResult['success']) {
                    $errors[] = $accountResult['error'];
                }

                $account->update(['status' => WhatsAppAccountStatusEnum::SUSPENDED]);

                // Clear the status cache so next read gets fresh data
                Cache::forget("twilio:account_status:{$gymId}");

                return [
                    'success' => blank($errors),
                    'message' => blank($errors) ? 'All Twilio resources cleaned up' : 'Cleanup completed with errors',
                    'cleaned' => $cleaned,
                    'errors' => $errors,
                ];
            });
        } catch (LockTimeoutException $e) {
            return [
                'success' => false,
                'message' => 'Account cleanup is currently in progress. Please wait.',
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during Twilio cleanup', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred during cleanup.',
            ];
        }
    }

    /**
     * Release phone numbers.
     */
    protected function cleanupPhoneNumbers(WhatsAppAccount $account): array
    {
        $phoneNumbers = $account->phoneNumber()->get();

        if ($phoneNumbers->isEmpty()) {
            return ['success' => true, 'message' => 'No phone numbers to release', 'released' => 0];
        }

        $released = 0;
        $failed = 0;
        $lastError = null;

        try {
            $subClient = $this->context->getClientForAccount($account);

            foreach ($phoneNumbers as $phoneNumber) {
                try {
                    $channelSenderSid = $phoneNumber->getChannelSenderSid();
                    if ($account->messaging_service_sid && $channelSenderSid) {
                        try {
                            $subClient->messaging->v1->services($account->messaging_service_sid)
                                ->channelSenders($channelSenderSid)
                                ->delete();

                            Log::info('Channel sender removed', [
                                'phone_number' => $phoneNumber->phone_number,
                            ]);
                        } catch (RestException $e) {
                            throw_if($e->getStatusCode() !== 404, $e);
                        }
                    }

                    $incomingPhoneSid = $phoneNumber->getIncomingPhoneNumberSid();
                    if ($incomingPhoneSid && $phoneNumber->isValidIncomingPhoneNumberSid()) {
                        try {
                            $subClient->incomingPhoneNumbers($incomingPhoneSid)->delete();
                        } catch (RestException $e) {
                            throw_if($e->getStatusCode() !== 404, $e);
                        }
                    }

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
     * Delete messaging service.
     */
    protected function cleanupMessagingService(WhatsAppAccount $account): array
    {
        if (! $account->messaging_service_sid) {
            return ['success' => true, 'message' => 'No messaging service to delete'];
        }

        try {
            $this->context->getClientForAccount($account)
                ->messaging->v1->services($account->messaging_service_sid)
                ->delete();

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
     * Close subaccount.
     */
    protected function closeSubaccount(WhatsAppAccount $account): array
    {
        if (! $account->twilio_account_sid) {
            return ['success' => true, 'message' => 'No subaccount to close'];
        }

        try {
            $this->context->getMasterClient()
                ->api->v2010->accounts($account->twilio_account_sid)
                ->update(['status' => 'closed']);

            return ['success' => true, 'message' => 'Subaccount closed'];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 404) {
                return ['success' => true, 'message' => 'Subaccount already closed or not found'];
            }

            Log::error('Failed to close Twilio subaccount', [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get account or fail.
     *
     * @throws \Exception
     */
    public function getAccountOrFail(int $gymId): WhatsAppAccount
    {
        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();

        throw_unless($account, \Exception::class, 'WhatsApp account not found for this gym');

        return $account;
    }

    // /**
    //  * Create a WABA.
    //  */
    // public function createWaba(int $gymId, array $businessProfile): array
    // {
    //     try {
    //         $account = $this->getAccountOrFail($gymId);

    //         if ($account->waba_id && $account->messaging_service_sid) {
    //             return [
    //                 'success' => true,
    //                 'waba_id' => $account->waba_id,
    //                 'message' => 'WABA already exists',
    //             ];
    //         }

    //         $subClient = $this->clientFactory->makeClientForGym($gymId);

    //         if ($this->useIndividualWaba) {
    //             return $this->createIndividualWaba($account, $gymId, $subClient, $businessProfile);
    //         }

    //         return $this->createSharedWaba($account, $gymId, $subClient, $businessProfile);
    //     } catch (\Exception $e) {
    //         Log::error('Failed to create WABA', [
    //             'gym_id' => $gymId,
    //             'error' => $e->getMessage(),
    //         ]);

    //         return [
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //             'message' => 'Failed to create WABA',
    //         ];
    //     }
    // }

    // /**
    //  * Create shared WABA.
    //  */
    // private function createSharedWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    // {
    //     if (! $account->waba_id) {
    //         $account->update([
    //             'waba_id' => $this->sharedWabaId,
    //         ]);
    //     }

    //     if (! $account->messaging_service_sid) {
    //         $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
    //         $account->update(['messaging_service_sid' => $service->sid]);
    //     }

    //     if (! empty($businessProfile['business_name'])) {
    //         $account->update([
    //             'business_name' => $businessProfile['business_name'],
    //             'business_email' => $businessProfile['business_email'] ?? $account->business_email,
    //             'business_description' => $businessProfile['business_description'] ?? '',
    //         ]);
    //     }

    //     Log::info('Shared WABA setup completed', [
    //         'gym_id' => $gymId,
    //         'waba_id' => $account->waba_id,
    //         'messaging_service_sid' => $account->messaging_service_sid,
    //     ]);

    //     return [
    //         'success' => true,
    //         'waba_id' => $account->waba_id,
    //         'messaging_service_sid' => $account->messaging_service_sid,
    //         'message' => 'Shared WABA created successfully',
    //     ];
    // }

    // /**
    //  * Create individual WABA.
    //  */
    // private function createIndividualWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    // {
    //     $account->update([
    //         'status' => WhatsAppAccountStatusEnum::PENDING,
    //     ]);

    //     if (! $account->messaging_service_sid) {
    //         $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
    //         $account->update(['messaging_service_sid' => $service->sid]);
    //     }

    //     if (! empty($businessProfile['business_name'])) {
    //         $account->update([
    //             'business_name' => $businessProfile['business_name'],
    //             'business_email' => $businessProfile['business_email'] ?? $account->business_email,
    //             'business_description' => $businessProfile['business_description'] ?? '',
    //         ]);
    //     }

    //     Log::info('Individual WABA setup initiated', [
    //         'gym_id' => $gymId,
    //         'messaging_service_sid' => $account->messaging_service_sid,
    //         'requires_embedded_signup' => true,
    //     ]);

    //     return [
    //         'success' => true,
    //         'messaging_service_sid' => $account->messaging_service_sid,
    //         'requires_embedded_signup' => true,
    //         'message' => 'Please complete Embedded Signup to connect your WABA',
    //     ];
    // }
}
