<?php

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorReport extends EditRecord
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('copyMarkdown')
                ->label('Copy as Markdown')
                ->icon('heroicon-o-clipboard-document')
                ->action(function ($livewire) {
                    $record = $this->getRecord();
                    // Use json_encode to safely escape the string for JavaScript
                    $jsString = json_encode($record->toMarkdown());
                    
                    $livewire->js("
                        window.navigator.clipboard.writeText({$jsString});
                        new FilamentNotification()
                            .title('Copied to clipboard')
                            .success()
                            .send();
                    ");
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
