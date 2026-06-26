<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
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

        $this->gym = Gym::factory()->create();
        $this->templateService = resolve(WhatsAppTemplateService::class);
    }

    // ========== DEFAULT TEMPLATES ==========

    #[Test]
    public function it_creates_default_templates_for_gym(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::where('gym_id', $this->gym->id)->get();

        // Should create 8 default utility templates
        $this->assertGreaterThanOrEqual(8, $templates->count());

        // All should be default and active
        $templates->each(function ($template) {
            $this->assertTrue($template->is_default);
            $this->assertTrue($template->is_active);
        });
    }

    #[Test]
    public function it_creates_templates_with_correct_names(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $expectedNames = [
            'welcome_message',
            'membership_expiry',
            'payment_reminder',
            'payment_confirmation',
            'attendance_milestone',
            'class_reminder',
            'birthday_wish',
            'renewal_reminder',
        ];

        $templates = WhatsAppTemplate::where('gym_id', $this->gym->id)
            ->pluck('name')
            ->toArray();

        foreach ($expectedNames as $name) {
            $this->assertContains($name, $templates, "Template '{$name}' was not created");
        }
    }

    #[Test]
    public function it_creates_templates_with_category_utility(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::where('gym_id', $this->gym->id)->get();

        $templates->each(function ($template) {
            $this->assertEquals('utility', $template->category);
        });
    }

    #[Test]
    public function it_creates_templates_with_variables(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);

        $templates = WhatsAppTemplate::where('gym_id', $this->gym->id)->get();

        $templates->each(function ($template) {
            $this->assertNotNull($template->body);
            $this->assertGreaterThanOrEqual(0, $template->variable_count);
        });
    }

    public function default_templates_are_idempotent(): void
    {
        $this->templateService->createDefaultTemplates($this->gym->id);
        $firstCount = WhatsAppTemplate::where('gym_id', $this->gym->id)->count();

        // Calling again should not duplicate templates
        $this->templateService->createDefaultTemplates($this->gym->id);
        $secondCount = WhatsAppTemplate::where('gym_id', $this->gym->id)->count();

        $this->assertEquals($firstCount, $secondCount);
    }

    // ========== TEMPLATE RETRIEVAL ==========

    #[Test]
    public function it_returns_default_templates_definition(): void
    {
        $defaults = $this->templateService->getDefaultTemplates();

        $this->assertIsArray($defaults);
        $this->assertNotEmpty($defaults);
    }

    #[Test]
    public function it_retrieves_approved_templates_for_gym(): void
    {
        WhatsAppTemplate::create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, welcome to {{2}}!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name'], ['name' => 'gym_name']],
            'variable_count' => 2,
            'status' => 'approved',
            'is_default' => false,
            'is_active' => true,
        ]);

        WhatsAppTemplate::create([
            'gym_id' => $this->gym->id,
            'name' => 'pending_template',
            'display_name' => 'Pending Template',
            'body' => 'Hello {{1}}!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name']],
            'variable_count' => 1,
            'status' => 'pending',
            'is_default' => false,
            'is_active' => true,
        ]);

        $approved = $this->templateService->getApprovedTemplates($this->gym->id);

        $this->assertCount(1, $approved);
        $this->assertEquals('test_template', $approved->first()->name);
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
        $this->assertEquals('draft', $template->status);
    }

    // ========== TEMPLATE DELETION ==========

    #[Test]
    public function it_deletes_a_template(): void
    {
        $template = WhatsAppTemplate::create([
            'gym_id' => $this->gym->id,
            'name' => 'to_delete',
            'display_name' => 'To Delete',
            'body' => 'Test',
            'category' => 'utility',
            'variable_count' => 0,
            'status' => 'draft',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->templateService->deleteTemplate($template->id);

        $this->assertDatabaseMissing('whatsapp_templates', ['id' => $template->id]);
    }
}
