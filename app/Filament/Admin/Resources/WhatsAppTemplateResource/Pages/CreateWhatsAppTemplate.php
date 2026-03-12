<?php

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppTemplate extends CreateRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        $data['gym_id'] = $tenant?->id;
        $data['status'] = WhatsAppTemplateStatusEnum::DRAFT;
        $data['whatsapp_account_id'] = $tenant?->whatsappAccount?->id;

        // Handle default template selection — merge variable_placeholder from default
        if (! empty($data['_default_template'])) {
            $templates = WhatsAppTemplateResource::getDefaultTemplatesForForm();
            if (isset($templates[$data['_default_template']])) {
                $template = $templates[$data['_default_template']];
                $data['name'] = $template['name'];
                $data['display_name'] = $template['display_name'] ?? ucwords(str_replace('_', ' ', $template['name']));
                $data['body'] = $template['body'];
                $data['category'] = $template['category'] ?? 'utility';

                // Merge default template's variable_placeholder as a base,
                // then let the form's values override where present
                if (! empty($template['variable_placeholder'])) {
                    $formPlaceholders = $data['variable_placeholder'] ?? [];
                    $merged = $template['variable_placeholder'];

                    foreach ($formPlaceholders as $num => $formValues) {
                        foreach ($formValues as $key => $value) {
                            if (! empty($value)) {
                                $merged[$num][$key] = $value;
                            }
                        }
                    }

                    $data['variable_placeholder'] = $merged;
                }
            }
        }

        // Remove the temporary field
        unset($data['_default_template']);

        // Normalize: fill null/missing values, build variables array
        $data = WhatsAppTemplateResource::normalizeTemplateData($data);

        return $data;
    }

    /**
     * Display a success notification after template creation.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Template Created!')
            ->body('Your template has been saved as a draft. Submit it for Meta approval when ready.')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
