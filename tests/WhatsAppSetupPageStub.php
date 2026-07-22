<?php

declare(strict_types=1);

namespace Tests;

use App\Filament\Admin\Pages\WhatsAppSetup;

/**
 * Minimal stand-in for the WhatsAppSetup page object used by the schema and
 * step builders. Extends the real page so PHPStan (and PHP's runtime type
 * checks) accept it wherever `WhatsAppSetup` is type-hinted, while skipping
 * the Livewire/Filament bootstrapping that the real constructor performs.
 *
 * All public setup-state properties (`viewState`, `data`, `selectedProvider`,
 * etc.) are inherited from the `HasSetupState` trait — no redeclaration is
 * needed. Tests that need a specific value (e.g. `selectedProvider`) set it
 * after construction.
 */
class WhatsAppSetupPageStub extends WhatsAppSetup
{
    /**
     * Skip the parent (Livewire/Filament) constructor chain and set
     * test-friendly defaults for the setup-state properties.
     *
     * Parent constructor is intentionally NOT called: the stub only needs
     * the public properties from HasSetupState and related traits, not the
     * full Livewire lifecycle.
     */
    public function __construct()
    {
        $this->viewState = 'setup';
        $this->data = ['provider_type' => 'waba'];
    }

    /**
     * Override to provide a fixed tenant slug in tests where Filament
     * tenant context is not available.
     */
    public function getTenantSlug(): string
    {
        return 'test-gym';
    }
}
