<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\Contracts\HostResolverInterface;
use App\Biometrics\DTOs\DoorControlResult;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\DoorEvent;
use App\Models\GymMember;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DoorControlService
{
    public function __construct(
        protected HostResolverInterface $hostResolver
    ) {}

    /**
     * Called when access is GRANTED.
     * Supports dual-mode (Decision D-6):
     * 1. If device has an external HTTP controller URL configured in provider_config,
     *    executes HTTP POST to the controller with SSRF protection and timeout.
     * 2. Otherwise (native terminal relay / wiegand mode), returns skipped() so the
     *    webhook response instructs the terminal to pulse its onboard relay.
     */
    public function unlock(BiometricDevice $device): DoorControlResult
    {
        if (! $device->isDoorAccess()) {
            return DoorControlResult::notApplicable();
        }

        /** @var array<string, mixed> $config */
        $config = is_array($device->provider_config) ? $device->provider_config : [];
        $controllerUrl = $config['door_controller_url'] ?? null;

        if (blank($controllerUrl) || ! is_string($controllerUrl)) {
            // Native relay mode: handled by device onboard relay or webhook pulse
            return DoorControlResult::skipped();
        }

        return $this->callDoorApi($device, $controllerUrl, $config);
    }

    /**
     * Call external HTTP door controller.
     *
     * @param  array<string, mixed>  $config
     */
    protected function callDoorApi(BiometricDevice $device, string $url, array $config): DoorControlResult
    {
        $timeout = config()->integer('biometrics.door_controller_timeout', 3);
        $token = $config['door_controller_token'] ?? null;
        $zone = $config['door_zone'] ?? 'main_entrance';
        $unlockDuration = config()->integer('biometrics.default_unlock_duration', 5);

        $resolvedIp = null;
        if (! $this->validateDoorControllerUrl($url, $resolvedIp)) {
            Log::warning('DoorControlService: URL rejected by trusted domains policy', [
                'device_id' => $device->id,
                'url' => $url,
            ]);

            return DoorControlResult::failed('untrusted_door_controller_url', [
                'error' => 'URL rejected by trusted domains policy',
            ]);
        }

        try {
            $parsedHost = (string) parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT);
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'http';

            // Pin DNS resolution to prevent rebinding TOCTOU without breaking TLS SNI
            $resolvePort = $port ?? ($scheme === 'https' ? 443 : 80);
            $resolveEntry = "{$parsedHost}:{$resolvePort}:{$resolvedIp}";

            $client = Http::timeout($timeout)->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => [$resolveEntry],
                ],
            ]);
            if (filled($token) && is_string($token)) {
                $client = $client->withToken($token);
            }

            $response = $client->post($url, [
                'action' => 'unlock',
                'duration' => $unlockDuration,
                'zone' => $zone,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawUnlockedAt = is_array($data) ? ($data['unlocked_at'] ?? null) : null;
                $unlockedAt = is_string($rawUnlockedAt) ? Date::parse($rawUnlockedAt) : now();

                /** @var array<string, mixed> $rawPayload */
                $rawPayload = is_array($data) ? $data : [];

                return new DoorControlResult(
                    success: true,
                    status: 'unlocked',
                    failureReason: null,
                    unlockedAt: $unlockedAt,
                    raw: $rawPayload
                );
            }

            $rawBody = $response->body();
            $logBody = strlen($rawBody) > 200 ? substr($rawBody, 0, 200) . '...' : $rawBody;
            Log::error('DoorControlService: External controller returned error', [
                'device_id' => $device->id,
                'status' => $response->status(),
                'body' => BiometricLogRedactor::mask($logBody),
            ]);

            $rawResponse = $response->json() ?? $response->body();
            $maskedResponse = is_array($rawResponse)
                ? BiometricLogRedactor::redact($rawResponse)
                : BiometricLogRedactor::mask(is_string($rawResponse) ? $rawResponse : (is_scalar($rawResponse) ? (string) $rawResponse : null));

            return DoorControlResult::failed('door_controller_error', [
                'http_status' => $response->status(),
                'response' => $maskedResponse,
            ]);
        } catch (Throwable $e) {
            Log::error('DoorControlService: Failed to reach external door controller', [
                'device_id' => $device->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return DoorControlResult::failed('door_controller_unreachable', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate controller URL against configured trusted domains/subnets (SSRF protection).
     * Enforces deny-by-default.
     */
    protected function validateDoorControllerUrl(string $url, ?string &$resolvedIp = null): bool
    {
        $rawDomains = config('biometrics.trusted_domains', []);
        $trustedDomains = is_array($rawDomains) ? $rawDomains : [];
        $rawSubnets = config('biometrics.trusted_subnets', []);
        /** @var array<string> $trustedSubnets */
        $trustedSubnets = is_array($rawSubnets) ? $rawSubnets : [];

        if (blank($trustedDomains) && blank($trustedSubnets)) {
            Log::warning('DoorControlService: URL rejected - no trusted domains or subnets configured');

            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || blank($host)) {
            return false;
        }

        // If host is an IP literal
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $resolvedIp = $host;

            return $this->validateIpAddress($host, $trustedSubnets);
        }

        // If host is a hostname, check trusted domains
        $domainMatches = false;
        foreach ($trustedDomains as $allowed) {
            if (is_string($allowed) && filled($allowed)) {
                $allowedLower = strtolower(trim($allowed));
                $hostLower = strtolower($host);
                if ($hostLower === $allowedLower || str_ends_with($hostLower, '.' . $allowedLower)) {
                    $domainMatches = true;
                    break;
                }
            }
        }

        if (! $domainMatches) {
            return false;
        }

        // Resolve host IP using injected resolver policy to protect against DNS rebinding
        $resolvedIp = $this->hostResolver->resolve($host);
        if ($resolvedIp === null || ! filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            // Unresolvable host: fail closed
            return false;
        }

        if ($this->isPrivateOrReservedIp($resolvedIp)) {
            return $this->validateIpAddress($resolvedIp, $trustedSubnets);
        }

        return true;
    }

    /**
     * @param  array<string>  $trustedSubnets
     */
    protected function validateIpAddress(string $ip, array $trustedSubnets): bool
    {
        if ($this->isPrivateOrReservedIp($ip)) {
            foreach ($trustedSubnets as $subnet) {
                if (filled($subnet) && $this->ipInSubnet($ip, trim($subnet))) {
                    return true;
                }
            }

            return false;
        }

        foreach ($trustedSubnets as $subnet) {
            if (filled($subnet) && $this->ipInSubnet($ip, trim($subnet))) {
                return true;
            }
        }

        return false;
    }

    protected function isPrivateOrReservedIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '169.254.')) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    protected function ipInSubnet(string $ip, string $range): bool
    {
        if (str_contains($range, '/')) {
            [$subnet, $bits] = explode('/', $range, 2);
            $bits = (int) $bits;
        } else {
            $subnet = $range;
            $bits = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
                return false;
            }
            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
            $subnetLong &= $mask;

            return ($ipLong & $mask) === $subnetLong;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return false;
            }
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false || $bits < 0 || $bits > 128) {
                return false;
            }
            $bytes = intdiv($bits, 8);
            $remainderBits = $bits % 8;
            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }
            if ($remainderBits > 0) {
                $mask = 0xFF << (8 - $remainderBits);

                return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
            }

            return true;
        }

        return false;
    }

    /**
     * Save door event to audit log.
     */
    public function logEvent(
        BiometricDevice $device,
        ?GymMember $gymMember,
        ?Attendances $attendance,
        string $eventType,
        DoorControlResult $result
    ): DoorEvent {
        $unlockDuration = config()->integer('biometrics.default_unlock_duration', 5);

        return DoorEvent::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'gym_member_id' => $gymMember?->id,
            'attendance_id' => $attendance?->id,
            'event_type' => $eventType,
            'trigger_source' => 'biometric',
            'denial_reason' => $result->failureReason,
            'door_status' => $result->status,
            'unlock_duration_seconds' => $unlockDuration,
            'unlocked_at' => $result->status === 'unlocked' ? ($result->unlockedAt ?? now()) : null,
            'ip_address' => request()?->ip(),
            'raw_response' => $result->raw,
        ]);
    }
}
