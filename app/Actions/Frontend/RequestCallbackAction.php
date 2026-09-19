<?php

declare(strict_types=1);

namespace App\Actions\Frontend;

use App\Models\RequestContact;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class RequestCallbackAction
{
    /**
     * Execute the callback request action as an invokable class.
     *
     * @param  string  $phone  The raw or formatted phone number
     * @param  string|null  $name  Visitor name
     * @param  string|null  $gymName  Gym or company name
     * @param  string|null  $ipAddress  Client IP address (max 45 chars)
     * @param  string|null  $userAgent  Client User Agent (max 255 chars)
     */
    public function __invoke(
        string $phone,
        ?string $name = null,
        ?string $gymName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): RequestContact {
        $trimmedPhone = trim($phone);
        throw_if($trimmedPhone === '', InvalidArgumentException::class, 'Phone number is required for callback requests.');

        // Normalize phone number to canonical E.164 (+...) format for database persistence and casting
        try {
            $normalizedPhone = (new PhoneNumber($trimmedPhone, ['IN', 'US', 'PK']))->formatE164();
        } catch (Throwable) {
            throw new InvalidArgumentException("Invalid phone number format for callback: {$phone}");
        }

        $contactName = ($name !== null && trim($name) !== '')
            ? mb_substr(trim($name), 0, 255)
            : 'Callback Request';

        $companyName = ($gymName !== null && trim($gymName) !== '')
            ? mb_substr(trim($gymName), 0, 255)
            : null;

        $host = parse_url(config()->string('app.url', 'https://fluxfitness.in'), PHP_URL_HOST) ?: 'fluxfitness.in';
        $domain = (in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true) || filter_var($host, FILTER_VALIDATE_IP) !== false)
            ? 'fluxfitness.in'
            : $host;
        $digitsOnly = preg_replace('/[^0-9]/', '', $normalizedPhone);
        $truncatedIp = $ipAddress !== null ? mb_substr(trim($ipAddress), 0, 45) : null;
        $truncatedUA = $userAgent !== null ? mb_substr(trim($userAgent), 0, 255) : null;

        // Idempotency check: return existing uncontacted request if submitted within last 15 minutes for same phone
        $existing = RequestContact::query()
            ->where('phone', $normalizedPhone)
            ->where('is_contacted', false)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return RequestContact::query()->create([
            'name' => $contactName,
            'email' => "callback_{$digitsOnly}@{$domain}",
            'phone' => $normalizedPhone,
            'company_name' => $companyName,
            'message' => 'Requested instant callback from homepage contact hub.',
            'ip_address' => $truncatedIp,
            'user_agent' => $truncatedUA,
        ]);
    }
}
