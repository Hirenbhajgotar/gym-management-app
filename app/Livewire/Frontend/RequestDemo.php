<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Enums\PaymentStatus;
use App\Helpers\Number as HelpersNumber;
use App\Models\Gym;
use App\Models\MemberPayment;
use App\Models\RequestContact;
use App\Notifications\RequestDemoNotification;
use App\Rules\ValidEmailDomain;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

#[Layout('frontend.layouts.app_new', [
    'seo' => [
        'description' => 'Schedule a free 20-minute live demo of FluxFitness gym management software. See automated WhatsApp fee reminders, biometric turnstile gates, and owner dashboards in action.',
        'keywords' => 'gym management software demo, fitness club software, gym CRM, turnstile integration, whatsapp gym software',
    ],
])]
#[Title('Book a Free Demo | Gym Software | Fluxfitness')]
class RequestDemo extends Component
{
    public const FORM_FIELDS = [
        'name',
        'email',
        'phone',
        'company_name',
        'gym_size',
        'message',
    ];

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $company_name = '';

    public ?string $gym_size = null;

    public string $message = '';

    public bool $isSubmitting = false;

    public bool $isSubmitted = false;

    public static function rateLimitKeyFor(?string $ip = null): string
    {
        $resolvedIp = $ip ?: (request()->ip() ?? '127.0.0.1');

        return 'request-demo:' . $resolvedIp;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $countries = (array) config('request-demo.phone_countries', ['US', 'IN', 'PK']);
        $phoneCountriesRule = 'phone:' . implode(',', $countries);

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', new ValidEmailDomain()],
            'phone' => ['required', 'string', 'min:8', $phoneCountriesRule],
            'company_name' => ['nullable', 'string', 'max:255'],
            'gym_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'company_name' => 'company name',
            'gym_size' => 'gym size',
            'message' => 'message',
        ];
    }

    public function submitRequest(): void
    {
        // Rate limiting - derived strictly from remote IP
        $key = static::rateLimitKeyFor(request()->ip());
        $maxAttempts = config()->integer('request-demo.rate_limit_max_attempts', 3);
        $decaySeconds = config()->integer('request-demo.rate_limit_decay_seconds', 3600);

        // Count attempt towards rate limiter
        $attempts = RateLimiter::hit($key, $decaySeconds);

        if ($attempts > $maxAttempts) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = (int) max(1, ceil($seconds / 60));
            $timeUnit = $minutes === 1 ? '1 minute' : "{$minutes} minutes";

            $this->addError(
                'rate_limit',
                "Too many requests. Please try again in {$timeUnit}.",
            );

            return;
        }

        $this->isSubmitting = true;

        try {
            /** @var array{name: string, email: string, phone: string, company_name?: ?string, gym_size?: int|string|null, message?: ?string} $validated */
            $validated = $this->validate();

            // Normalize phone number to canonical E.164
            /** @var array<string> $countries */
            $countries = config()->array('request-demo.phone_countries', ['US', 'IN', 'PK']);
            $rawPhone = trim($validated['phone']);

            try {
                $normalizedPhone = (new PhoneNumber($rawPhone, $countries))->formatE164();
            } catch (Throwable) {
                $normalizedPhone = $rawPhone;
            }

            // Idempotency guard: if identical uncontacted request was created in last 5 mins, return success without duplicate insert
            $existing = RequestContact::query()
                ->where(function ($query) use ($validated, $normalizedPhone): void {
                    $query->where('email', $validated['email'])
                        ->orWhere('phone', $normalizedPhone);
                })
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($existing !== null) {
                $this->dispatch(
                    'show-success',
                    message: 'Request submitted successfully! Thank you for your interest. Our team will contact you shortly.',
                );
                $this->resetForm();
                $this->isSubmitted = true;

                return;
            }

            // Create the request contact record with truncated user_agent
            $rawUserAgent = request()->userAgent();
            $truncatedUserAgent = $rawUserAgent !== null ? Str::limit($rawUserAgent, 255, '') : null;

            $requestContact = RequestContact::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $normalizedPhone,
                'company_name' => $validated['company_name'] ?? null,
                'gym_size' => isset($validated['gym_size']) && is_numeric($validated['gym_size']) ? (int) $validated['gym_size'] : null,
                'message' => $validated['message'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => $truncatedUserAgent,
            ]);

            // Send notifications to admin
            $this->sendAdminNotifications($requestContact);

            // Show success message
            $this->dispatch(
                'show-success',
                message: 'Request submitted successfully! Thank you for your interest. Our team will contact you shortly.',
            );

            // Reset form fields
            $this->resetForm();
            $this->isSubmitted = true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Request Demo Submission Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch(
                'show-error',
                message: 'Something went wrong. Please try again later.',
            );
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function resetForm(): void
    {
        $this->isSubmitted = false;
        $this->resetValidation();
        $this->reset(self::FORM_FIELDS);
    }

    protected function sendAdminNotifications(RequestContact $requestContact): void
    {
        $forceEmail = config('request-demo.force_contact_email');
        $contactEmail = config('app.contact_email');

        $adminEmail = is_string($forceEmail) && $forceEmail !== ''
            ? $forceEmail
            : (is_string($contactEmail) ? $contactEmail : '');

        if ($adminEmail === '') {
            Log::info('Skipping demo request notification: no contact email configured', [
                'request_id' => $requestContact->id,
            ]);

            return;
        }

        try {
            Notification::route('mail', $adminEmail)->notify(
                new RequestDemoNotification($requestContact),
            );

            Log::info('Demo request email dispatched to queue', [
                'request_id' => $requestContact->id,
                'admin_email' => $adminEmail,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send demo request email', [
                'error' => $e->getMessage(),
                'request_id' => $requestContact->id,
                'admin_email' => $adminEmail,
            ]);
        }
    }

    #[Computed]
    public function whatsAppUrl(): string
    {
        $rawPhone = config('app.contact_whatsapp') ?: config('app.contact_phone');
        $phone = is_string($rawPhone) ? $rawPhone : '';
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($digits === '') {
            return '#';
        }

        $message = config()->string('request-demo.whatsapp_message', 'Hi FluxFitness Team! I want to know more about the gym software.');

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    /**
     * Get verified fitness operator testimonials.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTestimonials(): array
    {
        /** @var array<int, array<string, mixed>> $testimonials */
        $testimonials = config()->array('request-demo.testimonials', []);

        return $testimonials;
    }

    /**
     * Get compact demo & objection-handling FAQs.
     *
     * @return array<int, array<string, string>>
     */
    public function getFaqs(): array
    {
        /** @var array<int, array<string, string>> $faqs */
        $faqs = config()->array('request-demo.faqs', []);

        return $faqs;
    }

    #[Computed(cache: true, key: 'request_demo:active_gyms_count', seconds: 3600)]
    public function activeGymsCount(): string
    {
        $default = config()->string('request-demo.metrics.active_gyms_count', '47+');
        $threshold = config()->integer('request-demo.metrics.min_tenants_threshold', 47);

        try {
            $count = Gym::query()->count();

            return $count > $threshold ? "{$count}+" : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    #[Computed(cache: true, key: 'request_demo:monthly_dues_processed', seconds: 3600)]
    public function monthlyDuesProcessed(): string
    {
        $default = config()->string('request-demo.metrics.monthly_dues_processed', '₹42.3 Lakh+');
        $threshold = config()->integer('request-demo.metrics.min_tenants_threshold', 47);

        try {
            $gymCount = Gym::query()->count();

            if ($gymCount <= $threshold) {
                return $default;
            }

            $totalMonthDues = (float) MemberPayment::query()
                ->where('status', PaymentStatus::COMPLETED)
                ->whereYear('payment_date', now()->year)
                ->whereMonth('payment_date', now()->month)
                ->sum('amount');

            if ($totalMonthDues <= 0) {
                return $default;
            }

            return HelpersNumber::currencyForHumans($totalMonthDues, precision: 0, maxPrecision: 1) . '+';
        } catch (Throwable) {
            return $default;
        }
    }

    public function render(): View
    {
        return view('livewire.frontend.request-demo', [
            'testimonials' => $this->getTestimonials(),
            'faqs' => $this->getFaqs(),
        ]);
    }
}
