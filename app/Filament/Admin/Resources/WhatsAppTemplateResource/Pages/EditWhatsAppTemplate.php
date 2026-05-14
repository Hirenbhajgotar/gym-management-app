<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use App\Services\WhatsAppTemplateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

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
                        Log::info('Template resubmission failed', ['error' => $result['error']]);
                        Notification::make()
                            ->danger()
                            ->title('Resubmission Failed')
                            ->body('Your updated template has not been submitted for Meta approval.')
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
                        Log::info('Template submission failed', ['error' => $result['error']]);
                        Notification::make()
                            ->danger()
                            ->title('Submission Failed')
                            ->body('Your template has not been submitted for Meta approval.')
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

    /**
     * Display a success notification after template update.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Template Updated')
            ->body('The template has been successfully updated.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
