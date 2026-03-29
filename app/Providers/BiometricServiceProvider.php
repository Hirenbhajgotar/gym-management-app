<?php

namespace App\Providers;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Services\AntiSpoofGuard;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\CheckInService;
use App\Biometrics\Services\DoorControlService;
use App\Biometrics\Services\FraudDetector;
use App\Biometrics\Services\FusionEngine;
use App\Biometrics\Services\MembershipGuard;
use App\Biometrics\Services\OfflineQueueManager;
use App\Biometrics\Services\OfflineSyncService;
use Illuminate\Support\ServiceProvider;

class BiometricServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }

    public function boot(): void
    {
        //
    }
}
