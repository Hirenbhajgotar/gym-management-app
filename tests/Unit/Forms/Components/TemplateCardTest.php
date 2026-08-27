<?php

declare(strict_types=1);

namespace Tests\Unit\Forms\Components;

use App\Forms\Components\TemplateCard;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class TemplateCardTest extends TestCase
{
    public function test_template_card_evaluates_card_attributes(): void
    {
        $card = TemplateCard::make('template_id')
            ->cardValue('123')
            ->title('Marketing Promo')
            ->body('Get 20% discount!')
            ->badge('Marketing')
            ->color('emerald');

        $this->assertSame('123', $card->getCardValue());
        $this->assertSame('Marketing Promo', $card->getTitle());
        $this->assertSame('Get 20% discount!', $card->getBody());
        $this->assertSame('Marketing', $card->getBadge());
        $this->assertSame('emerald', $card->getColor());
    }

    public function test_template_card_detects_validation_errors(): void
    {
        $card = TemplateCard::make('template_id')
            ->cardValue('123');

        $this->assertFalse($card->hasValidationError());
        $this->assertNull($card->getValidationError());

        // Simulate validation error in session error bag
        $errors = new ViewErrorBag();
        $messageBag = new MessageBag([
            'template_id' => ['The template choice is required.'],
        ]);
        $errors->put('default', $messageBag);
        session(['errors' => $errors]);

        $this->assertTrue($card->hasValidationError());
        $this->assertSame('The template choice is required.', $card->getValidationError());
    }
}
