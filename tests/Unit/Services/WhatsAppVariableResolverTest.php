<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppVariableResolver;
use Tests\TestCase;

class WhatsAppVariableResolverTest extends TestCase
{
    public function test_resolves_variables_with_integer_placeholder_keys_without_type_error(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                1 => ['source' => 'member.name', 'placeholder' => 'Member Name', 'example' => 'John'],
                2 => ['source' => 'gym.name', 'placeholder' => 'Gym Name', 'example' => 'FitGym'],
                3 => ['source' => 'custom', 'placeholder' => 'Promo Code', 'example' => 'OFFER50'],
            ],
        ]);

        $resolver = new WhatsAppVariableResolver();

        $gym = new Gym(['name' => 'PowerGym']);
        $member = new GymMember(['name' => 'Rahul Sharma']);
        $member->setRelation('gym', $gym);

        $resolved = $resolver->resolve($template, [
            'member' => $member,
            'custom' => [3 => 'SUMMER2026'],
        ]);

        $this->assertSame([
            '1' => 'Rahul Sharma',
            '2' => 'PowerGym',
            '3' => 'SUMMER2026',
        ], $resolved);
    }

    public function test_get_custom_variables_returns_string_keys(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                '1' => ['source' => 'member.name', 'placeholder' => 'Member Name', 'example' => 'John'],
                '2' => ['source' => 'custom', 'placeholder' => 'Custom Code', 'example' => 'XYZ'],
            ],
        ]);

        $customVars = WhatsAppVariableResolver::getCustomVariables($template);

        $this->assertArrayHasKey('2', $customVars);
        $this->assertSame('Custom Code', $customVars['2']['placeholder']);
    }

    public function test_custom_value_zero_string_is_rendered_as_zero_not_example(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                3 => ['source' => 'custom', 'placeholder' => 'Promo Code', 'example' => 'OFFER50'],
            ],
        ]);

        $resolver = new WhatsAppVariableResolver();

        $this->assertSame(
            ['3' => '0'],
            $resolver->resolve($template, ['custom' => [3 => '0']]),
            'A custom value of "0" must render as "0", not the placeholder example.'
        );

        $this->assertSame(
            ['3' => '0'],
            $resolver->resolve($template, ['custom' => [3 => 0]]),
            'A custom value of 0 (int) must render as "0", not the placeholder example.'
        );
    }

    public function test_source_value_zero_string_is_rendered_as_zero_not_example(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                2 => ['source' => 'gym.name', 'placeholder' => 'Gym Name', 'example' => 'FitGym'],
            ],
        ]);

        $resolver = new WhatsAppVariableResolver();

        $gym = new Gym(['name' => '0']);
        $member = new GymMember(['name' => 'Rahul Sharma']);
        $member->setRelation('gym', $gym);

        $this->assertSame(
            ['2' => '0'],
            $resolver->resolve($template, ['member' => $member]),
            'A source value of "0" must render as "0", not the placeholder example.'
        );
    }

    public function test_non_array_placeholder_entry_is_handled_gracefully(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                1 => 'this entry is not an array',
            ],
        ]);

        $resolver = new WhatsAppVariableResolver();

        // Non-array placeholder entries fall back to the custom (empty) path,
        // so resolution must not throw and must return an empty string.
        $this->assertSame(
            ['1' => ''],
            $resolver->resolve($template, []),
            'A non-array placeholder entry must be handled gracefully.'
        );
    }

    public function test_get_custom_variables_handles_non_array_entry(): void
    {
        $template = new WhatsAppTemplate([
            'name' => 'test_template',
            'variable_placeholder' => [
                1 => 'this entry is not an array',
            ],
        ]);

        $custom = WhatsAppVariableResolver::getCustomVariables($template);

        $this->assertArrayHasKey('1', $custom);
        $this->assertSame('Variable 1', $custom['1']['placeholder']);
    }
}
