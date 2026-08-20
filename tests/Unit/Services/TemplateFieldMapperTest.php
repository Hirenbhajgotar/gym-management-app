<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
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
}
