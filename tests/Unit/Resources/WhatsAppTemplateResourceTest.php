<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use Tests\TestCase;

class WhatsAppTemplateResourceTest extends TestCase
{
    public function test_normalize_template_data_handles_livewire_wrapped_array_and_missing_name(): void
    {
        $data = [
            'body' => 'Hi {{1}}, this is a reminder that your membership payment of ₹{{2}} for {{3}} is due on {{4}}.',
            'variable_placeholder' => [
                '1' => [
                    ['name' => 'member_name', 'type' => 'text', 'source' => 'custom', 'example' => 'John', 'placeholder' => 'Custom Value'],
                    ['s' => 'arr'],
                ],
                '2' => [
                    ['type' => 'currency', 'source' => 'custom', 'example' => '100', 'placeholder' => 'Amount'],
                    ['s' => 'arr'],
                ],
            ],
        ];

        $normalized = WhatsAppTemplateResource::normalizeTemplateData($data);

        $this->assertEquals(4, $normalized['variable_count']);

        /** @var array<int|string, array<string, mixed>> $placeholders */
        $placeholders = $normalized['variable_placeholder'];

        // Check variable 1 (Livewire wrapped with name)
        $this->assertSame('{{1}}', $placeholders[1]['variable']);
        $this->assertSame('member_name', $placeholders[1]['name']);
        $this->assertSame('John', $placeholders[1]['example']);
        $this->assertSame('Custom Value', $placeholders[1]['placeholder']);

        // Check variable 2 (Livewire wrapped, missing name key - falls back to default 'gym_name')
        $this->assertSame('{{2}}', $placeholders[2]['variable']);
        $this->assertSame('gym_name', $placeholders[2]['name']);
        $this->assertSame('100', $placeholders[2]['example']);

        /** @var array<int, array<string, mixed>> $variables */
        $variables = $normalized['variables'];

        // Check variables summary list
        $this->assertCount(4, $variables);
        $this->assertSame('member_name', $variables[0]['name']);
        $this->assertSame('gym_name', $variables[1]['name']);
    }

    public function test_normalize_template_data_handles_flat_array_missing_keys(): void
    {
        $data = [
            'body' => 'Welcome {{1}} to {{2}}.',
            'variable_placeholder' => [
                '1' => [
                    'source' => 'member.name',
                    'placeholder' => 'Member Name',
                ],
            ],
        ];

        $normalized = WhatsAppTemplateResource::normalizeTemplateData($data);

        $this->assertEquals(2, $normalized['variable_count']);

        /** @var array<int|string, array<string, mixed>> $placeholders */
        $placeholders = $normalized['variable_placeholder'];

        $this->assertSame('member_name', $placeholders[1]['name']);
        $this->assertSame('member.name', $placeholders[1]['source']);
        $this->assertSame('gym_name', $placeholders[2]['name']);
    }
}
