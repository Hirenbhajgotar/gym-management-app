<?php

declare(strict_types=1);

namespace App\Filament\Components;

use App\Helpers\FeedbackHelper;
use App\Models\Feedback;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mokhosh\FilamentRating\Components\Rating;

/**
 * @property Form $form
 */
class FeedbackPopup extends Component implements HasForms
{
    use InteractsWithForms, WithFileUploads;

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
        if (! Auth::check()) {
            return;
        }

        $cacheKey = FeedbackHelper::getFeedbackCacheKey(Auth::id());

        // Check if popup was already shown this week
        if (! Cache::has($cacheKey)) {
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

        // Store feedback in database
        Feedback::query()->create($data);

        // Store the user ID in cache to prevent the feedback popup from displaying for a given number of days.
        // This prevents users from getting annoyed by the popup and allows us to only show it every 25 days.
        FeedbackHelper::storeFeedbackCache($userId, 25);

        $this->showModal = false;

        Notification::make()
            ->title('Thank you for your feedback!')
            ->success()
            ->send();

        $this->form->fill();
    }

    public function closeFeedback()
    {
        // Store the user ID in cache to prevent the feedback popup from displaying for a given number of days.
        // This prevents users from getting annoyed by the popup and allows us to only show it every 25 days.
        FeedbackHelper::storeFeedbackCache(Auth::id(), 25);

        $this->showModal = false;
    }

    public function render()
    {
        return view('filament.components.feedback-popup');
    }
}
