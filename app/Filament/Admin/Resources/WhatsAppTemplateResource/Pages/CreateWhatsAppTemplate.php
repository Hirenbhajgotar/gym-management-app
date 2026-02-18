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

        // Handle default template selection
        if (! empty($data['_default_template'])) {
            $templates = WhatsAppTemplateResource::getDefaultTemplatesForForm();
            if (isset($templates[$data['_default_template']])) {
                $template = $templates[$data['_default_template']];
                $data['name'] = $template['name'];
                $data['display_name'] = $template['display_name'] ?? ucwords(str_replace('_', ' ', $template['name']));
                $data['body'] = $template['body'];
                $data['category'] = $template['category'] ?? 'utility';
            }
        }

        // Calculate variable count from body
        preg_match_all('/\{\{\d+\}\}/', $data['body'] ?? '', $matches);
        $data['variable_count'] = count(array_unique($matches[0] ?? []));

        // Extract variables list
        $variables = [];
        foreach (array_unique($matches[0] ?? []) as $var) {
            $variables[] = $var;
        }
        $data['variables'] = $variables;

        // Remove the temporary field
        unset($data['_default_template']);

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
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
