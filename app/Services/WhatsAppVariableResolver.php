<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GymMember;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class WhatsAppVariableResolver
{
    /**
     * All available data sources that users can map template variables to.
     *
     * @return array<string, array{label: string, group: string, example: string}>
     */
    public static function getAvailableDataSources(): array
    {
        return [
            // Member data
            'member.name' => ['label' => 'Member Name', 'group' => 'Member', 'example' => 'John Doe'],
            'member.email' => ['label' => 'Member Email', 'group' => 'Member', 'example' => 'john@example.com'],
            'member.phone' => ['label' => 'Member Phone', 'group' => 'Member', 'example' => '+919876543210'],
            'member.dob' => ['label' => 'Date of Birth', 'group' => 'Member', 'example' => 'May 15, 1999'],
            'member.membership_start' => ['label' => 'Membership Start', 'group' => 'Member', 'example' => 'Jan 1, 2026'],
            'member.membership_end' => ['label' => 'Membership End', 'group' => 'Member', 'example' => 'Jul 1, 2026'],

            // Gym data
            'gym.name' => ['label' => 'Gym Name', 'group' => 'Gym', 'example' => 'FitZone Gym'],

            // Plan data
            'plan.name' => ['label' => 'Plan Name', 'group' => 'Plan', 'example' => 'Premium Monthly'],
            'plan.price' => ['label' => 'Plan Price', 'group' => 'Plan', 'example' => '1,500'],
            'plan.duration' => ['label' => 'Plan Duration (months)', 'group' => 'Plan', 'example' => '3'],

            // Trainer data
            'trainer.name' => ['label' => 'Trainer Name', 'group' => 'Trainer', 'example' => 'Mike Coach'],
            'trainer.phone' => ['label' => 'Trainer Phone', 'group' => 'Trainer', 'example' => '+919876543211'],
            'trainer.specialization' => ['label' => 'Trainer Specialization', 'group' => 'Trainer', 'example' => 'Strength Training'],

            // Payment data
            'payment.formatted_amount' => ['label' => 'Payment Amount (formatted)', 'group' => 'Payment', 'example' => 'Rs.1,500.00'],
            'payment.amount' => ['label' => 'Payment Amount (raw)', 'group' => 'Payment', 'example' => '1500.00'],
            'payment.payment_date' => ['label' => 'Payment Date', 'group' => 'Payment', 'example' => 'Jan 15, 2026'],
            'payment.payment_method' => ['label' => 'Payment Method', 'group' => 'Payment', 'example' => 'Cash'],
            'payment.invoice_number' => ['label' => 'Invoice Number', 'group' => 'Payment', 'example' => 'INV-001'],

            // Custom (user provides value at send time)
            'custom' => ['label' => 'Custom Value', 'group' => 'Other', 'example' => ''],
        ];
    }

    /**
     * Get data sources formatted for a Filament Select dropdown (grouped).
     *
     * @return array<string, array<string, string>>
     */
    public static function getGroupedSelectOptions(): array
    {
        $options = [];

        foreach (self::getAvailableDataSources() as $key => $source) {
            $options[$source['group']][$key] = $source['label'];
        }

        return $options;
    }

    /**
     * Get the example value for a given data source key.
     */
    public static function getExampleForSource(string $sourceKey): string
    {
        $sources = self::getAvailableDataSources();

        return $sources[$sourceKey]['example'] ?? '';
    }

    /**
     * Resolve all template variables using actual data from the provided context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string> Variables keyed by 1-indexed string (e.g., {"1": "John", "2": "FitZone"})
     */
    public function resolve(WhatsAppTemplate $template, array $context = []): array
    {
        $placeholders = $template->getVariablePlaceholders();
        $variables = [];

        foreach ($placeholders as $key => $placeholder) {
            /** @var array<string, mixed> $placeholderArr */
            $placeholderArr = is_array($placeholder) ? $placeholder : [];
            $source = is_string($placeholderArr['source'] ?? null) ? $placeholderArr['source'] : 'custom';
            $variables[(string) $key] = $this->resolveSource($source, (string) $key, $placeholderArr, $context);
        }

        return $variables;
    }

    /**
     * Resolve a single data source to its actual value.
     *
     * @param  array<string, mixed>  $placeholder
     * @param  array<string, mixed>  $context
     */
    private function resolveSource(string $source, string $key, array $placeholder, array $context): string
    {
        if ($source === 'custom') {
            $customData = is_array($context['custom'] ?? null) ? $context['custom'] : [];
            $customVal = $customData[$key] ?? $customData[(int) $key] ?? null;

            return $this->valueOrExample($customVal, $placeholder['example'] ?? '');
        }

        /** @var GymMember|null $member */
        $member = ($context['member'] ?? null) instanceof GymMember ? $context['member'] : null;

        $plan = $member?->membershipPlan;
        $trainer = $member?->trainer;
        $payment = $member?->latestPayment;

        $value = match ($source) {
            // Member fields
            'member.name' => $member?->name,
            'member.email' => $member?->email,
            'member.phone' => $member?->phone,
            'member.dob' => $this->formatDate($member?->dob),
            'member.membership_start' => $this->formatDate($member?->membership_start),
            'member.membership_end' => $this->formatDate($member?->membership_end),

            // Gym fields (loaded through member relationship)
            'gym.name' => $member?->gym?->name,

            // Plan fields
            'plan.name' => $plan?->name,
            'plan.price' => $plan?->price !== null
                ? number_format((float) $plan->price)
                : null,
            'plan.duration' => $plan?->duration !== null
                ? (string) $plan->duration
                : null,

            // Trainer fields
            'trainer.name' => $trainer?->name,
            'trainer.phone' => $trainer?->phone,
            'trainer.specialization' => $trainer?->specialization,

            // Payment fields (latest payment)
            'payment.formatted_amount' => is_string($payment?->formatted_amount) || is_numeric($payment?->formatted_amount)
                ? (string) $payment->formatted_amount
                : null,
            'payment.amount' => $payment?->amount !== null
                ? (string) $payment->amount
                : null,
            'payment.payment_date' => $this->formatDate($payment?->payment_date),
            'payment.payment_method' => is_string($payment?->payment_method)
                ? ucfirst($payment->payment_method)
                : null,
            'payment.invoice_number' => $payment?->invoice_number,

            default => null,
        };

        return $this->valueOrExample($value, $placeholder['example'] ?? '');
    }

    /**
     * Return the provided value when it is a non-empty string or numeric; otherwise
     * fall back to the placeholder example. Preserves falsy-but-valid values such as
     * the string "0" or the integer 0 (so they render as "0", not the example).
     */
    private function valueOrExample(mixed $value, mixed $example): string
    {
        if ($value !== null && (is_string($value) || is_numeric($value))) {
            $strValue = (string) $value;
            if ($strValue !== '') {
                return $strValue;
            }
        }

        return is_string($example) || is_numeric($example) ? (string) $example : '';
    }

    /**
     * Format a date value for display in messages.
     */
    private function formatDate(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('M j, Y');
        }

        if (is_string($date)) {
            return Date::parse($date)->format('M j, Y');
        }

        return null;
    }

    /**
     * Get the list of data source keys that require a member context to resolve.
     *
     * @return array<int, string>
     */
    public static function getSourcesRequiringMember(): array
    {
        return array_filter(
            array_keys(self::getAvailableDataSources()),
            fn (string $key) => $key !== 'custom'
        );
    }

    /**
     * Check if a template has any custom (non-auto-resolvable) variables.
     */
    public static function hasCustomVariables(WhatsAppTemplate $template): bool
    {
        $placeholders = $template->getVariablePlaceholders();

        foreach ($placeholders as $placeholder) {
            $source = is_array($placeholder) ? ($placeholder['source'] ?? 'custom') : 'custom';
            if ($source === 'custom') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get only the custom variable keys from a template (ones that need manual input).
     *
     * @return array<int|string, array{placeholder: string, example: string}>
     */
    public static function getCustomVariables(WhatsAppTemplate $template): array
    {
        $placeholders = $template->getVariablePlaceholders();
        /** @var array<int|string, array{placeholder: string, example: string}> $custom */
        $custom = [];

        foreach ($placeholders as $key => $placeholder) {
            $placeholderArr = is_array($placeholder) ? $placeholder : [];
            $source = is_string($placeholderArr['source'] ?? null) ? $placeholderArr['source'] : 'custom';

            if ($source === 'custom') {
                $placeholderText = isset($placeholderArr['placeholder']) && (is_string($placeholderArr['placeholder']) || is_numeric($placeholderArr['placeholder']))
                    ? (string) $placeholderArr['placeholder']
                    : 'Variable ' . $key;

                $exampleText = isset($placeholderArr['example']) && (is_string($placeholderArr['example']) || is_numeric($placeholderArr['example']))
                    ? (string) $placeholderArr['example']
                    : '';

                $custom[(string) $key] = [
                    'placeholder' => $placeholderText,
                    'example' => $exampleText,
                ];
            }
        }

        return $custom;
    }
}
