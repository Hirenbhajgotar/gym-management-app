<?php

declare(strict_types=1);

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

/**
 * TemplatePicker
 *
 * A custom Filament field component for selecting a WhatsApp template
 * from a list of template models/arrays inside a horizontal carousel.
 * Operates as a single Field instance named 'template_id' to prevent duplicate field wrapper error messages.
 */
class TemplatePicker extends Field
{
    protected string $view = 'forms.components.template-picker';

    /** @var array<int|string, mixed>|Collection<*, *>|Closure */
    protected array|Collection|Closure $templates = [];

    protected int|Closure $itemsPerPage = 2;

    protected bool|Closure $isSearchable = false;

    protected string|Closure|null $manageUrl = null;

    protected string|Closure|null $manageLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /**
     * Set the list of template models, collections, or arrays.
     *
     * @param  array<int|string, mixed>|Collection<*, *>|Closure  $templates
     */
    public function templates(array|Collection|Closure $templates): static
    {
        $this->templates = $templates;

        return $this;
    }

    /**
     * Alias for templates().
     *
     * @param  array<int|string, mixed>|Collection<*, *>|Closure  $options
     */
    public function options(array|Collection|Closure $options): static
    {
        return $this->templates($options);
    }

    /**
     * Set the number of items per carousel page.
     */
    public function itemsPerPage(int|Closure $count): static
    {
        $this->itemsPerPage = $count;

        return $this;
    }

    /**
     * Enable search functionality.
     */
    public function searchable(bool|Closure $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    /**
     * Check if picker is searchable.
     */
    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->isSearchable);
    }

    /**
     * Set quick management link URL (e.g., "+ Create Template").
     */
    public function manageUrl(string|Closure|null $url, string|Closure|null $label = 'Manage Templates'): static
    {
        $this->manageUrl = $url;
        if ($label !== null) {
            $this->manageLabel = $label;
        }

        return $this;
    }

    /**
     * Get manage URL.
     */
    public function getManageUrl(): ?string
    {
        $url = $this->evaluate($this->manageUrl);

        return is_string($url) && filled($url) ? $url : null;
    }

    /**
     * Get manage label.
     */
    public function getManageLabel(): ?string
    {
        $label = $this->evaluate($this->manageLabel);

        return is_string($label) && filled($label) ? $label : null;
    }

    /**
     * Get the resolved templates array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        $evaluated = $this->evaluate($this->templates);

        if ($evaluated instanceof Collection) {
            $evaluated = $evaluated->toArray();
        }

        /** @var array<int|string, mixed> $items */
        $items = is_array($evaluated) ? $evaluated : [];

        /** @var array<int, array<string, mixed>> $result */
        $result = array_values(array_map(function ($item) {
            if (is_array($item)) {
                $id = $item['id'] ?? '';
                $title = $item['title'] ?? $item['display_name'] ?? $item['name'] ?? '';
                $body = $item['body'] ?? $item['content'] ?? '';
                $badge = $item['badge'] ?? $item['category'] ?? '';

                return array_merge([
                    'id' => is_scalar($id) ? (string) $id : '',
                    'title' => is_scalar($title) ? (string) $title : '',
                    'body' => is_scalar($body) ? (string) $body : '',
                    'badge' => is_scalar($badge) ? (string) $badge : '',
                ], $item);
            }

            return $item;
        }, $items));

        return $result;
    }

    /**
     * Get items per page count.
     */
    public function getItemsPerPage(): int
    {
        $val = $this->evaluate($this->itemsPerPage);

        return is_numeric($val) ? (int) $val : 2;
    }

    /**
     * Get resolved visible items mapping for responsiveness.
     *
     * @return array<string, int>
     */
    public function getVisibleItems(): array
    {
        $count = $this->getItemsPerPage();

        return [
            'default' => 1,
            'md' => $count,
            'lg' => $count,
        ];
    }
}
