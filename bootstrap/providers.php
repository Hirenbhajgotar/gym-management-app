<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\BiometricServiceProvider::class,
    App\Providers\ExportServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\StaffPanelProvider::class,
    App\Providers\Filament\SuperAdminPanelProvider::class,
    App\Providers\VoltServiceProvider::class,
    Spatie\EloquentSortable\EloquentSortableServiceProvider::class,
];
