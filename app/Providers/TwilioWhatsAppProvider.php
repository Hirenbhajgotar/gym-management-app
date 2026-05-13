<?php

namespace App\Providers;

use App\Interfaces\AccountManagementInterface;
use App\Interfaces\CreditManagementInterface;
use App\Interfaces\MessagingInterface;
use App\Interfaces\PhoneNumberManagementInterface;
use App\Interfaces\TemplateManagementInterface;
use App\Interfaces\TestMessagingInterface;
use App\Models\Gym;
use App\Services\Twilio\TwilioAccountService;
use App\Services\Twilio\TwilioContext;
use App\Services\Twilio\TwilioMessagingService;
use App\Services\Twilio\TwilioPhoneService;
use App\Services\Twilio\TwilioTemplateService;
use App\Services\WhatsApp\WhatsAppCreditService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Support\Facades\Log;

/**
 * Twilio WhatsApp Provider - Orchestrator.
 *
 * @implements AccountManagementInterface,
 *      CreditManagementInterface,
 *      MessagingInterface,
 *      PhoneNumberManagementInterface,
 *      TemplateManagementInterface,
 *      TestMessagingInterface
 */
class TwilioWhatsAppProvider implements AccountManagementInterface, CreditManagementInterface, MessagingInterface, PhoneNumberManagementInterface, TemplateManagementInterface, TestMessagingInterface
{
    private TwilioContext $context;

    private WhatsAppCreditService $credits;

    // Lazy-loaded services
    private ?TwilioMessagingService $messaging = null;

    private ?TwilioAccountService $account = null;

    private ?TwilioPhoneService $phoneNumbers = null;

    private ?TwilioTemplateService $templates = null;

    public function __construct(int $gymId)
    {
        $this->context = new TwilioContext($gymId);
        $this->credits = new WhatsAppCreditService($gymId);
    }

    // =========================
    // Service Accessors
    // =========================

    protected function messaging(): TwilioMessagingService
    {
        return $this->messaging ??= new TwilioMessagingService($this->context, $this->credits());
    }

    protected function account(): TwilioAccountService
    {
        return $this->account ??= new TwilioAccountService($this->context);
    }

    protected function credits(): WhatsAppCreditService
    {
        return $this->credits;
    }

    protected function phoneNumbers(): TwilioPhoneService
    {
        return $this->phoneNumbers ??= new TwilioPhoneService($this->context);
    }

    protected function templates(): TwilioTemplateService
    {
        return $this->templates ??= new TwilioTemplateService(
            $this->context,
            app(WhatsAppTemplateService::class)
        );
    }

    // =========================
    // MessagingInterface - Delegation
    // =========================

    public function sendMessage(string $to, string $message): array
    {
        return $this->messaging()->sendMessage($to, $message);
    }

    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        return $this->messaging()->sendTemplateMessage($to, $templateName, $variables);
    }

    public function sendTestMessage(string $to, string $message): array
    {
        return $this->messaging()->sendTestMessage($to, $message);
    }

    public function sendBroadcast(int $broadcastId): array
    {
        return $this->messaging()->sendBroadcast($broadcastId);
    }

    public function getMessageStatus(string $messageId): array
    {
        return $this->messaging()->getMessageStatus($messageId);
    }

    // =========================
    // CreditManagementInterface - Delegation
    // =========================

    public function getCreditBalance(): array
    {
        return $this->credits()->getCreditBalance();
    }

    public function getMonthlyUsage(int $year, int $month): array
    {
        return $this->credits()->getMonthlyUsage($year, $month);
    }

    // =========================
    // AccountManagementInterface - Delegation
    // =========================

    public function createSubAccount(int $gymId, string $gymName, string $email): array
    {
        return $this->account()->createSubAccount($gymId, $gymName, $email);
    }

    public function getAccountStatus(int $gymId): array
    {
        return $this->account()->getAccountStatus($gymId);
    }

    public function cleanupAccount(int $gymId): array
    {
        return $this->account()->cleanupAccount($gymId);
    }

    public function requiresEmbeddedSignup(int $gymId): bool
    {
        return $this->account()->requiresEmbeddedSignup($gymId);
    }

    // =========================
    // PhoneNumberManagementInterface - Delegation
    // =========================

    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        return $this->phoneNumbers()->getAvailableNumbers($country, $type, $limit);
    }

    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        return $this->phoneNumbers()->purchaseNumber($gymId, $phoneNumber);
    }

    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        return $this->phoneNumbers()->registerOwnNumber($gymId, $phoneNumber);
    }

    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        return $this->phoneNumbers()->verifyOwnNumber($gymId, $senderSid, $otpCode);
    }

    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        return $this->phoneNumbers()->resendOwnNumberOtp($gymId, $senderSid, $channel);
    }

    // =========================
    // TemplateManagementInterface - Delegation
    // =========================

    public function createTemplate(int $gymId, array $data): \App\Models\WhatsAppTemplate
    {
        return $this->templates()->createTemplate($gymId, $data);
    }

    public function submitTemplate(\App\Models\WhatsAppTemplate $template): array
    {
        return $this->templates()->submitTemplate($template);
    }

    public function syncTemplateStatus(\App\Models\WhatsAppTemplate $template): void
    {
        $this->templates()->syncTemplateStatus($template);
    }

    public function getTemplates(int $gymId): array
    {
        return $this->templates()->getTemplates($gymId);
    }

    // =========================
    // TestMessagingInterface - Implementation
    // =========================

    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array
    {
        try {
            $account = $this->context->getAccount();

            if (! $account || empty($account->twilio_account_sid) || empty($account->twilio_auth_token)) {
                return ['success' => false, 'error' => 'WhatsApp account not found or credentials not configured.'];
            }

            $gym = Gym::find($gymId);
            $gymName = $gym->name ?? 'Your Gym';
            $recipientNumber = $to ?? $account?->phoneNumber?->phone_number;

            if (! $recipientNumber) {
                return ['success' => false, 'error' => 'No phone number configured.'];
            }

            $result = $this->context->useSandbox
                ? $this->sendSandboxTestMessage($recipientNumber, $gymName)
                : $this->sendProductionTestMessage($recipientNumber, $gymName);

            return array_merge($result, ['to' => $recipientNumber, 'mode' => $this->context->useSandbox ? 'sandbox' : 'production']);
        } catch (\Exception $e) {
            Log::error('Failed to send test WhatsApp message', ['gym_id' => $gymId, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendSandboxTestMessage(string $to, string $gymName): array
    {
        $messageBody = "🎉 *WhatsApp Integration Active!*\n\nHello from {$gymName}! 👋\n\n_Sent at: " . now()->format('M d, Y H:i A') . '_';

        return $this->messaging()->sendMessage($to, $messageBody);
    }

    private function sendProductionTestMessage(string $to, string $gymName): array
    {
        $templateSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

        if (! $templateSid) {
            return ['success' => false, 'error' => 'No approved WhatsApp template found.', 'requires_template' => true];
        }

        return $this->messaging()->sendTemplateMessage($to, '', ['1' => $gymName]);
    }
}
