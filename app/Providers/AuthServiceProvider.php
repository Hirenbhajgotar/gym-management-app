<?php

namespace App\Providers;

use App\Policies\ExportPolicy;
use App\Policies\ImportPolicy;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ProvidersAuthServiceProvider;

class AuthServiceProvider extends ProvidersAuthServiceProvider
{
    protected $policies = [
        Export::class => ExportPolicy::class,
        Import::class => ImportPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
