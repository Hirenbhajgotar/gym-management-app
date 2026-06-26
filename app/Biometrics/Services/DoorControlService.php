<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use Illuminate\Http\Client\ConnectionException;
use App\Biometrics\DTOs\DoorControlResult;
use App\Enums\BiometricDoorControlType;
use App\Events\DoorControllerUnreachable;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\DoorEvent;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoorControlService
{
    /**
     * Called when access is GRANTED
     */
    public function unlock(BiometricDevice $device): DoorControlResult
    {
        if (! $device->isDoorAccess()) {
            return DoorControlResult::notApplicable();
        }

        $doorControlType = $device->door_control_type;

        // Relay and Wiegand: FluxFitness does NOT call any API.
        // The device handles relay/wiegand trigger by reading
        // the webhook response field: door.action = "unlock"
        // FluxFitness simply returns skipped() — success assumed.
        if ($doorControlType === BiometricDoorControlType::Relay
            || $doorControlType === BiometricDoorControlType::Wiegand) {
            return DoorControlResult::skipped();
        }

        // API: FluxFitness calls the door controller REST API
        if ($doorControlType === BiometricDoorControlType::Api) {
            return $this->callDoorApi($device);
        }

        return DoorControlResult::notApplicable();
    }

    /**
     * Call the door controller API for API-based door control
     */
    private function callDoorApi(BiometricDevice $device): DoorControlResult
    {
        if (! $device->hasDoorApiControl()) {
            return DoorControlResult::failed('door_controller_url not configured');
        }

        // Validate URL against trusted domains/subnets
        $validationResult = $this->validateDoorControllerUrl($device->door_controller_url);
        if ($validationResult !== true) {
            Log::warning('Door controller URL validation failed', [
                'device_id' => $device->id,
                'url' => $device->door_controller_url,
                'reason' => $validationResult,
            ]);

            return DoorControlResult::failed('door_controller_url not allowed');
        }

        try {
            $request = Http::timeout(config('biometrics.door_controller_timeout', 3));

            if ($device->door_controller_token) {
                $request = $request->withToken($device->door_controller_token);
            }
            $response = $request->post($device->door_controller_url, [
                'action' => 'unlock',
                'duration' => $device->unlock_duration_seconds,
                'zone' => $device->door_zone,
            ]);

            if ($response->successful()) {
                return DoorControlResult::unlocked();
            }

            return DoorControlResult::failed(
                'Door API responded with error: ' . $response->status(),
                $response->json() ?? []
            );

        } catch (ConnectionException $e) {
            event(new DoorControllerUnreachable($device, $e->getMessage()));

            return DoorControlResult::failed('Door controller unreachable');
        } catch (\Exception $e) {
            event(new DoorControllerUnreachable($device, $e->getMessage()));

            return DoorControlResult::failed('Door controller error: ' . $e->getMessage());
        }
    }

    /**
     * Validate door controller URL against trusted domains/subnets
     *
     * @return true|string True if valid, or error message if invalid
     */
    private function validateDoorControllerUrl(string $url): true|string
    {
        // Parse and validate URL
        $parsed = parse_url($url);

        if (! isset($parsed['host'])) {
            return 'Invalid URL: no host component';
        }

        $host = $parsed['host'];

        // // Allow localhost and IP addresses
        // if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        //     return true;
        // }

        // Get trusted config
        $trustedDomains = array_filter(config('biometrics.trusted_domains', []));
        $trustedSubnets = array_filter(config('biometrics.trusted_subnets', []));

        // If no restrictions configured, deny by default for security
        if (blank($trustedDomains) && blank($trustedSubnets)) {
            return 'No trusted domains or subnets configured';
        }

        // Check exact hostname match
        foreach ($trustedDomains as $domain) {
            $domain = trim($domain);
            if (strcasecmp($host, $domain) === 0 || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        // Resolve hostname to IP and check subnet match
        $resolvedIp = $this->resolveHostToIp($host);
        if ($resolvedIp === null) {
            return "Cannot resolve host: {$host}";
        }

        // Check resolved IP against subnets
        foreach ($trustedSubnets as $subnet) {
            $subnet = trim($subnet);
            if ($this->ipInSubnet($resolvedIp, $subnet)) {
                return true;
            }
        }

        // Also check if the host itself is an IP in the subnets
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            foreach ($trustedSubnets as $subnet) {
                $subnet = trim($subnet);
                if ($this->ipInSubnet($host, $subnet)) {
                    return true;
                }
            }
        }

        return "Host {$host} (resolved to {$resolvedIp}) not in trusted list";
    }

    /**
     * Resolve hostname to IP address
     */
    private function resolveHostToIp(string $host): ?string
    {
        // Check if already an IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $records = @dns_get_record($host, DNS_A);

        if ($records !== false && filled($records)) {
            return $records[0]['ip'] ?? null;
        }

        // Try AAAA record for IPv6
        $records = @dns_get_record($host, DNS_AAAA);
        if ($records !== false && filled($records)) {
            return $records[0]['ipv6'] ?? null;
        }

        return null;
    }

    /**
     * Check if IP is in CIDR subnet
     */
    private function ipInSubnet(string $ip, string $subnet): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Handle single IP (no CIDR notation)
        if (strpos($subnet, '/') === false) {
            return $ip === $subnet;
        }

        [$subnetIp, $mask] = explode('/', $subnet);

        if (! filter_var($subnetIp, FILTER_VALIDATE_IP)) {
            return false;
        }

        $mask = (int) $mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnetIp);
            $maskLong = -1 << (32 - $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6 not fully supported for subnet check, fall back to exact match
        return $ip === $subnetIp;
    }

    /**
     * Save door event to audit log
     */
    public function logEvent(
        BiometricDevice $device,
        ?GymMember $gymMember,
        ?Attendances $attendance,
        string $eventType,
        DoorControlResult $result
    ): DoorEvent {
        return DoorEvent::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'gym_member_id' => $gymMember?->id,
            'attendance_id' => $attendance?->id,
            'event_type' => $eventType,
            'trigger_source' => 'biometric',
            'denial_reason' => $result->failureReason,
            'door_status' => $result->status,
            'unlock_duration_seconds' => $device->unlock_duration_seconds,
            'unlocked_at' => $result->unlockedAt,
            'ip_address' => request()?->ip(),
            'raw_response' => $result->raw,
        ]);
    }
}
