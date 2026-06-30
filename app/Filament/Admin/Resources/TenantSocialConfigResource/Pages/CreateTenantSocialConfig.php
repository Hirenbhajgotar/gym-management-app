<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\Pages;

use App\Filament\Admin\Resources\TenantSocialConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantSocialConfig extends CreateRecord
{
    protected static string $resource = TenantSocialConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return TenantSocialConfigResource::getUrl('index');
    }
}
