<?php

namespace App\Livewire;

use Livewire\Component;

class Toast extends Component
{
    public $error;

    public $success;

    public $showError = false;

    public $showSuccess = false;

    public function mount()
    {
        $this->error = session('error');
        $this->success = session('success');

        if ($this->error) {
            $this->showError = true;
        }

        if ($this->success) {
            $this->showSuccess = true;
        }
    }

    public function render()
    {
        return view('livewire.toast');
    }
}
