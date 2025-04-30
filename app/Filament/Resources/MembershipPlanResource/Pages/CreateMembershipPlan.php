<?php

namespace App\Filament\Resources\MembershipPlanResource\Pages;

use App\Filament\Resources\MembershipPlanResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipPlan extends CreateRecord
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Disable default success notification
    }

    protected function getRedirectUrl(): string
    {
        return MembershipPlanResource::getUrl('index'); // Redirect to index page after create
    }
}
