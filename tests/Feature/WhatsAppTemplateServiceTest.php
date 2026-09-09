<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\UpdateTemplateData;
use App\Enums\NotificationType;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaTemplateService;
use App\Services\WhatsAppTemplateService;
use Database\Seeders\WhatsAppDefaultTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WhatsAppDefaultTemplatesSeeder::class);
        $this->gym = Gym::factory()->create();
        $this->templateService = resolve(WhatsAppTemplateService::class);
    }

    // ========== DEFAULT TEMPLATES ==========

    #[Test]
    public function it_creates_default_templates_for_gym(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $this->assertCount(9, $templates);

        // All should be default and active
        $templates->each(function ($template) {
            $this->assertTrue($template->is_default);
            $this->assertTrue($template->is_active);
        });
    }

    #[Test]
    public function default_templates_are_idempotent(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);
        $firstCount = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->count();

        // Calling again should not duplicate templates
        $this->templateService->createDefaultTemplates($this->gym->id);
        $secondCount = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->count();

        $this->assertEquals(9, $firstCount);
        $this->assertEquals($firstCount, $secondCount);
    }

    #[Test]
    public function it_creates_templates_with_correct_names(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $expectedNames = [
            NotificationType::WELCOME->value,
            NotificationType::MEMBERSHIP_EXPIRY->value,
            NotificationType::PAYMENT_REMINDER->value,
            NotificationType::CLASS_REMINDER->value,
            NotificationType::BIRTHDAY->value,
        ];

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)
            ->pluck('name')
            ->toArray();

        foreach ($expectedNames as $name) {
            $this->assertContains($name, $templates, "Template '{$name}' was not created");
        }
    }

    #[Test]
    public function it_creates_templates_with_valid_categories(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $templates->each(function ($template) {
            $category = $template->category->value ?? $template->category;
            $this->assertContains($category, ['utility', 'marketing', 'authentication']);
        });
    }

    #[Test]
    public function it_creates_templates_with_variables(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::query()->where('gym_id', $this->gym->id)->get();

        $templates->each(function ($template) {
            $this->assertNotNull($template->body);
            $this->assertGreaterThanOrEqual(0, $template->variable_count);
        });
    }

    // ========== TEMPLATE RETRIEVAL ==========

    #[Test]
    public function it_retrieves_approved_templates_for_gym(): void
    {
        WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, welcome to {{2}}!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name'], ['name' => 'gym_name']],
            'variable_count' => 2,
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'is_default' => false,
            'is_active' => true,
        ]);

        WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'pending_template',
            'display_name' => 'Pending Template',
            'body' => 'Hello {{1}}!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name']],
            'variable_count' => 1,
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'is_default' => false,
            'is_active' => true,
        ]);

        $approved = $this->templateService->getApprovedTemplates($this->gym->id);

        $this->assertCount(1, $approved);
        $this->assertEquals('test_template', $approved->first()?->name);
    }

    // ========== TEMPLATE CREATION ==========

    #[Test]
    public function it_creates_custom_template(): void
    {
        $templateData = [
            'name' => 'custom_notification',
            'display_name' => 'Custom Notification',
            'body' => 'Hey {{1}}, your session at {{2}} is at {{3}}.',
            'category' => 'utility',
            'variables' => [
                ['name' => 'member_name', 'example' => 'John'],
                ['name' => 'gym_name', 'example' => 'FitZone'],
                ['name' => 'time', 'example' => '5:00 PM'],
            ],
        ];

        $template = $this->templateService->createTemplate($this->gym->id, CreateTemplateData::fromArray($templateData));

        $this->assertInstanceOf(WhatsAppTemplate::class, $template);
        $this->assertEquals('custom_notification', $template->name);
        $this->assertEquals($this->gym->id, $template->gym_id);
        $this->assertEquals(3, $template->variable_count);
        $this->assertSame(WhatsAppTemplateStatusEnum::DRAFT, $template->status);
    }

    // ========== TEMPLATE DELETION ==========

    #[Test]
    public function it_creates_template_with_default_and_explicit_language(): void
    {
        $defaultTemplate = $this->templateService->createTemplate($this->gym->id, CreateTemplateData::fromArray([
            'name' => 'default_lang',
            'display_name' => 'Default Lang',
            'body' => 'Hello',
            'category' => 'utility',
        ]));

        $this->assertEquals(MetaTemplateService::defaultTemplateLanguage(), $defaultTemplate->language);

        $customTemplate = $this->templateService->createTemplate($this->gym->id, CreateTemplateData::fromArray([
            'name' => 'custom_lang',
            'display_name' => 'Custom Lang',
            'body' => 'Hola',
            'category' => 'utility',
            'language' => 'es_ES',
        ]));

        $this->assertEquals('es_ES', $customTemplate->language);
    }

    #[Test]
    public function it_updates_template_while_preserving_or_updating_language(): void
    {
        $template = $this->templateService->createTemplate($this->gym->id, CreateTemplateData::fromArray([
            'name' => 'test_lang',
            'display_name' => 'Test Lang',
            'body' => 'Original body',
            'category' => 'utility',
            'language' => 'en_US',
        ]));

        // Update without language preserves existing language
        $updated = $this->templateService->updateTemplate($template, UpdateTemplateData::fromArray([
            'display_name' => 'Updated Name',
        ]));

        $this->assertEquals('en_US', $updated?->language);
        $this->assertEquals('Updated Name', $updated?->display_name);

        // Update with explicit language changes language
        $updatedWithLang = $this->templateService->updateTemplate($template, UpdateTemplateData::fromArray([
            'language' => 'es_ES',
        ]));

        $this->assertEquals('es_ES', $updatedWithLang?->language);
    }

    #[Test]
    public function it_deletes_a_template(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'to_delete',
            'display_name' => 'To Delete',
            'body' => 'Test',
            'category' => 'utility',
            'variable_count' => 0,
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->templateService->softDeleteTemplate($template);

        $this->assertSoftDeleted('whatsapp_templates', ['id' => $template->id]);
    }
}
