<?php

declare(strict_types=1);

namespace App\Forms\Components;

use App\Forms\Components\Concerns\HasColorPalette;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Throwable;

/**
 * TemplateCard
 *
 * A reusable, dynamic radio card selection component for Filament forms.
 * Functions as an interactive radio card with custom title, body content, badge, icon, and selection state.
 */
class TemplateCard extends Field
{
    use HasColorPalette;

    protected string $view = 'forms.components.template-card';

    protected mixed $cardValue = null;

    protected string|Closure|null $title = null;

    protected string|Closure|null $body = null;

    protected string|Closure|null $badge = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $color = 'emerald';

    protected bool|Closure|null $isSelected = null;

    protected bool|Closure $showErrorMessage = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    /**
     * Configure whether this card instance should render the validation error text message.
     */
    public function showErrorMessage(bool|Closure $condition = true): static
    {
        $this->showErrorMessage = $condition;

        return $this;
    }

    /**
     * Determine if this card instance should render the error message text.
     */
    public function shouldShowErrorMessage(): bool
    {
        return (bool) $this->evaluate($this->showErrorMessage);
    }

    /**
     * Set the radio choice value.
     */
    public function cardValue(mixed $value): static
    {
        $this->cardValue = $value;

        return $this;
    }

    /**
     * Set the card header title.
     */
    public function title(string|Closure|null $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the card body text or content snippet.
     */
    public function body(string|Closure|null $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set an optional badge label for the card.
     */
    public function badge(string|Closure|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Set an optional icon for the card header.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the card accent color theme (default: emerald).
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Explicitly set whether the card is selected.
     */
    public function selected(bool|Closure $condition = true): static
    {
        $this->isSelected = $condition;

        return $this;
    }

    /**
     * Get the card value.
     */
    public function getCardValue(): mixed
    {
        return $this->evaluate($this->cardValue);
    }

    /**
     * Get the card title.
     */
    public function getTitle(): ?string
    {
        $val = $this->evaluate($this->title);
        $raw = is_scalar($val) ? (string) $val : '';

        if (blank($raw)) {
            return null;
        }

        return Str::title(str_replace(['_', '-', ','], ' ', $raw));
    }

    /**
     * Get the card body content.
     */
    public function getBody(): ?string
    {
        $val = $this->evaluate($this->body);

        return is_scalar($val) && filled($val) ? (string) $val : null;
    }

    /**
     * Get the badge text.
     */
    public function getBadge(): ?string
    {
        $val = $this->evaluate($this->badge);

        return is_scalar($val) && filled($val) ? (string) $val : null;
    }

    /**
     * Get the card icon.
     */
    public function getIcon(): ?string
    {
        $val = $this->evaluate($this->icon);

        return is_scalar($val) && filled($val) ? (string) $val : null;
    }

    /**
     * Get the color scheme name.
     */
    public function getColor(): string
    {
        $val = $this->evaluate($this->color);

        return is_scalar($val) && filled($val) ? (string) $val : 'emerald';
    }

    /**
     * Determine if this card is currently selected.
     */
    public function isSelected(): bool
    {
        if ($this->isSelected !== null) {
            return (bool) $this->evaluate($this->isSelected);
        }

        $cardValue = $this->getCardValue();
        $state = $this->getState();

        if ($cardValue === null || $state === null) {
            return false;
        }

        $cVal = is_scalar($cardValue) ? (string) $cardValue : '';
        $sVal = is_scalar($state) ? (string) $state : '';

        return $cVal !== '' && $cVal === $sVal;
    }

    /**
     * Check if this component currently has a validation error in the error bag.
     */
    public function hasValidationError(): bool
    {
        $name = $this->getName();
        $statePath = null;

        try {
            $statePath = $this->getStatePath();
        } catch (Throwable) {
            // Container uninitialized in standalone unit tests
        }

        $errors = session('errors');

        if (! $errors instanceof ViewErrorBag) {
            return false;
        }

        return ($statePath && $errors->has($statePath))
            || $errors->has($name)
            || $errors->has("data.{$name}")
            || ($statePath && $errors->has("data.{$statePath}"));
    }

    /**
     * Get the first validation error message for this field.
     */
    public function getValidationError(): ?string
    {
        $name = $this->getName();
        $statePath = null;

        try {
            $statePath = $this->getStatePath();
        } catch (Throwable) {
            // Container uninitialized in standalone unit tests
        }

        $errors = session('errors');

        if (! $errors instanceof ViewErrorBag) {
            return null;
        }

        if ($statePath && $errors->has($statePath)) {
            /** @var string */
            return $errors->first($statePath);
        }

        if ($errors->has($name)) {
            /** @var string */
            return $errors->first($name);
        }

        if ($errors->has("data.{$name}")) {
            /** @var string */
            return $errors->first("data.{$name}");
        }

        if ($statePath && $errors->has("data.{$statePath}")) {
            /** @var string */
            return $errors->first("data.{$statePath}");
        }

        return null;
    }
}
