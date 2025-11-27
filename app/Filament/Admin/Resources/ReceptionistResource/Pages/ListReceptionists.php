<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
use Filament\Resources\Pages\ListRecords;

class ListReceptionists extends ListRecords
{
    protected static string $resource = ReceptionistResource::class;

    protected function getHeaderActions(): array
    {
        return ReceptionistResource::getCommonHeaderActions();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->getUser()->id},.ImportCompleted" => '$refresh',
        ];
    }

    public function getUser(): \App\Models\User
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user;
    }
}
