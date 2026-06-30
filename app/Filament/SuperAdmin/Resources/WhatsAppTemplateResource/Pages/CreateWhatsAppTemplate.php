<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppTemplate extends CreateRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_default'] = true;
        $data['gym_id'] = null;
        $data['variable_count'] = preg_match_all('/\{\{\d+\}\}/', $data['body'] ?? '');
        $data['variables'] = $this->extractVariables($data['body'] ?? '');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['variable_count'] = preg_match_all('/\{\{\d+\}\}/', $data['body'] ?? '');
        $data['variables'] = $this->extractVariables($data['body'] ?? '');

        return $data;
    }

    protected function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        return array_unique($matches[0]);
    }
}
