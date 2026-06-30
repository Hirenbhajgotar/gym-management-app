<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\DTOs\WhatsAppTemplates\TemplatePlaceholder;
use App\DTOs\WhatsAppTemplates\TemplateVariable;

class TemplateVariableHelper
{
    private const DEFAULT_PLACEHOLDERS = [
        '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
        '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
        '3' => ['placeholder' => 'Date', 'name' => 'date', 'example' => 'January 15, 2026', 'type' => 'text', 'source' => 'member.membership_end'],
        '4' => ['placeholder' => 'Amount', 'name' => 'amount', 'example' => '₹1,499', 'type' => 'number', 'source' => 'payment.formatted_amount'],
        '5' => ['placeholder' => 'Time', 'name' => 'time', 'example' => '7:00 AM', 'type' => 'text', 'source' => 'custom'],
        '6' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Plan', 'type' => 'text', 'source' => 'plan.name'],
    ];

    /**
     * @return list<TemplateVariable>
     */
    public static function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $variables = [];
        $usedNumbers = [];

        foreach ($matches[1] as $number) {
            if (! in_array($number, $usedNumbers, true)) {
                $usedNumbers[] = $number;
                $variables[] = new TemplateVariable(
                    name: 'variable_' . $number,
                    format: 'text',
                    example: 'Example ' . $number,
                );
            }
        }

        return $variables;
    }

    /**
     * @param  list<TemplateVariable>  $variables
     * @return array<int, TemplatePlaceholder>
     */
    public static function generateDefaultPlaceholders(array $variables): array
    {
        $placeholders = [];

        foreach ($variables as $variable) {
            if (preg_match('/variable_(\d+)/', $variable->name, $matches)) {
                $num = $matches[1];
                $default = self::DEFAULT_PLACEHOLDERS[$num] ?? [
                    'placeholder' => 'Variable ' . $num,
                    'name' => 'variable_' . $num,
                    'example' => 'Example ' . $num,
                    'type' => 'text',
                    'source' => 'custom',
                ];
                $placeholders[$num] = TemplatePlaceholder::fromArray($default);
            }
        }

        return $placeholders;
    }

    public static function parseVariables(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    public static function extractBodyFromTypes(array $types): ?string
    {
        foreach (['twilio/text', 'twilio/media', 'twilio/location', 'twilio/card'] as $type) {
            if (isset($types[$type]['body'])) {
                return $types[$type]['body'];
            }
        }

        return null;
    }

    /**
     * Get default variable data.
     */
    public static function getDefaultVariableData(): array
    {
        return self::DEFAULT_PLACEHOLDERS;
    }
}
