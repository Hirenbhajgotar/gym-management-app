<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Services\Meta\MetaTemplateService;
use App\Services\WhatsApp\TemplateFieldMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemplateFieldMapperTest extends TestCase
{
    private function mapper(): TemplateFieldMapper
    {
        return new TemplateFieldMapper();
    }

    /**
     * @return array<string, array{0: string, 1: WhatsAppTemplateStatusEnum}>
     */
    public static function metaStatusCases(): array
    {
        return [
            'IN_REVIEW maps to PENDING' => ['IN_REVIEW', WhatsAppTemplateStatusEnum::PENDING],
            'PAUSED maps to DISABLED' => ['PAUSED', WhatsAppTemplateStatusEnum::DISABLED],
            'LIMIT_EXCEEDED maps to DISABLED' => ['LIMIT_EXCEEDED', WhatsAppTemplateStatusEnum::DISABLED],
            'PENDING_DELETION maps to DISABLED' => ['PENDING_DELETION', WhatsAppTemplateStatusEnum::DISABLED],
        ];
    }

    #[DataProvider('metaStatusCases')]
    public function test_maps_meta_template_statuses_including_new_arms(string $rawStatus, WhatsAppTemplateStatusEnum $expected): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => $rawStatus,
            'category' => 'UTILITY',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame($expected, $fields['status']);
    }

    public function test_maps_unknown_category_to_utility(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'TOTALLY_UNKNOWN',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame(TemplateCategoryEnum::UTILITY->value, $fields['category']);
    }

    public function test_maps_known_category_from_raw_value(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'MARKETING',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame(TemplateCategoryEnum::MARKETING->value, $fields['category']);
    }

    public function test_maps_template_language(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame('en_US', $fields['language']);
    }

    public function test_defaults_waba_template_language_to_config_default(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame(MetaTemplateService::defaultTemplateLanguage(), $fields['language']);
    }

    public function test_defaults_twilio_template_language_to_en(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'friendly_name' => 'welcome',
            'types' => ['twilio/text' => ['body' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::TWILIO);

        $this->assertSame('en', $fields['language']);
    }

    public function test_preserves_explicit_twilio_template_language(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'friendly_name' => 'welcome',
            'language' => 'es',
            'types' => ['twilio/text' => ['body' => 'Hola {{1}}']],
        ], WhatsAppProviderTypeEnum::TWILIO);

        $this->assertSame('es', $fields['language']);
    }

    public function test_sanitizes_and_clamps_overlong_language(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => 'en_US_EXTRA_LONG_VALUE_THAT_EXCEEDS_TWENTY_CHARS',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame(MetaTemplateService::defaultTemplateLanguage(), $fields['language']);
    }

    public function test_handles_empty_or_invalid_language(): void
    {
        $fields = $this->mapper()->mapToTemplateFields([
            'name' => 'welcome',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'language' => '   ',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ], WhatsAppProviderTypeEnum::WABA);

        $this->assertSame(MetaTemplateService::defaultTemplateLanguage(), $fields['language']);
    }

    public function test_sanitize_language_handles_valid_and_invalid_locales(): void
    {
        // 2-char code
        $this->assertSame('en', TemplateFieldMapper::sanitizeLanguage('en'));
        $this->assertSame('es', TemplateFieldMapper::sanitizeLanguage('es'));

        // 3-char code
        $this->assertSame('fil', TemplateFieldMapper::sanitizeLanguage('fil'));

        // Full locale with underscore
        $this->assertSame('en_US', TemplateFieldMapper::sanitizeLanguage('en_US'));
        $this->assertSame('pt_BR', TemplateFieldMapper::sanitizeLanguage('pt_BR'));

        // Full locale with hyphen
        $this->assertSame('zh-Hans', TemplateFieldMapper::sanitizeLanguage('zh-Hans'));

        // Rejection / fallback cases
        $this->assertSame('en_US', TemplateFieldMapper::sanitizeLanguage(null, WhatsAppProviderTypeEnum::WABA));
        $this->assertSame('en_US', TemplateFieldMapper::sanitizeLanguage('', WhatsAppProviderTypeEnum::WABA));
        $this->assertSame('en_US', TemplateFieldMapper::sanitizeLanguage('invalid!locale@#', WhatsAppProviderTypeEnum::WABA));
        $this->assertSame('en_US', TemplateFieldMapper::sanitizeLanguage('12345', WhatsAppProviderTypeEnum::WABA));

        // Twilio fallback
        $this->assertSame('en', TemplateFieldMapper::sanitizeLanguage(null, WhatsAppProviderTypeEnum::TWILIO));
        $this->assertSame('en', TemplateFieldMapper::sanitizeLanguage('', WhatsAppProviderTypeEnum::TWILIO));
    }
}
