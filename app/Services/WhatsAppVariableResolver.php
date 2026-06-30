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
     * @param  array{member?: GymMember, custom?: array<string, string>}  $context
     * @return array<string, string> Variables keyed by 1-indexed string (e.g., {"1": "John", "2": "FitZone"})
     */
    public function resolve(WhatsAppTemplate $template, array $context = []): array
    {
        $placeholders = $template->getVariablePlaceholders();
        $variables = [];

        foreach ($placeholders as $key => $placeholder) {
            $source = $placeholder['source'] ?? 'custom';
            $variables[(string) $key] = $this->resolveSource($source, $key, $placeholder, $context);
        }

        return $variables;
    }

    /**
     * Resolve a single data source to its actual value.
     */
    private function resolveSource(string $source, string $key, array $placeholder, array $context): string
    {
        if ($source === 'custom') {
            return $context['custom'][$key] ?? $placeholder['example'] ?? '';
        }

        $member = $context['member'] ?? null;

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
            'plan.name' => $member?->membershipPlan?->name,
            'plan.price' => $member?->membershipPlan?->price ? number_format((float) $member->membershipPlan->price) : null,
            'plan.duration' => $member?->membershipPlan?->duration ? (string) $member->membershipPlan->duration : null,

            // Trainer fields
            'trainer.name' => $member?->trainer?->name,
            'trainer.phone' => $member?->trainer?->phone,
            'trainer.specialization' => $member?->trainer?->specialization,

            // Payment fields (latest payment)
            'payment.formatted_amount' => $member?->latestPayment?->formatted_amount,
            'payment.amount' => $member?->latestPayment?->amount ? (string) $member->latestPayment->amount : null,
            'payment.payment_date' => $this->formatDate($member?->latestPayment?->payment_date),
            'payment.payment_method' => $member?->latestPayment?->payment_method ? ucfirst($member->latestPayment->payment_method) : null,
            'payment.invoice_number' => $member?->latestPayment?->invoice_number,

            default => null,
        };

        // Fall back to example value if source data is unavailable
        return $value ?? $placeholder['example'] ?? '';
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
            if (($placeholder['source'] ?? 'custom') === 'custom') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get only the custom variable keys from a template (ones that need manual input).
     *
     * @return array<string, array{placeholder: string, example: string}>
     */
    public static function getCustomVariables(WhatsAppTemplate $template): array
    {
        $placeholders = $template->getVariablePlaceholders();
        $custom = [];

        foreach ($placeholders as $key => $placeholder) {
            if (($placeholder['source'] ?? 'custom') === 'custom') {
                $custom[$key] = [
                    'placeholder' => $placeholder['placeholder'] ?? 'Variable ' . $key,
                    'example' => $placeholder['example'] ?? '',
                ];
            }
        }

        return $custom;
    }
}
