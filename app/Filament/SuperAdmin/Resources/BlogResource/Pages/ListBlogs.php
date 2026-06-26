<?php

namespace App\Filament\SuperAdmin\Resources\BlogResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\BlogResource;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
