<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\DTOs\WhatsAppTemplates\TemplatePlaceholder;
use App\DTOs\WhatsAppTemplates\TemplateVariable;
use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Services\Meta\MetaTemplateService;
use App\Services\Support\TemplateVariableHelper;
use App\Services\Twilio\TwilioTemplateService;

class TemplateFieldMapper
{
    /**
     * @param  array<string, mixed>  $rawData
     * @return array{name: string|null, display_name: string|null, body: string|null, category: string, language: string, status: WhatsAppTemplateStatusEnum, provider_sid: string|null, variables: list<TemplateVariable>, variable_count: int, variable_placeholder: array<int, TemplatePlaceholder>}
     */
    public function mapToTemplateFields(array $rawData, ?WhatsAppProviderTypeEnum $providerType): array
    {
        $name = $this->extractTemplateName($rawData, $providerType);
        $body = $this->extractBody($rawData, $providerType);
        $variables = $this->extractVariables($rawData, $body, $providerType);

        return [
            'name' => $name,
            'display_name' => $name,
            'body' => $body,
            'category' => $this->extractCategory($rawData, $providerType),
            'language' => $this->extractLanguage($rawData, $providerType),
            'status' => $this->extractStatus($rawData, $providerType),
            'provider_sid' => $this->extractProviderSid($rawData, $providerType),
            'variables' => $variables,
            'variable_count' => count($variables),
            'variable_placeholder' => TemplateVariableHelper::generateDefaultPlaceholders($variables),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    public function filterValidTemplates(array $templates, ?WhatsAppProviderTypeEnum $providerType): array
    {
        return array_values(array_filter($templates, function (array $templateData) use ($providerType): bool {
            return $this->extractTemplateName($templateData, $providerType) !== null;
        }));
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractTemplateName(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        $name = match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $templateData['friendly_name'] ?? $templateData['name'] ?? null,
            WhatsAppProviderTypeEnum::WABA => $templateData['name'] ?? null,
            default => $templateData['friendly_name'] ?? $templateData['name'] ?? null,
        };

        return is_string($name) ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractProviderSid(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        $sid = match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $templateData['sid'] ?? null,
            WhatsAppProviderTypeEnum::WABA => $templateData['id'] ?? null,
            default => $templateData['sid'] ?? $templateData['id'] ?? null,
        };

        return is_string($sid) ? $sid : null;
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractBody(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $this->extractTwilioBody($templateData),
            WhatsAppProviderTypeEnum::WABA => $this->extractMetaBody($templateData),
            default => is_string($templateData['body'] ?? null)
                ? ($templateData['body'])
                : $this->extractTwilioBody($templateData),
        };
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractTwilioBody(array $templateData): ?string
    {
        $types = $templateData['types'] ?? [];

        if (is_array($types)) {
            $twilioText = $types['twilio/text'] ?? null;
            $body = is_array($twilioText) ? ($twilioText['body'] ?? null) : null;

            return is_string($body) ? $body : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractMetaBody(array $templateData): ?string
    {
        $components = $templateData['components'] ?? [];

        if (! is_array($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (is_array($component) && ($component['type'] ?? '') === 'BODY') {
                $text = $component['text'] ?? null;

                return is_string($text) ? $text : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractCategory(array $templateData, ?WhatsAppProviderTypeEnum $providerType): string
    {
        $category = $templateData['category'] ?? null;

        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => TemplateCategoryEnum::UTILITY->value,
            WhatsAppProviderTypeEnum::WABA => TemplateCategoryEnum::tryFromOrDefault(is_string($category) ? $category : null)->value,
            default => TemplateCategoryEnum::UTILITY->value,
        };
    }

    public static function sanitizeLanguage(?string $lang, ?WhatsAppProviderTypeEnum $providerType = null): string
    {
        if (is_string($lang) && $lang !== '') {
            $trimmed = trim($lang);
            if (preg_match('/^[a-zA-Z]{2,3}(?:[_-][a-zA-Z0-9]{2,8})?$/', $trimmed)) {
                return $trimmed;
            }
        }

        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => TwilioTemplateService::TWILIO_DEFAULT_LANGUAGE,
            default => MetaTemplateService::defaultTemplateLanguage(),
        };
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractLanguage(array $templateData, ?WhatsAppProviderTypeEnum $providerType): string
    {
        return self::sanitizeLanguage(is_string($templateData['language'] ?? null) ? $templateData['language'] : null, $providerType);
    }

    /**
     * @param  array<string, mixed>  $templateData
     */
    private function extractStatus(array $templateData, ?WhatsAppProviderTypeEnum $providerType): WhatsAppTemplateStatusEnum
    {
        if ($providerType === WhatsAppProviderTypeEnum::WABA) {
            $rawStatus = $templateData['status'] ?? null;

            if (is_string($rawStatus) && $rawStatus !== '') {
                return match (strtoupper($rawStatus)) {
                    'PENDING', 'IN_REVIEW' => WhatsAppTemplateStatusEnum::PENDING,
                    'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
                    'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
                    'DISABLED', 'PAUSED', 'LIMIT_EXCEEDED', 'PENDING_DELETION' => WhatsAppTemplateStatusEnum::DISABLED,
                    default => WhatsAppTemplateStatusEnum::DRAFT,
                };
            }
        }

        return WhatsAppTemplateStatusEnum::DRAFT;
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @return list<TemplateVariable>
     */
    private function extractVariables(array $rawData, ?string $body, ?WhatsAppProviderTypeEnum $providerType): array
    {
        if ($body) {
            return TemplateVariableHelper::extractVariables($body);
        }

        if ($providerType === WhatsAppProviderTypeEnum::TWILIO) {
            $twilioVars = $rawData['variables'] ?? [];
            if (is_array($twilioVars) && filled($twilioVars)) {
                return $this->convertTwilioVariables($twilioVars);
            }
        }

        return [];
    }

    /**
     * @param  array<mixed, mixed>  $twilioVars
     * @return list<TemplateVariable>
     */
    private function convertTwilioVariables(array $twilioVars): array
    {
        $result = [];
        $index = 1;

        foreach ($twilioVars as $var) {
            if (is_array($var)) {
                $varName = $var['name'] ?? null;
                $varFormat = $var['format'] ?? null;
                $varExample = $var['example'] ?? null;

                $result[] = new TemplateVariable(
                    name: is_string($varName) ? $varName : "{{$index}}",
                    format: is_string($varFormat) ? $varFormat : 'text',
                    example: is_string($varExample) ? $varExample : "{{$index}}",
                );
            }
            $index++;
        }

        return $result;
    }
}
