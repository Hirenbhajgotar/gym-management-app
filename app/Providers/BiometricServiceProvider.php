<?php

declare(strict_types=1);

namespace App\Providers;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\HostResolverInterface;
use App\Biometrics\Services\AntiSpoofGuard;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Biometrics\Services\CheckInService;
use App\Biometrics\Services\DnsHostResolver;
use App\Biometrics\Services\DoorControlService;
use App\Biometrics\Services\FraudDetector;
use App\Biometrics\Services\FusionEngine;
use App\Biometrics\Services\MembershipGuard;
use App\Biometrics\Services\OfflineQueueManager;
use App\Biometrics\Services\OfflineSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class BiometricServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HostResolverInterface::class, DnsHostResolver::class);
        $this->app->singleton(BiometricProviderRegistry::class);
        $this->app->singleton(DoorControlService::class);
        $this->app->singleton(CheckInService::class);
        $this->app->singleton(FusionEngine::class);
        $this->app->singleton(AntiSpoofGuard::class);
        $this->app->singleton(FraudDetector::class);
        $this->app->singleton(MembershipGuard::class);
        $this->app->singleton(OfflineSyncService::class);
        $this->app->singleton(OfflineQueueManager::class);
        $this->app->singleton(BiometricDataDeletionService::class);
        $this->app->singleton(BiometricTemplatePropagator::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction() && ! config()->boolean('biometrics.adms.require_comm_key', true)) {
            Log::warning(
                'ADMS communication key requirement is disabled in production. This leaves ADMS endpoints vulnerable to impersonation. Ensure ADMS_REQUIRE_COMM_KEY=true is set.'
            );
        }
    }
}
