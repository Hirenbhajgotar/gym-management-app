<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Actions;

class StartSetupAction
{
    public function __construct(public $page) {}

    public function __invoke(): void
    {
        if ($this->page->whatsappAccount) {
            $this->page->viewState = 'setup';
            $this->page->setupStep = $this->page->determineSetupStep();
        } else {
            $this->page->viewState = 'setup';
            $this->page->setupStep = 1;
        }
    }
}
