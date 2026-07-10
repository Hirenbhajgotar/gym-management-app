<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Toast extends Component
{
    // `error` and `success` are only ever written server-side (session flash on
    // mount, or dispatched events). They must never be settable via client
    // `updates`, which is how a malformed/array payload reached htmlspecialchars().
    #[Locked]
    public ?string $error = null;

    #[Locked]
    public ?string $success = null;

    public bool $showError = false;

    public bool $showSuccess = false;

    public function mount(): void
    {
        // Check for session flash messages on mount
        $this->error = is_string(session('error')) ? session('error') : null;
        $this->success = is_string(session('success')) ? session('success') : null;

        if ($this->error !== null) {
            $this->showError = true;
        }

        if ($this->success !== null) {
            $this->showSuccess = true;
        }
    }

    #[On('show-toast')]
    public function showToast(string $type, mixed $message): void
    {
        $message = is_string($message) ? $message : '';

        if ($type === 'success') {
            $this->success = $message;
            $this->showSuccess = true;
        } elseif ($type === 'error') {
            $this->error = $message;
            $this->showError = true;
        }
    }

    #[On('show-success')]
    public function showSuccess(mixed $message): void
    {
        $this->success = is_string($message) ? $message : '';
        $this->showSuccess = true;
    }

    #[On('show-error')]
    public function showError(mixed $message): void
    {
        $this->error = is_string($message) ? $message : '';
        $this->showError = true;
    }

    public function render()
    {
        return view('livewire.toast');
    }
}
