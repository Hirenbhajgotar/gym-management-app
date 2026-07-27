<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use App\Filament\Admin\Pages\WhatsAppSetup;

class StartSetupAction
{
    public function __construct(public WhatsAppSetup $page) {}

    public function __invoke(): void
    {
        if ($this->page->whatsappAccount) {
            // Redirect to force a full page load so the Filament Wizard
            // component mounts properly. Swapping the form schema from
            // ErrorSchema → SetupWizardSchema via Livewire alone causes
            // the wizard to render with ?step=undefined and empty content.
            $step = $this->page->determineSetupStep();

            $this->page->redirect(
                route('filament.admin.pages.whats-app-setup', [
                    'tenant' => $this->page->getTenantSlug(),
                    'setup_step' => $step,
                ])
            );
        } else {
            $this->page->viewState = 'setup';
            $this->page->setupStep = 1;
        }
    }
}
