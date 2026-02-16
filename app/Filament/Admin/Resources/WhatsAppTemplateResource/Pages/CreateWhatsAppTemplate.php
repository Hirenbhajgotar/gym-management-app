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

        // Calculate variable count from body
        preg_match_all('/\{\{\d+\}\}/', $data['body'] ?? '', $matches);
        $data['variable_count'] = count(array_unique($matches[0] ?? []));

        // Extract variables list
        $variables = [];
        foreach (array_unique($matches[0] ?? []) as $var) {
            $variables[] = $var;
        }
        $data['variables'] = $variables;

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
