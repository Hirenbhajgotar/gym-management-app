<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use Filament\Pages\Page;

class FeedbackPage extends Page implements HasFilamentPermissions
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Give Feedback';

    protected static ?int $navigationSort = 999; // Put it at the bottom

    protected static ?string $navigationGroup = 'Support';

    protected static string $view = 'filament.pages.feedback-page';

    protected static bool $shouldRegisterNavigation = true;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['page'],
            DefaultRole::RECEPTIONIST->value => ['page'],
            DefaultRole::TRAINER->value => ['page'],
        ];
    }

    public function mount(): void
    {
        // Automatically open the feedback modal when this page is accessed
        $this->dispatch('open-feedback-modal');
    }
}
