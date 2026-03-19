<?php

namespace App\Filament\Components;

use Livewire\Component;

class FeedbackTrigger extends Component
{
    public function triggerFeedback()
    {
        $this->dispatch('open-feedback-modal');
    }

    public function render()
    {
        if (! auth()->check() || request()->routeIs([
            'filament.*.auth.*',
            'login',
            'password.*',
            'register',
            'gym.register',
            'filament.*.tenant.*',
        ])) {
            return <<<'HTML'
<div></div>
HTML;
        }

        return view('filament.components.feedback-trigger');
    }
}
