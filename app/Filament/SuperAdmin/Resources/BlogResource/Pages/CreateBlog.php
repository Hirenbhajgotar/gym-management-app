<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\BlogResource\Pages;

use App\Filament\SuperAdmin\Resources\BlogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;
}
