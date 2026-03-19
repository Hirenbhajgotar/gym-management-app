<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * A reusable OTP (One-Time Password) input field for Filament forms.
 *
 * Wraps Flux UI's `<flux:otp>` component and integrates it as a first-class
 * Filament form field, supporting all Filament lifecycle hooks such as
 * `afterStateUpdated`, validation, and reactive state.
 *
 * Usage:
 * ```php
 * OtpInput::make('otp_code')
 *     ->label('Verification Code')
 *     ->length(6)
 *     ->numericMode()        // or ->alphanumericMode() / ->alphaMode()
 *     ->private()
 *     ->required()
 *     ->afterStateUpdated(fn (string $state) => ...),
 * ```
 */
class OtpInput extends Field
{
    protected string $view = 'forms.components.otp-input';

    /** Number of OTP digit fields to render. */
    protected int|Closure $length = 6;

    /**
     * Input mode passed to flux:otp.
     * Options: 'numeric' (default), 'alphanumeric', 'alpha'.
     */
    protected string|Closure $mode = 'numeric';

    /** Whether to mask the input values (password-like). */
    protected bool|Closure $isPrivate = false;

    /**
     * Whether to auto-submit the form when all fields are filled.
     * Options: false (default), 'auto'.
     */
    protected bool|Closure $autoSubmit = false;

    /**
     * Set the number of OTP digit fields.
     */
    public function length(int|Closure $length): static
    {
        $this->length = $length;

        return $this;
    }

    /**
     * Set the input mode.
     *
     * @param  'numeric'|'alphanumeric'|'alpha'|Closure  $mode
     */
    public function mode(string|Closure $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Mask all input values (renders as password fields).
     */
    public function private(bool|Closure $condition = true): static
    {
        $this->isPrivate = $condition;

        return $this;
    }

    /**
     * Allow only numeric characters (default behaviour).
     */
    public function numericMode(): static
    {
        $this->mode = 'numeric';

        return $this;
    }

    /**
     * Allow alphanumeric characters.
     */
    public function alphanumericMode(): static
    {
        $this->mode = 'alphanumeric';

        return $this;
    }

    /**
     * Allow only alphabetic characters.
     */
    public function alphaMode(): static
    {
        $this->mode = 'alpha';

        return $this;
    }

    /**
     * Automatically submit the form when all OTP fields are filled.
     */
    public function autoSubmit(bool|Closure $condition = true): static
    {
        $this->autoSubmit = $condition;

        return $this;
    }

    /**
     * Resolve and return the configured OTP length.
     */
    public function getLength(): int
    {
        return (int) $this->evaluate($this->length);
    }

    /**
     * Resolve and return the configured input mode.
     */
    public function getMode(): string
    {
        return (string) $this->evaluate($this->mode);
    }

    /**
     * Determine whether the OTP inputs should be masked.
     */
    public function isPrivate(): bool
    {
        return (bool) $this->evaluate($this->isPrivate);
    }

    /**
     * Determine whether the form should auto-submit on completion.
     */
    public function getAutoSubmit(): bool
    {
        return (bool) $this->evaluate($this->autoSubmit);
    }
}
