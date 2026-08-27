<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Forms\Components\SummaryStatCard;
use Tests\TestCase;

class SummaryStatCardTest extends TestCase
{
    public function test_summary_stat_card_parses_bar_separated_string(): void
    {
        $field = SummaryStatCard::make('summary')
            ->icon('heroicon-o-user-group')
            ->color('success')
            ->stats('Total Filtered Members: 1,240 | Expected Cost: $12.40');

        $this->assertEquals('heroicon-o-user-group', $field->getIcon());
        $this->assertEquals('success', $field->getColor());

        $stats = $field->getStats();
        $this->assertCount(2, $stats);
        $this->assertEquals('Total Filtered Members', $stats[0]['label']);
        $this->assertEquals('1,240', $stats[0]['value']);
        $this->assertEquals('Expected Cost', $stats[1]['label']);
        $this->assertEquals('$12.40', $stats[1]['value']);
    }

    public function test_summary_stat_card_supports_array_stats(): void
    {
        $field = SummaryStatCard::make('summary')
            ->stats([
                'Total Filtered Members' => '1,240',
                'Expected Cost' => '$12.40',
            ]);

        $stats = $field->getStats();
        $this->assertCount(2, $stats);
        $this->assertEquals('Total Filtered Members', $stats[0]['label']);
        $this->assertEquals('1,240', $stats[0]['value']);
        $this->assertEquals('Expected Cost', $stats[1]['label']);
        $this->assertEquals('$12.40', $stats[1]['value']);
    }

    public function test_summary_stat_card_supports_fluent_stat_method(): void
    {
        $field = SummaryStatCard::make('summary')
            ->color('primary')
            ->stat('Active Members', '500')
            ->stat('Pending Invoices', '12');

        $this->assertEquals('primary', $field->getColor());

        $stats = $field->getStats();
        $this->assertCount(2, $stats);
        $this->assertEquals('Active Members', $stats[0]['label']);
        $this->assertEquals('500', $stats[0]['value']);
        $this->assertEquals('Pending Invoices', $stats[1]['label']);
        $this->assertEquals('12', $stats[1]['value']);
    }

    public function test_summary_stat_card_evaluates_closures_dynamically(): void
    {
        $field = SummaryStatCard::make('summary')
            ->icon(fn () => 'heroicon-o-currency-dollar')
            ->color(fn () => 'warning')
            ->stats(fn () => 'Total Revenue: $50,000 | Pending: $2,500');

        $this->assertEquals('heroicon-o-currency-dollar', $field->getIcon());
        $this->assertEquals('warning', $field->getColor());

        $stats = $field->getStats();
        $this->assertCount(2, $stats);
        $this->assertEquals('Total Revenue', $stats[0]['label']);
        $this->assertEquals('$50,000', $stats[0]['value']);
        $this->assertEquals('Pending', $stats[1]['label']);
        $this->assertEquals('$2,500', $stats[1]['value']);
    }

    public function test_summary_stat_card_supports_dynamic_dividers(): void
    {
        $defaultField = SummaryStatCard::make('summary');
        $this->assertEquals('|', $defaultField->getDivider());

        $customChar = SummaryStatCard::make('summary')->divider('/');
        $this->assertEquals('/', $customChar->getDivider());

        $iconDivider = SummaryStatCard::make('summary')->divider('heroicon-o-chevron-right');
        $this->assertEquals('heroicon-o-chevron-right', $iconDivider->getDivider());

        $disabledDivider = SummaryStatCard::make('summary')->divider(false);
        $this->assertFalse($disabledDivider->getDivider());
    }
}
