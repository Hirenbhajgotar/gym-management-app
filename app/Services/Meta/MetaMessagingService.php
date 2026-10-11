<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Interfaces\MessagingInterface;
use App\Models\Gym;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateLookup;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use App\Services\WhatsApp\WhatsAppCreditService;
use App\Services\WhatsApp\WhatsAppSendCharge;
use Illuminate\Database\Eloquent\Builder;

/**
 * Meta Messaging Service.
 *
 * Implements MessagingInterface for sending WhatsApp messages
 * via Meta Graph API.
 */
class MetaMessagingService implements MessagingInterface
{
    private ?WhatsAppBroadcastService $broadcastService = null;

    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api,
        protected WhatsAppCreditService $credits
    ) {}

    private function getBroadcastService(): WhatsAppBroadcastService
    {
        return $this->broadcastService ??= new WhatsAppBroadcastService($this->context->gymId);
    }

    /**
     * Send a WhatsApp message.
     *
     * @return array{success: bool, message_id?: ?string, status?: ?string, error?: ?string, data?: mixed}
     */
    public function sendMessage(string $to, string $message): array
    {
        if (! $this->context->hasAccount()) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        if (! $this->context->hasAccessToken()) {
            return [
                'success' => false,
                'error' => 'Meta access token not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $phoneNumberId = $this->context->getPhoneNumberId();

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $response = WhatsAppSendCharge::attempt(
            $this->credits,
            fn (): array => $this->api->post("/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ])
        );

        if ($response['success']) {
            $rawMessageId = isset($response['data']['messages'][0]['id']) ? $response['data']['messages'][0]['id'] : null;
            $messageId = is_string($rawMessageId) ? $rawMessageId : null;

            $this->credits->logUsage($to, null, $messageId);

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent',
                'error' => null,
            ];
        }

        $error = $response['error'] ?? null;

        return [
            'success' => false,
            'error' => is_string($error) ? $error : 'Failed to send message',
            'status' => 'failed',
            'message_id' => null,
        ];
    }

    /**
     * Resolves gym-specific templates only, and only rows that are approved,
     * not retired and not deactivated.
     *
     * The scope comes from TemplateLookup rather than being restated here: the
     * tenant global scope silently killed an `orWhereNull('gym_id')` branch
     * inside an HTTP request while leaving it live in a queue worker, so this
     * lookup used to return different rows depending on where it ran.
     *
     * Meta has no sandbox mode, and it never had a system-default branch: a Meta
     * gym that has not configured a template gets "not found" and sends nothing,
     * which is the safe default when the alternative is dispatching the
     * platform's copy under the gym's own sender identity.
     *
     * The predicate matches only the two identifiers Meta can be addressed by.
     * It used to also match twilio_template_sid, which is the other provider's
     * column: the only caller that could reach it passed a SID *because* the Meta
     * id was empty, and the blank-meta_template_id guard below then refused the
     * row anyway. Keeping it could only ever resolve a row this service cannot
     * dispatch, so it is gone rather than commented.
     */
    private function findTemplate(string $templateIdOrName): ?WhatsAppTemplate
    {
        return TemplateLookup::applyScope(
            WhatsAppTemplate::query(),
            $this->context->gymId,
        )
            ->where(function (Builder $query) use ($templateIdOrName): void {
                $query->where('meta_template_id', $templateIdOrName)
                    ->orWhere('name', $templateIdOrName);
            })
            ->first();
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(string $to, string $templateIdOrName, array $variables = []): array
    {
        $phoneNumberId = $this->context->getPhoneNumberId();

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        $template = $this->findTemplate($templateIdOrName);

        if (! $template) {
            return [
                'success' => false,
                'error' => "Template '{$templateIdOrName}' not found or not approved",
            ];
        }

        $languageCode = MetaTemplateService::resolveLanguage($template);

        // Meta addresses the template by its provider-side name, which is
        // meta_template_id — not the internal enum value in the name column. A row
        // without one would deduct a credit and then post a name Meta has never
        // seen, so it has to fail before the deduction like Twilio's SID guard.
        if (blank($template->meta_template_id)) {
            return [
                'success' => false,
                'error' => "Template '{$template->name}' has no Meta template id and cannot be dispatched.",
            ];
        }

        $templatePayload = [
            'name' => $template->meta_template_id,
        ];

        if (filled($variables)) {
            // Meta substitutes these positionally against the template's
            // {{1}}, {{2}}, ... placeholders, so the order the values arrive in is
            // part of the contract. WhatsAppVariableResolver keys them by the
            // stored placeholder key, and nothing enforces that those keys are
            // stored in ascending numeric order on a JSON column. Sorting here
            // makes the payload depend on the placeholder numbers rather than on
            // how the row happened to be written.
            ksort($variables, SORT_NUMERIC);

            $templatePayload['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_values(array_map(
                        fn (string $value): array => [
                            'type' => 'text',
                            'text' => $value,
                        ],
                        $variables
                    )),
                ],
            ];
        }
        $templatePayload['language'] = ['code' => $languageCode];

        $response = WhatsAppSendCharge::attempt(
            $this->credits,
            fn (): array => $this->api->post("/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => $templatePayload,
            ])
        );

        if ($response['success']) {
            $rawMessageId = isset($response['data']['messages'][0]['id']) ? $response['data']['messages'][0]['id'] : null;
            $messageId = is_string($rawMessageId) ? $rawMessageId : null;

            $this->credits->logUsage($to, $template, $messageId);
            $template->incrementUsage();

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent',
            ];
        }

        $error = $response['error'] ?? null;

        return [
            'success' => false,
            'error' => is_string($error) ? $error : 'Failed to send template message',
        ];
    }

    /**
     * Send a test message.
     */
    public function sendTestMessage(string $to, string $message): array
    {
        return $this->sendMessage($to, $message);
    }

    /**
     * Send a test WhatsApp message to verify setup.
     */
    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array
    {
        /**
         * @var WhatsAppPhoneNumber|null $phoneNumber
         */
        $account = $this->context->getAccount();
        $phoneNumber = $account?->phoneNumber?->phone_number;
        $recipient = $to ?? $phoneNumber;

        if (! $recipient) {
            return [
                'success' => false,
                'error' => 'No phone number configured',
            ];
        }

        $gym = Gym::query()->find($gymId);
        $gymName = $gym?->name ?? 'Your Gym';

        $message = "WhatsApp Integration Active!\n\nHello from {$gymName}!";

        return $this->sendMessage($recipient, $message);
    }

    /**
     * Send a broadcast to multiple recipients.
     */
    public function sendBroadcast(int $broadcastId): void
    {
        $this->getBroadcastService()->dispatchBroadcast($broadcastId);
    }

    /**
     * Get message status.
     *
     * @param  string  $messageId  Message SID or ID
     * @return array{success: bool, status?: string, delivered_at?: ?string, read_at?: ?string, error?: string, data?: array<array-key, mixed>}
     */
    public function getMessageStatus(string $messageId): array
    {
        return $this->api->get("/{$messageId}");
    }
}
