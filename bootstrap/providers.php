<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ExportServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\StaffPanelProvider::class,
    App\Providers\Filament\SuperAdminPanelProvider::class,
    App\Providers\VoltServiceProvider::class,
    Spatie\EloquentSortable\EloquentSortableServiceProvider::class,
];
