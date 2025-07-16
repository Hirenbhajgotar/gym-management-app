<?php

namespace App\Filament\Actions;

use App\Services\ErrorReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconPosition;
use Throwable;

class ReportErrorAction
{
    public static function make(string $errorMessage, ?Throwable $exception = null): Action
    {
        return Action::make('reportError')
            ->label('Report Error')
            ->icon('heroicon-o-bug-ant')
            ->iconPosition(IconPosition::Before)
            ->size(ActionSize::Small)
            ->color('danger')
            ->form([
                TextInput::make('error_preview')
                    ->label('Error')
                    ->default($errorMessage)
                    ->disabled()
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('What were you trying to do?')
                    ->placeholder('Please describe what you were doing when this error occurred...')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) use ($errorMessage, $exception) {
                $errorReportService = app(ErrorReportService::class);

                if ($exception) {
                    $errorReportService->reportException($exception, $data['description'] ?? null);
                } else {
                    $errorReportService->reportError($errorMessage, $data['description'] ?? null);
                }

                Notification::make()
                    ->title('Error Reported')
                    ->body('Thank you for reporting this error. Our team will investigate it.')
                    ->success()
                    ->send();
            });
    }
}
