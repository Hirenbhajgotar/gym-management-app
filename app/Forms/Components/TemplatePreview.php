<?php

declare(strict_types=1);

namespace App\Forms\Components;

use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use App\Services\WhatsAppVariableResolver;
use Closure;
use Filament\Forms\Components\Field;

/**
 * TemplatePreview
 *
 * A custom field placeholder component for WhatsApp template live preview in Filament forms.
 * Renders a realistic WhatsApp chat bubble, variable resolution highlights, category/status badges,
 * and dynamic variable source mappings.
 */
class TemplatePreview extends Field
{
    protected string $view = 'forms.components.template-preview';

    /** @var WhatsAppTemplate|array<string, mixed>|string|Closure|null */
    protected WhatsAppTemplate|array|string|Closure|null $template = null;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $sampleContext = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /**
     * Set the selected template model, ID, array, or Closure.
     *
     * @param  WhatsAppTemplate|array<string, mixed>|string|Closure|null  $template
     */
    public function template(WhatsAppTemplate|array|string|Closure|null $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Set custom sample context for variable resolution.
     *
     * @param  array<string, mixed>|Closure  $context
     */
    public function sampleContext(array|Closure $context): static
    {
        $this->sampleContext = $context;

        return $this;
    }

    /**
     * Get the resolved WhatsAppTemplate model instance.
     */
    public function getTemplateModel(): ?WhatsAppTemplate
    {
        $template = $this->evaluate($this->template);

        if ($template instanceof WhatsAppTemplate) {
            return $template;
        }

        if (is_numeric($template)) {
            return WhatsAppTemplate::query()->find((int) $template);
        }

        if (is_string($template) && filled($template)) {
            return WhatsAppTemplate::query()
                ->where('name', $template)
                ->orWhere('id', $template)
                ->first();
        }

        return null;
    }

    /**
     * Get preview data array passed directly to the Blade component.
     *
     * @return array<string, mixed>
     */
    public function getPreviewData(): array
    {
        $template = $this->getTemplateModel();

        if (! $template) {
            $raw = $this->evaluate($this->template);
            if (is_array($raw) && filled($raw)) {
                /** @var array<string, mixed> $rawArray */
                $rawArray = $raw;

                return $this->formatArrayPreviewData($rawArray);
            }

            return [
                'has_template' => false,
            ];
        }

        /** @var WhatsAppVariableResolver $resolver */
        $resolver = resolve(WhatsAppVariableResolver::class);

        /** @var WhatsAppTemplateService $templateService */
        $templateService = resolve(WhatsAppTemplateService::class);

        $evalContext = $this->evaluate($this->sampleContext);
        /** @var array<string, mixed> $customContext */
        $customContext = is_array($evalContext) ? $evalContext : [];

        // Resolve template variables with sample or context data
        $resolvedVariables = $resolver->resolve($template, $customContext);

        // Format message body with resolved variable values
        $body = $template->body ?? '';
        $resolvedBody = $body;
        $highlightedBody = e($body);

        if (filled($resolvedVariables)) {
            $search = [];
            $replace = [];
            $highlightReplace = [];

            foreach ($resolvedVariables as $key => $val) {
                $search[] = "{{{$key}}}";
                $replace[] = $val;

                // Create HTML highlighted version of variable replacements for preview
                $safeVal = e($val);
                $highlightReplace[] = "<span class=\"font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-100/80 dark:bg-emerald-950/80 px-1 py-0.5 rounded border border-emerald-300/60 dark:border-emerald-700/50\">{$safeVal}</span>";
            }

            $resolvedBody = str_replace($search, $replace, $body);
            $highlightedBody = str_replace($search, $highlightReplace, e($body));
        }

        $placeholders = $template->getVariablePlaceholders();
        $variableMappings = [];
        $dataSources = WhatsAppVariableResolver::getAvailableDataSources();

        foreach ($placeholders as $key => $varData) {
            $source = is_array($varData) ? ($varData['source'] ?? 'custom') : 'custom';
            $isAuto = $source !== 'custom';
            $label = is_array($varData) ? ($varData['placeholder'] ?? "Variable {$key}") : (is_scalar($varData) ? (string) $varData : "Variable {$key}");
            $example = is_array($varData) ? ($varData['example'] ?? '') : '';

            $srcKey = is_scalar($source) ? (string) $source : 'custom';
            $srcMeta = $dataSources[$srcKey] ?? null;
            $srcLabel = $srcMeta['label'] ?? $srcKey;
            $srcGroup = $srcMeta['group'] ?? 'Custom';

            $keyStr = $key;
            $exStr = is_scalar($example) ? (string) $example : '';
            $resVal = $resolvedVariables[$keyStr] ?? $exStr;

            $variableMappings[] = [
                'key' => $keyStr,
                'label' => is_scalar($label) ? (string) $label : "Variable {$keyStr}",
                'source' => $source,
                'source_label' => $srcLabel,
                'source_group' => $srcGroup,
                'is_auto' => $isAuto,
                'example' => $exStr,
                'resolved_value' => $resVal,
            ];
        }

        $category = $template->category;
        $status = $template->status;

        return [
            'has_template' => true,
            'id' => $template->id,
            'name' => $template->name,
            'display_name' => $template->display_name ?: $template->name,
            'body' => $body,
            'formatted_body' => $template->formatted_body,
            'resolved_body' => $resolvedBody,
            'highlighted_body' => $highlightedBody,
            'category_label' => $category?->getLabel() ?? 'General',
            'category_color' => $category?->getColor() ?? 'gray',
            'status_label' => $status?->getLabel() ?? 'Approved',
            'status_color' => $status?->getColor() ?? 'success',
            'variable_count' => $template->variable_count ?? count($placeholders),
            'variable_mappings' => $variableMappings,
            'has_custom_variables' => WhatsAppVariableResolver::hasCustomVariables($template),
            'is_valid_variables' => $templateService->validateVariables($template, $resolvedVariables),
            'meta_template_id' => $template->meta_template_id,
            'twilio_template_sid' => $template->twilio_template_sid,
        ];
    }

    /**
     * Fallback formatter when template is supplied as raw array.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function formatArrayPreviewData(array $raw): array
    {
        $body = isset($raw['body']) && is_scalar($raw['body']) ? (string) $raw['body'] : '';
        $formattedBody = isset($raw['formatted_body']) && is_scalar($raw['formatted_body']) ? (string) $raw['formatted_body'] : $body;
        $placeholders = is_array($raw['variable_placeholder'] ?? null) ? $raw['variable_placeholder'] : [];

        $variableMappings = [];
        $dataSources = WhatsAppVariableResolver::getAvailableDataSources();

        foreach ($placeholders as $key => $varData) {
            $source = is_array($varData) ? ($varData['source'] ?? 'custom') : 'custom';
            $isAuto = $source !== 'custom';
            $label = is_array($varData) ? ($varData['placeholder'] ?? "Variable {$key}") : (is_scalar($varData) ? (string) $varData : "Variable {$key}");
            $example = is_array($varData) ? ($varData['example'] ?? '') : '';

            $srcKey = is_scalar($source) ? (string) $source : 'custom';
            $srcMeta = $dataSources[$srcKey] ?? null;
            $srcLabel = $srcMeta['label'] ?? $srcKey;
            $srcGroup = $srcMeta['group'] ?? 'Custom';

            $keyStr = (string) $key;
            $exStr = is_scalar($example) ? (string) $example : '';

            $variableMappings[] = [
                'key' => $keyStr,
                'label' => is_scalar($label) ? (string) $label : "Variable {$keyStr}",
                'source' => $source,
                'source_label' => $srcLabel,
                'source_group' => $srcGroup,
                'is_auto' => $isAuto,
                'example' => $exStr,
                'resolved_value' => $exStr !== '' ? $exStr : "{{{$keyStr}}}",
            ];
        }

        $name = isset($raw['name']) && is_scalar($raw['name']) ? (string) $raw['name'] : 'Template';
        $displayName = isset($raw['display_name']) && is_scalar($raw['display_name']) ? (string) $raw['display_name'] : $name;

        return [
            'has_template' => true,
            'id' => $raw['id'] ?? null,
            'name' => $name,
            'display_name' => $displayName,
            'body' => $body,
            'formatted_body' => $formattedBody,
            'resolved_body' => $formattedBody,
            'highlighted_body' => e($formattedBody),
            'category_label' => isset($raw['category']) && is_scalar($raw['category']) ? ucfirst((string) $raw['category']) : 'General',
            'category_color' => 'info',
            'status_label' => isset($raw['status']) && is_scalar($raw['status']) ? ucfirst((string) $raw['status']) : 'Approved',
            'status_color' => 'success',
            'variable_count' => count($placeholders),
            'variable_mappings' => $variableMappings,
            'has_custom_variables' => false,
            'is_valid_variables' => true,
            'meta_template_id' => $raw['meta_template_id'] ?? null,
            'twilio_template_sid' => $raw['twilio_template_sid'] ?? null,
        ];
    }
}
