<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FeedbackPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Give Feedback';

    protected static ?int $navigationSort = 999; // Put it at the bottom

    protected static ?string $navigationGroup = 'Support';

    protected static string $view = 'filament.pages.feedback-page';

    protected static bool $shouldRegisterNavigation = true;

    public function mount(): void
    {
        // Automatically open the feedback modal when this page is accessed
        $this->dispatch('open-feedback-modal');
    }
}
