<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class EditWhatsAppTemplate extends EditRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Resubmit for Approval (only for rejected templates)
            Action::make('resubmit')
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

                    try {
                        $provider = WhatsAppTemplateResource::getTemplateProvider($this->record->gym_id);
                        $result = $provider->submitTemplate($this->record->fresh());

                        if ($result->success) {
                            Notification::make()
                                ->success()
                                ->title('Template Resubmitted!')
                                ->body('Your updated template has been resubmitted for Meta approval.')
                                ->send();
                        } else {
                            Log::info('Template resubmission failed', ['template_id' => $this->record->id, 'error' => $result->error]);
                            Notification::make()
                                ->danger()
                                ->title('Resubmission Failed')
                                ->body('Your updated template has not been submitted for Meta approval.')
                                ->send();
                        }
                    } catch (InvalidArgumentException|RuntimeException $e) {
                        Log::error('Template resubmission failed with exception', ['template_id' => $this->record->id, 'error' => $e->getMessage()]);
                        Notification::make()
                            ->danger()
                            ->title('Resubmission Failed')
                            ->body('Template resubmission failed. Please try again later.')
                            ->send();
                    }
                }),

            // Submit for Approval (only for draft templates)
            Action::make('submit')
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

                    try {
                        $provider = WhatsAppTemplateResource::getTemplateProvider($this->record->gym_id);
                        $result = $provider->submitTemplate($this->record->fresh());

                        if ($result->success) {
                            Notification::make()
                                ->success()
                                ->title('Template Submitted!')
                                ->body('Your template has been submitted for Meta approval.')
                                ->send();
                        } else {
                            Log::info('Template submission failed', ['template_id' => $this->record->id, 'error' => $result->error]);
                            Notification::make()
                                ->danger()
                                ->title('Submission Failed')
                                ->body('Your template has not been submitted for Meta approval.')
                                ->send();
                        }
                    } catch (InvalidArgumentException|RuntimeException $e) {
                        Log::error('Template submission failed with exception', ['template_id' => $this->record->id, 'error' => $e->getMessage()]);
                        Notification::make()
                            ->danger()
                            ->title('Submission Failed')
                            ->body('Template submission failed. Please try again later.')
                            ->send();
                    }
                }),

            DeleteAction::make()
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
