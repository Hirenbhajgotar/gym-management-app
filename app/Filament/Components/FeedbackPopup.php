<?php

namespace App\Filament\Components;

use App\Models\Feedback;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Mokhosh\FilamentRating\Components\Rating;

class FeedbackPopup extends Component implements HasForms
{
    use WithFileUploads, InteractsWithForms;

    public bool $showModal = false;
    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();

        // Check if feedback popup should be shown
        $this->checkAndShowPopup();
    }

    #[On('open-feedback-modal')]
    public function openModal()
    {
        $this->showModal = true;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Rating::make('rating')
                    ->size('lg')
                    ->required()
                    ->label('How would you rate your experience?'),

                Textarea::make('feedback')
                    ->label('Tell us more about your experience')
                    ->placeholder('What did you like? What could we improve?')
                    ->required()
                    ->minLength(10)
                    ->rows(4),

                FileUpload::make('files')
                    ->label('Upload an attachment (optional)')
                    ->acceptedFileTypes(['image/*', 'video/*', 'application/pdf', '.doc', '.docx', '.txt'])
                    ->maxSize(10240) // 10MB
                    ->disk('public')
                    ->multiple(),
            ])
            ->statePath('data');
    }

    public function submitFeedback()
    {
        $userId = Auth::id();
        $data = $this->form->getState();

        $data['user_id'] = $userId;
        $data['submitted_at'] = now();

        // Store feedback in database (you'll need to create this model)
        Feedback::create($data);

        // Mark popup as shown for today
        $cacheKey = "feedback_popup_shown_{$userId}_" . now()->format('Y-m-d');
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->showModal = false;

        Notification::make()
            ->title('Thank you for your feedback!')
            ->success()
            ->send();

        // $this->reset(['rating', 'feedback', 'email']);
        $this->form->fill();
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
