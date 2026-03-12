<?php

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use App\Services\WhatsAppTemplateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppTemplate extends EditRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Resubmit for Approval (only for rejected templates)
            Actions\Action::make('resubmit')
                ->label('Resubmit for Approval')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Resubmit Template')
                ->modalDescription('This will save your changes and resubmit the template to Meta for approval.')
                ->visible(fn () => $this->record->status === WhatsAppTemplateStatusEnum::REJECTED)
                ->action(function () {
                    // Save the form first
                    $this->save();

                    // Reset rejection and resubmit
                    $this->record->update([
                        'status' => WhatsAppTemplateStatusEnum::DRAFT,
                        'rejection_reason' => null,
                    ]);

                    $service = app(WhatsAppTemplateService::class);
                    $result = $service->submitForVerification($this->record);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Template Resubmitted!')
                            ->body('Your updated template has been resubmitted for Meta approval.')
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Resubmission Failed')
                            ->body($result['error'] ?? 'Unknown error occurred.')
                            ->send();
                    }
                }),

            // Submit for Approval (only for draft templates)
            Actions\Action::make('submit')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Submit Template for Meta Approval')
                ->modalDescription('Your template will be submitted to Meta (WhatsApp) for review.')
                ->visible(fn () => $this->record->status === WhatsAppTemplateStatusEnum::DRAFT)
                ->action(function () {
                    // Save changes first
                    $this->save();

                    $service = app(WhatsAppTemplateService::class);
                    $result = $service->submitForVerification($this->record);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Template Submitted!')
                            ->body('Your template has been submitted for Meta approval.')
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Submission Failed')
                            ->body($result['error'] ?? 'Unknown error occurred.')
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->record->is_default && ! $this->record->isApproved()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Normalize: fill null/missing values, build variables array
        return WhatsAppTemplateResource::normalizeTemplateData($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
