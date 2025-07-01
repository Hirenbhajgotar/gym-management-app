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
        return view('filament.components.feedback-trigger');
    }
}
