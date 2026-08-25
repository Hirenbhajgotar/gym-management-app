<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Forms\Components\TemplateCard;
use App\Forms\Components\TemplatePicker;
use Tests\TestCase;

class TemplatePickerTest extends TestCase
{
    public function test_template_picker_normalizes_options_array(): void
    {
        $picker = TemplatePicker::make('template_id')
            ->options([
                [
                    'id' => 1,
                    'display_name' => 'Promotional Offer',
                    'content' => 'Hi {{1}}, check out our summer discount!',
                    'category' => 'Marketing',
                ],
                [
                    'id' => 2,
                    'display_name' => 'Welcome Message',
                    'content' => 'Welcome to {{1}}! We are thrilled to have you.',
                    'category' => 'Utility',
                ],
            ]);

        $templates = $picker->getTemplates();

        $this->assertCount(2, $templates);
        $this->assertEquals('1', $templates[0]['id']);
        $this->assertEquals('Promotional Offer', $templates[0]['title']);
        $this->assertEquals('Hi {{1}}, check out our summer discount!', $templates[0]['body']);
        $this->assertEquals('Marketing', $templates[0]['badge']);

        $this->assertEquals('2', $templates[1]['id']);
        $this->assertEquals('Welcome Message', $templates[1]['title']);
    }

    public function test_template_picker_configures_search_and_manage_url(): void
    {
        $picker = TemplatePicker::make('template_id')
            ->searchable(true)
            ->manageUrl('/admin/whatsapp-templates', 'Manage All Templates');

        $this->assertTrue($picker->isSearchable());
        $this->assertEquals('/admin/whatsapp-templates', $picker->getManageUrl());
        $this->assertEquals('Manage All Templates', $picker->getManageLabel());
    }

    public function test_template_card_single_radio_behavior(): void
    {
        $card = TemplateCard::make('template_id')
            ->cardValue('promo_offer')
            ->title('Promotional Offer')
            ->body('Get 20% off today!')
            ->selected(true);

        $this->assertEquals('promo_offer', $card->getCardValue());
        $this->assertEquals('Promotional Offer', $card->getTitle());
        $this->assertEquals('Get 20% off today!', $card->getBody());
        $this->assertTrue($card->isSelected());
    }
}
