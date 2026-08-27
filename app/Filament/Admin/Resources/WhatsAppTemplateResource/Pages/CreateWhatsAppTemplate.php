<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppTemplate extends CreateRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();

        $data['gym_id'] = $tenant?->id;
        $data['status'] = WhatsAppTemplateStatusEnum::DRAFT;
        $data['whatsapp_account_id'] = $tenant?->whatsappAccount?->id;

        // Handle default template selection — merge variable_placeholder from default
        if (filled($data['_default_template'] ?? null)) {
            $rawKey = $data['_default_template'];
            $defaultKey = is_scalar($rawKey) ? (string) $rawKey : '';
            $templates = WhatsAppTemplateResource::getDefaultTemplatesForForm();
            if (isset($templates[$defaultKey])) {
                $template = $templates[$defaultKey];
                $data['name'] = $template['name'];
                $nameStr = is_scalar($template['name'] ?? null) ? (string) $template['name'] : '';
                $dispStr = is_scalar($template['display_name'] ?? null) ? (string) $template['display_name'] : ucwords(str_replace('_', ' ', $nameStr));
                $data['display_name'] = $dispStr;
                $data['body'] = $template['body'];
                $data['category'] = $template['category'] ?? TemplateCategoryEnum::UTILITY->value;

                // Merge default template's variable_placeholder as a base,
                // then let the form's values override where present
                if (filled($template['variable_placeholder'] ?? null)) {
                    /** @var array<string, mixed> $formPlaceholders */
                    $formPlaceholders = is_array($data['variable_placeholder'] ?? null) ? $data['variable_placeholder'] : [];
                    /** @var array<string, mixed> $merged */
                    $merged = is_array($template['variable_placeholder']) ? $template['variable_placeholder'] : [];

                    foreach ($formPlaceholders as $num => $formValues) {
                        if (is_array($formValues) && isset($formValues[0]) && is_array($formValues[0])) {
                            $formValues = $formValues[0];
                        }

                        if (is_array($formValues)) {
                            foreach ($formValues as $key => $value) {
                                if (is_string($key) && filled($value)) {
                                    $numKey = $num;
                                    if ($numKey !== '') {
                                        /** @var array<string, mixed> $numMerged */
                                        $numMerged = is_array($merged[$numKey] ?? null) ? $merged[$numKey] : [];
                                        $numMerged[$key] = $value;
                                        $merged[$numKey] = $numMerged;
                                    }
                                }
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
