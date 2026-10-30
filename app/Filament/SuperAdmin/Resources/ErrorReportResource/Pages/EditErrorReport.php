<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Models\ErrorReport;
use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditErrorReport extends EditRecord
{
    protected static string $resource = ErrorReportResource::class;

    /**
     * Hydrate only the fields the edit form can actually write.
     *
     * Filament passes `attributesToArray()` to `Form::fill()`, and the form
     * state lives in the public Livewire `$data` property (and therefore in
     * `wire:snapshot`). Hydrating the whole model would serialize the raw
     * context, markdown, stack trace, URLs, and operator description even
     * though the UI renders redacted Placeholders for them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'status' => $data['status'] ?? null,
            'priority' => $data['priority'] ?? null,
            'error_type' => $data['error_type'] ?? null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyMarkdown')
                ->label('Copy as Markdown')
                ->icon('heroicon-o-clipboard-document')
                ->action(function ($livewire) {
                    /** @var ErrorReport $record */
                    $record = $this->getRecord();

                    $livewire->js(ErrorReportResource::copyToClipboardScript($record->toRedactedMarkdown()));
                }),
            DeleteAction::make(),
        ];
    }
}
