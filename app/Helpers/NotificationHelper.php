<?php

namespace App\Helpers;

use App\Filament\Actions\ReportErrorAction;
use App\Services\ErrorReportService;
use App\Services\ExceptionStorageService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Throwable;

class NotificationHelper
{
    public static function errorWithReport(string $title, string $message, ?Throwable $exception = null): Notification
    {
        $exceptionId = null;

        if ($exception) {
            $exceptionStorage = app(ExceptionStorageService::class);
            $exceptionId = $exceptionStorage->store($exception);
        }

        return Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->persistent()
            ->actions([
                Action::make('report')
                    ->label('Report Error')
                    ->icon('heroicon-o-bug-ant')
                    ->button()
                    ->color('danger')
                    ->dispatch('open-error-report-modal', ['data' => [
                        'errorMessage' => $message,
                        'exceptionId' => $exceptionId,
                    ]])
                    ->close(),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->close(),
            ]);
    }

    public static function unexpectedError(string $operation, ?Throwable $exception = null): Notification
    {
        $message = "An unexpected error occurred while {$operation}. Please try again or report this issue.";

        return self::errorWithReport(
            'Unexpected Error',
            $message,
            $exception
        );
    }
}
