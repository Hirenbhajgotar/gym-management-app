<?php

namespace App\Filament\Components;

use App\Models\Feedback;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class FeedbackPopup extends Component
{
    public bool $showModal = false;
    public $rating = '';
    public $feedback = '';
    public $email = '';

    public function mount()
    {
        // Check if feedback popup should be shown
        $this->checkAndShowPopup();
    }

    protected function checkAndShowPopup()
    {
        $userId = Auth::id();
        $cacheKey = "feedback_popup_shown_{$userId}_" . now()->format('Y-m-d');

        // Check if popup was already shown today
        if (!Cache::has($cacheKey)) {
            $this->showModal = true;
        }
    }

    public function submitFeedback()
    {
        $this->validate([
            'rating' => 'required|in:1,2,3,4,5',
            'feedback' => 'required|min:10|max:500',
            'email' => 'nullable|email'
        ]);

        // Store feedback in database (you'll need to create this model)
        Feedback::create([
            'user_id' => Auth::id(),
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'email' => $this->email,
            'submitted_at' => now(),
        ]);

        // Mark popup as shown for today
        $userId = Auth::id();
        $cacheKey = "feedback_popup_shown_{$userId}_" . now()->format('Y-m-d');
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->showModal = false;

        Notification::make()
            ->title('Thank you for your feedback!')
            ->success()
            ->send();

        $this->reset(['rating', 'feedback', 'email']);
    }

    public function closeFeedback()
    {
        // Mark popup as shown for today even if closed without submitting
        $userId = Auth::id();
        $cacheKey = "feedback_popup_shown_{$userId}_" . now()->format('Y-m-d');
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->showModal = false;
    }

    public function render()
    {
        return view('filament.components.feedback-popup');
    }
}
