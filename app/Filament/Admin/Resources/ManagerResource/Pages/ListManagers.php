<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Models\User;
use App\Filament\Admin\Resources\ManagerResource;
use Filament\Resources\Pages\ListRecords;

class ListManagers extends ListRecords
{
    protected static string $resource = ManagerResource::class;

    protected function getHeaderActions(): array
    {
        return ManagerResource::getCommonHeaderActions();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->getUser()->id},.ImportCompleted" => '$refresh',
        ];
    }

    public function getUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
