<?php

declare(strict_types=1);

namespace Tests\Unit\Forms\Components;

use App\Forms\Components\TemplatePicker;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TemplatePickerTest extends TestCase
{
    public function test_template_picker_evaluates_templates_and_items_per_page(): void
    {
        $templates = [
            ['id' => 1, 'name' => 'template_one', 'display_name' => 'Template One', 'body' => 'Body 1'],
            ['id' => 2, 'name' => 'template_two', 'display_name' => 'Template Two', 'body' => 'Body 2'],
        ];

        $picker = TemplatePicker::make('template_id')
            ->templates($templates)
            ->itemsPerPage(3);

        $resolved = $picker->getTemplates();

        $this->assertCount(2, $resolved);
        $this->assertEquals('1', $resolved[0]['id']);
        $this->assertEquals('Template One', $resolved[0]['title']);
        $this->assertEquals(3, $picker->getItemsPerPage());
    }

    public function test_template_picker_accepts_collection(): void
    {
        $templatesArray = [
            ['id' => 1, 'name' => 'template_one', 'display_name' => 'Template One', 'body' => 'Body 1'],
        ];

        $collection = new Collection($templatesArray);

        $picker = TemplatePicker::make('template_id')
            ->templates($collection);

        $resolved = $picker->getTemplates();

        $this->assertCount(1, $resolved);
        $this->assertEquals('1', $resolved[0]['id']);
        $this->assertEquals('Template One', $resolved[0]['title']);
    }
}
