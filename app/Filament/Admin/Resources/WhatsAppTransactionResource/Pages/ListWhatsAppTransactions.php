<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WhatsAppTransactionResource\Pages;

use App\Filament\Admin\Resources\WhatsAppTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTransactions extends ListRecords
{
    protected static string $resource = WhatsAppTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
