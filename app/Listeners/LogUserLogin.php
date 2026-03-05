<?php

namespace App\Listeners;

use App\Models\UserLoginLog;
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

        // Get the primary gym from pivot (first one)
        $gym = $user->gyms()->first();

        // Skip if no gym association
        if (! $gym) {
            return;
        }

        // Mark any existing active sessions as logged out using the service
        $loginService = app(\App\Services\LoginActivityService::class);
        $loginService->markSessionAsLoggedOut($user->id);

        // Get request details
        $request = request();
        $ipAddress = $this->getIpAddress($request);
        $userAgent = $request->userAgent();
        $deviceType = $this->detectDeviceType($userAgent);

        UserLoginLog::create([
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
        UserLoginLog::where('user_id', $user->id)
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
        // Check for forwarded IP (when behind proxy)
        if ($request->hasHeader('X-Forwarded-For')) {
            return explode(',', $request->header('X-Forwarded-For'))[0];
        }

        if ($request->hasHeader('X-Real-IP')) {
            return $request->header('X-Real-IP');
        }

        return $request->ip();
    }

    /**
     * Detect device type from user agent string.
     */
    protected function detectDeviceType(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
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
}
