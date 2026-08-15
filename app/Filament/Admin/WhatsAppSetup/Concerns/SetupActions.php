<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Filament\Admin\WhatsAppSetup\Actions\ClaimWelcomeCreditsAction;
use App\Filament\Admin\WhatsAppSetup\Actions\ConnectExistingWabaAction;
use App\Filament\Admin\WhatsAppSetup\Actions\PurchaseNumberAction;
use App\Filament\Admin\WhatsAppSetup\Actions\RegisterWabaPhoneAction;
use App\Filament\Admin\WhatsAppSetup\Actions\SearchNumbersAction;
use App\Filament\Admin\WhatsAppSetup\Actions\StartEmbeddedSignupAction;
use App\Filament\Admin\WhatsAppSetup\Actions\StartSetupAction;

trait SetupActions
{
    public function searchNumbers(): void
    {
        (new SearchNumbersAction($this))();
    }

    /**
     * Livewire bridge method for the search-results.blade.php view.
     * wire:click requires the method to exist directly on the Page component.
     */
    public function selectNumber(string $phoneNumber): void
    {
        $this->selectedNumber = $phoneNumber;
        (new PurchaseNumberAction($this))();
    }

    /**
     * Claim welcome credits action.
     */
    public function claimWelcomeCreditsAction(): void
    {
        (new ClaimWelcomeCreditsAction($this))();
    }

    /**
     * Start whatsapp setup action.
     */
    public function startSetupAction(): void
    {
        (new StartSetupAction($this))();
    }

    public function startEmbeddedSignupAction(): void
    {
        (new StartEmbeddedSignupAction($this))();
    }

    public function connectExistingWabaAction(): void
    {
        (new ConnectExistingWabaAction($this))();
    }

    public function registerWabaPhoneAction(): void
    {
        (new RegisterWabaPhoneAction($this))();
    }
}
