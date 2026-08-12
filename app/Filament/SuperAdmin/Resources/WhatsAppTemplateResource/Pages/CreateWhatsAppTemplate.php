<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use App\Services\Support\TemplateVariableHelper;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppTemplate extends CreateRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $templateBody = is_string($data['body']) ? $data['body'] : '';

        $data['is_default'] = true;
        $data['gym_id'] = null;
        $data['variable_count'] = preg_match_all('/\{\{\d+\}\}/', $templateBody);
        $data['variables'] = TemplateVariableHelper::extractVariables($templateBody);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $templateBody = is_string($data['body']) ? $data['body'] : '';

        $data['variable_count'] = preg_match_all('/\{\{\d+\}\}/', $templateBody);
        $data['variables'] = TemplateVariableHelper::extractVariables($templateBody);

        return $data;
    }
}
