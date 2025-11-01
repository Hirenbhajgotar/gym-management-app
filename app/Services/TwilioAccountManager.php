<?php

namespace App\Services;

use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use App\Models\NotificationSetting;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

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
        $this->masterClient = new Client($this->masterAccountSid, $this->masterAuthToken);
    }

    /**
     * Create Twilio subaccount for gym owner with their WhatsApp number
     */
    public function createAccountForGymOwner(
        string $tenantId,
        string $gymOwnerName,
        string $gymName,
        string $whatsappNumber,
        string $email
    ): array {
        try {
            DB::beginTransaction();

            // Check if account already exists
            $existingSetting = NotificationSetting::where('gym_id', $tenantId)->first();

            if ($existingSetting && $existingSetting->twilio_account_sid) {
                // Account exists, check if verification is pending
                if ($existingSetting->verification_status === NotificationVerificationStatusEnum::PENDING) {
                    // Resend verification
                    $verificationResult = $this->startWhatsAppVerification(
                        $existingSetting->twilio_account_sid,
                        $existingSetting->twilio_auth_token,
                        $whatsappNumber
                    );

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
                'friendlyName' => "Gym: {$gymName} - Owner: {$gymOwnerName}",
            ]);

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
            NotificationSetting::updateOrCreate(
                ['gym_id' => $tenantId],
                $accountData
            );

            // Initiate WhatsApp number verification process
            $verificationResult = $this->startWhatsAppVerification(
                $subaccount->sid,
                $subaccount->authToken,
                $whatsappNumber
            );

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
            if ($e->getCode() === 21217) { // Reached maximum number of subaccounts
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

            $verification = $client->verify->v2->services($verifyServiceSid)
                ->verifications
                ->create($whatsappNumber, 'whatsapp'); // Send code via WhatsApp

            return [
                'success' => true,
                'sid' => $verification->sid,
                'status' => $verification->status,
            ];
        } catch (RestException $e) {
            Log::error('Twilio WhatsApp verification initiation failed: ' . $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during WhatsApp verification initiation: ' . $e);

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
                // throw new \Exception('No Twilio account found for this gym');
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
            $verifyServiceSid = $this->getCachedVerifyServiceSid($settings->twilio_account_sid)
                ?? $this->getOrCreateVerifyService($client, $settings->twilio_account_sid);
            // $verifyServiceSid = $this->getOrCreateVerifyService($client, $settings->twilio_account_sid);

            // Check the verification code
            $verificationCheck = $client->verify->v2->services($verifyServiceSid)
                ->verificationChecks
                ->create([
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

                // Test the WhatsApp integration
                $testResult = $this->sendTestWhatsAppMessage($settings);

                return [
                    'success' => true,
                    'message' => 'WhatsApp number verified successfully',
                    'test_message_sent' => $testResult['success'] ?? false,
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
            Log::error('Failed to fetch Twilio account status: ' . $e);

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
        $tenantId = NotificationSetting::where('twilio_account_sid', $accountSid)->value('gym_id') ?? Filament::getTenant()?->id ?? 'unknown';

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
     */
    public function sendTestWhatsAppMessage(NotificationSetting $settings): array
    {
        try {
            if (empty($settings->twilio_account_sid) || empty($settings->twilio_auth_token)) {
                return [
                    'success' => false,
                    'error' => 'Twilio credentials are not configured.',
                ];
            }

            $client = $this->makeClient($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Get from number based on sandbox or production
            $fromNumber = $this->useSandbox
                ? config('services.twilio.sandbox_number') // e.g., '+14155238886'
                : app(TwilioWhatsAppService::class)->getProductionFromNumber($settings);

            // Send test message to gym owner
            $message = $client->messages->create(
                'whatsapp:' . $settings->whatsapp_business_number,
                [
                    'from' => 'whatsapp:' . $fromNumber,
                    'body' => 'Your WhatsApp Business integration is now active! You can start sending notifications to your members.',
                ]
            );

            return [
                'success' => true,
                'message_id' => $message->sid,
            ];
        } catch (RestException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
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
    protected function makeClient(string $sid, string $token): Client
    {
        // For testing, use master account credentials
        if ($this->useSandbox) {
            return new Client(
                config('services.twilio.master_sid'),
                config('services.twilio.master_token')
            );
        }

        return new Client($sid, $token);
    }
}
