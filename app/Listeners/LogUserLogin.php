<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\LoginActivityService;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Listener to log user login activity.
 *
 * This service provides methods to retrieve login statistics and history
 * for staff roles (gym_owner, manager, receptionist, trainer).
 */
class LogUserLogin
{
    /**
     * Prevent double logging in the same request.
     */
    protected static array $processedUsers = [];

    public function handleLogin(Login $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        // Skip if user is null
        if (! $user) {
            return;
        }

        // Prevent double logging for the same user in the same request/lifecycle
        if (isset(static::$processedUsers[$user->id])) {
            return;
        }
        static::$processedUsers[$user->id] = true;

        // Skip non-staff roles that are not associated with any gym or not in staff roles
        $staffRoles = ['gym_owner', 'manager', 'receptionist', 'trainer'];
        if (! $user->gyms()->whereIn('gym_user.role', $staffRoles)->exists()) {
            return;
        }

        $request = request();

        // Try to get the active gym from Filament context or request
        $gym = null;

        try {
            if (class_exists(Filament::class) && Filament::isServing()) {
                $gym = Filament::getTenant();
            }
        } catch (\Throwable $th) {
            // Ignore if tenant is not set yet
        }

        // Also check if passed in API request
        if (! $gym && $request->has('gym_id')) {
            $gym = $user->gyms()->where('gyms.id', $request->input('gym_id'))->first();
        }

        // Fallback to the primary gym from pivot (first one)
        if (! $gym) {
            $gym = $user->gyms()->first();
        }

        // Skip if no gym association
        if (! $gym) {
            return;
        }

        // Mark any existing active sessions as logged out using the service
        $loginService = resolve(LoginActivityService::class);
        $loginService->markSessionAsLoggedOut($user->id);
        $ipAddress = $this->getIpAddress($request);
        $userAgent = $request->userAgent();
        $deviceType = $this->detectDeviceType($userAgent);

        UserLoginLog::query()->create([
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'login_at' => now(),
            'is_current_session' => true,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'login_method' => 'password',
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        // Mark the current session as logged out
        UserLoginLog::query()->where('user_id', $user->id)
            ->where('is_current_session', true)
            ->whereNull('logged_out_at')
            ->update([
                'logged_out_at' => now(),
                'is_current_session' => false,
            ]);
    }

    public function handleFailed(Failed $event): void
    {
        // Optionally log failed login attempts
        // For now, we only track successful logins as per requirements
    }

    /**
     * Get IP address from request, handling proxies.
     */
    protected function getIpAddress(Request $request): ?string
    {
        return $request->ip();
    }

    /**
     * Detect device type from user agent string.
     */
    protected function detectDeviceType(?string $userAgent): ?string
    {
        if (blank($userAgent)) {
            return null;
        }

        $userAgent = strtolower($userAgent);

        // Check for bots first
        if ($this->isBot($userAgent)) {
            return 'bot';
        }

        // Check for mobile devices
        if (
            preg_match('/mobile|android|iphone|ipad|phone|tablet|ipod/i', $userAgent)
        ) {
            if (preg_match('/ipad|tablet/i', $userAgent)) {
                return 'tablet';
            }

            return 'mobile';
        }

        // Check for desktop
        if (preg_match('/windows|macintosh|linux|cros/i', $userAgent)) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Check if user agent is a bot.
     */
    protected function isBot(string $userAgent): bool
    {
        $bots = [
            'bot', 'crawler', 'spider', 'googlebot', 'bingbot', 'yandex',
            'duckduckbot', 'baiduspider', 'facebookexternalhit', 'twitterbot',
        ];

        foreach ($bots as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle the tenant set event to update the active session's gym.
     */
    public function handleTenantSet(TenantSet $event): void
    {
        $user = $event->getUser();
        $tenant = $event->getTenant();

        if (! $tenant) {
            return;
        }

        // Update the current session's gym_id to match the newly active tenant
        UserLoginLog::query()->where('user_id', $user->id)
            ->where('is_current_session', true)
            ->whereNull('logged_out_at')
            ->latest('login_at')
            ->update(['gym_id' => $tenant->id]);
    }
}
