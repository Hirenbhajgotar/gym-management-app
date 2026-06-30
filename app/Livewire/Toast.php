<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Toast extends Component
{
    public $error;

    public $success;

    public $showError = false;

    public $showSuccess = false;

    public function mount()
    {
        // Check for session flash messages on mount
        $this->error = session('error');
        $this->success = session('success');

        if ($this->error) {
            $this->showError = true;
        }

        if ($this->success) {
            $this->showSuccess = true;
        }
    }

    #[On('show-toast')]
    public function showToast($type, $message)
    {
        if ($type === 'success') {
            $this->success = $message;
            $this->showSuccess = true;
        } elseif ($type === 'error') {
            $this->error = $message;
            $this->showError = true;
        }
    }

    #[On('show-success')]
    public function showSuccess($message)
    {
        $this->success = $message;
        $this->showSuccess = true;
    }

    #[On('show-error')]
    public function showError($message)
    {
        $this->error = $message;
        $this->showError = true;
    }

    public function render()
    {
        return view('livewire.toast');
    }
}
