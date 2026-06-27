<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MembershipPlanResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\MembershipPlanResource;
use Filament\Resources\Pages\EditRecord;

class EditMembershipPlan extends EditRecord
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotification(null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return MembershipPlanResource::getUrl('index'); // Redirect to index page after create
    }
}
