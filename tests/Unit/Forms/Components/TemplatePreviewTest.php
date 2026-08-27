<?php

declare(strict_types=1);

namespace Tests\Unit\Forms\Components;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Forms\Components\TemplatePreview;
use App\Models\WhatsAppTemplate;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    public function test_template_preview_returns_empty_state_when_no_template(): void
    {
        $preview = TemplatePreview::make('preview');

        $data = $preview->getPreviewData();

        $this->assertFalse($data['has_template']);
    }

    public function test_template_preview_resolves_template_model_data(): void
    {
        $template = new WhatsAppTemplate([
            'id' => 999,
            'name' => 'membership_renewal_notice',
            'display_name' => 'Membership Renewal Notice',
            'body' => 'Hello {{1}}, your membership at {{2}} expires on {{3}}.',
            'category' => TemplateCategoryEnum::UTILITY,
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'variable_placeholder' => [
                1 => ['source' => 'member.name', 'placeholder' => 'Member Name', 'example' => 'John Doe'],
                2 => ['source' => 'gym.name', 'placeholder' => 'Gym Name', 'example' => 'FitZone Gym'],
                3 => ['source' => 'member.membership_end', 'placeholder' => 'Expiration Date', 'example' => 'Dec 31, 2026'],
            ],
            'variable_count' => 3,
        ]);

        $component = TemplatePreview::make('preview')
            ->template($template);

        $data = $component->getPreviewData();

        $this->assertTrue($data['has_template']);
        $this->assertSame('Membership Renewal Notice', $data['display_name']);
        $this->assertSame(3, $data['variable_count']);

        /** @var array<int, mixed> $mappings */
        $mappings = $data['variable_mappings'];
        $this->assertCount(3, $mappings);

        /** @var string $resolvedBody */
        $resolvedBody = $data['resolved_body'];

        // Check highlighted body contains replaced text
        $this->assertStringContainsString('John Doe', $resolvedBody);
        $this->assertStringContainsString('FitZone Gym', $resolvedBody);
    }

    public function test_template_preview_handles_array_fallback(): void
    {
        $raw = [
            'id' => 10,
            'name' => 'welcome_template',
            'display_name' => 'Welcome Message',
            'body' => 'Welcome {{1}}!',
            'category' => 'UTILITY',
            'status' => 'approved',
            'variable_placeholder' => [
                1 => ['source' => 'member.name', 'placeholder' => 'Member Name', 'example' => 'Alice'],
            ],
        ];

        $component = TemplatePreview::make('preview')
            ->template($raw);

        $data = $component->getPreviewData();

        $this->assertTrue($data['has_template']);
        $this->assertSame('Welcome Message', $data['display_name']);

        /** @var array<int, mixed> $mappings */
        $mappings = $data['variable_mappings'];
        $this->assertCount(1, $mappings);
    }
}
