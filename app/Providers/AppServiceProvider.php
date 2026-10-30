<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\DefaultRole;
use App\Events\BiometricConsentGiven;
use App\Events\BiometricConsentRevoked;
use App\Events\PaymentCompleted;
use App\Listeners\HandleConsentRevoked;
use App\Listeners\LogUserLogin;
use App\Listeners\NotifyStaffOnConsentGiven;
use App\Listeners\SubscriptionProvisioner;
use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Notifications\Channels\FilamentChannel;
use App\Notifications\WhatsAppChannel;
use App\Permissions\TeamPermissionRegistrar;
use App\Services\GymContextResolver;
use Filament\Events\TenantSet;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogFile;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, bool>
     */
    private static array $loggedAdmsWarnings = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        $this->configureCommands();

        $this->registerFilamentColors();

        $this->registerPermissionRegistrar();

        $this->registerRateLimiter();

        $this->guardInsecureAdmsConfiguration();

        $this->registerSpatiePermissionListener();

        $this->registerAuthEventListeners();

        $this->registerBiometricEventListeners();

        $this->registerPaymentEventListeners();

        $this->registerNotificationChannels();

        $this->defineGates();
    }

    private function registerFilamentColors(): void
    {
        FilamentColor::register([
            'primary' => Color::Amber,
            'success' => Color::Green,
            'danger' => Color::Red,
            'info' => Color::Sky,
            'warning' => Color::Yellow,
            'blue' => Color::Blue,
            'neutral' => Color::Neutral,
            'orange' => Color::Orange,
            'lime' => Color::Lime,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'rose' => Color::Rose,
            'purple' => Color::Purple,
            'pink' => Color::Pink,
        ]);
    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());
    }

    private function registerAuthEventListeners(): void
    {
        FacadesEvent::listen(
            Login::class,
            [LogUserLogin::class, 'handleLogin']
        );
        FacadesEvent::listen(
            Logout::class,
            [LogUserLogin::class, 'handleLogout']
        );
        FacadesEvent::listen(
            Failed::class,
            [LogUserLogin::class, 'handleFailed']
        );
    }

    private function registerBiometricEventListeners(): void
    {
        FacadesEvent::listen(
            BiometricConsentGiven::class,
            NotifyStaffOnConsentGiven::class
        );

        FacadesEvent::listen(
            BiometricConsentRevoked::class,
            HandleConsentRevoked::class
        );
    }

    private function registerPaymentEventListeners(): void
    {
        // Provision a subscription once its payment is completed. The listener
        // is idempotent, so a webhook redelivery never creates a duplicate.
        FacadesEvent::listen(
            PaymentCompleted::class,
            SubscriptionProvisioner::class
        );
    }

    private function registerSpatiePermissionListener(): void
    {
        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('whatsapp-outbound-limit', function (object $job) {
            $gymId = property_exists($job, 'gymId') && is_scalar($job->gymId)
                ? (string) $job->gymId
                : 'global';

            return Limit::perSecond(30)->by($gymId);
        });

        RateLimiter::for('biometric-api', function (Request $request) {
            // IP-keyed rate limiter prevents pre-auth rate limit bypass via SN manipulation
            $limit = config('biometrics.device_throttle');
            if (! is_int($limit) || $limit < 0) {
                $limit = 120;
            }

            if ($limit === 0) {
                return Limit::none();
            }

            // Partition rate limit by IP + route category (iclock vs biometric)
            // so an enrollment burst does not block real-time attendance check-ins on shared NAT IPs.
            // IPv6 requests are keyed by /64 so an attacker cannot rotate inside one allocation.
            $category = $request->is('*iclock*') ? 'iclock' : 'biometric';
            $ip = $request->ip() ?? 'unknown';

            if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $matches) === 1
                && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                // IPv4-mapped IPv6 collapses to "::" under the /64 mask, which
                // would put every IPv4 client behind one shared bucket.
                $ip = $matches[1];
            } elseif (preg_match('/^::ffff:([0-9a-f]{1,4}):([0-9a-f]{1,4})$/i', $ip, $matches) === 1) {
                $convertedIp = long2ip((hexdec($matches[1]) << 16) | hexdec($matches[2]));
                if ($convertedIp !== false) {
                    $ip = $convertedIp;
                }
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $packed = inet_pton($ip);

                if (is_string($packed) && strlen($packed) === 16) {
                    $ip = inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) ?: $ip;
                }
            }

            return Limit::perMinute($limit)->by($ip . ':' . $category);
        });
    }

    /**
     * Refuse to run production with the ADMS guessing controls silently disabled.
     */
    private function guardInsecureAdmsConfiguration(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('biometrics.device_throttle') === 0) {
            $this->logInsecureAdmsOnce(
                'throttle_disabled',
                'ADMS: biometric API rate limiting is disabled (BIOMETRIC_DEVICE_THROTTLE=0).'
            );
        }

        if (! config()->boolean('biometrics.adms.require_comm_key', true)) {
            $this->logInsecureAdmsOnce(
                'key_enforcement_disabled',
                'ADMS: communication key enforcement is disabled (ADMS_REQUIRE_COMM_KEY=false).'
            );
        }

        $iterations = config()->integer('biometrics.adms.pbkdf2_iterations', BiometricDevice::MIN_PBKDF2_ITERATIONS);

        if ($iterations < BiometricDevice::MIN_PBKDF2_ITERATIONS) {
            $this->logInsecureAdmsOnce(
                'pbkdf2_below_floor',
                'ADMS: PBKDF2 iterations are below the 100,000 floor.'
            );
        }

        if ($iterations > BiometricDevice::MAX_PBKDF2_ITERATIONS) {
            $this->logInsecureAdmsOnce(
                'pbkdf2_above_ceiling',
                'ADMS: PBKDF2 iterations exceed the safe maximum and will be clamped.'
            );
        }

        if (! in_array(config('cache.default'), ['redis', 'memcached', 'database', 'dynamodb'], true)) {
            $this->logInsecureAdmsOnce(
                'cache_not_shared',
                'ADMS: per-device authentication lockout requires a shared cache store; the configured store is process-local or unsupported.',
                ['store' => config('cache.default')]
            );
        }
    }

    /**
     * Keep an insecure-deployment warning to one line per process instead of
     * emitting up to five criticals on every request/command.
     *
     * @param  array<string, mixed>  $context
     */
    private function logInsecureAdmsOnce(string $key, string $message, array $context = []): void
    {
        if (isset(self::$loggedAdmsWarnings[$key])) {
            return;
        }

        self::$loggedAdmsWarnings[$key] = true;

        Log::critical($message, $context);
    }

    private function registerNotificationChannels(): void
    {
        Notification::extend('whatsapp', function ($app) {
            /** @var GymContextResolver $resolver */
            $resolver = resolve(GymContextResolver::class);

            return new WhatsAppChannel($resolver);
        });

        Notification::extend('filament', function ($app) {
            return new FilamentChannel();
        });
    }

    private function registerPermissionRegistrar(): void
    {
        // Replace Spatie's registrar with per-gym scoped version
        // This loads only current gym's permissions (~500) instead of all (35k+)
        $this->app->singleton(function ($app): PermissionRegistrar {
            return new TeamPermissionRegistrar($app['cache']);
        });
    }

    private function defineGates(): void
    {
        // Gate for queue job
        Gate::define('viewVantage', function (?User $user = null) {
            // Only allow super-admins
            if (! $user) {
                return false;
            }
            if (! $user->hasRole([DefaultRole::SUPER_ADMIN->value])) {
                return false;
            }

            return true;
        });

        // Gate for Log Viewer
        LogViewer::auth(function (Request $request) {
            /** @var User|null $user */
            $user = $request->user();
            // Only allow super-admins
            if (! $user) {
                return false;
            }
            if (! $user->hasRole([DefaultRole::SUPER_ADMIN->value])) {
                return false;
            }

            return true;
        });

        Gate::define('downloadLogFile', function (?User $user, LogFile $file) {
            // return true if the user is allowed to download the specific log file.
            // Only allow super-admins
            if (! $user) {
                return false;
            }
            if (! $user->hasRole([DefaultRole::SUPER_ADMIN->value])) {
                return false;
            }

            return true;
        });

        Gate::define('deleteLogFile', function (?User $user, LogFile $file) {
            // return true if the user is allowed to delete the specific log file.
            // Only allow super-admins
            if (! $user) {
                return false;
            }
            if (! $user->hasRole([DefaultRole::SUPER_ADMIN->value])) {
                return false;
            }

            return true;
        });
    }
}
