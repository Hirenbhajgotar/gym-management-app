<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\RequestContact;
use App\Notifications\RequestDemoNotification;
use App\Rules\ValidEmailDomain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('frontend.layouts.app_new')]
#[Title('Book a Free Demo | Gym Software | Fluxfitness')]
class RequestDemo extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $company_name = '';

    public ?int $gym_size = null;

    public string $message = '';

    public bool $isSubmitting = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', new ValidEmailDomain],
            'phone' => ['required', 'phone:US,IN,PK'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'gym_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

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
        // Rate limiting - 3 requests per hour per IP
        $key = 'request-demo:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);

            $this->dispatch(
                'show-error',
                message: "Too many requests. Please try again in {$minutes} minutes.",
            );

            return;
        }

        $this->isSubmitting = true;

        // Validate the form
        $validated = $this->validate();

        try {
            // Create the request contact record
            $requestContact = RequestContact::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'company_name' => $validated['company_name'] ?? null,
                'gym_size' => $validated['gym_size'] ?? null,
                'message' => $validated['message'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Hit rate limiter
            RateLimiter::hit($key, 3600); // 1 hour

            // Send notifications to admin
            $this->sendAdminNotifications($requestContact);

            // Show success message
            $this->dispatch(
                'show-success',
                message: 'Request submitted successfully! Thank you for your interest. Our team will contact you shortly.',
            );

            // Reset form
            $this->reset([
                'name',
                'email',
                'phone',
                'company_name',
                'gym_size',
                'message',
            ]);

            // Optional: Redirect to thank you page or home
            // return redirect()->route('home');
        } catch (ValidationException $e) {
            // Validation errors are automatically displayed
            $this->dispatch(
                'show-error',
                message: 'Validation error. Please check the form and correct the errors.',
            );
        } catch (\Exception $e) {
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

    protected function sendAdminNotifications(RequestContact $requestContact): void
    {
        try {
            $adminEmail = config('app.contact_email');
            $adminPhone = config('app.contact_phone');

            // Send email notification
            if ($adminEmail) {
                try {
                    Notification::route('mail', $adminEmail)->notify(
                        new RequestDemoNotification($requestContact),
                    );

                    Log::info('Demo request email sent successfully', [
                        'request_id' => $requestContact->id,
                        'admin_email' => $adminEmail,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send demo request email', [
                        'error' => $e->getMessage(),
                        'request_id' => $requestContact->id,
                    ]);
                }
            }

            // Send WhatsApp notification
            if ($adminPhone) {
                try {
                    $this->sendWhatsAppNotification(
                        $requestContact,
                        $adminPhone,
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to send demo request WhatsApp', [
                        'error' => $e->getMessage(),
                        'request_id' => $requestContact->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin notifications', [
                'error' => $e->getMessage(),
                'request_id' => $requestContact->id,
            ]);
        }
    }

    protected function sendWhatsAppNotification(RequestContact $requestContact, string $adminPhone): void
    {
        try {
            // Format WhatsApp message
            $message = "🔔 *New Demo Request*\n\n";
            $message .= "*Contact Details:*\n";
            $message .= "• Name: {$requestContact->name}\n";
            $message .= "• Email: {$requestContact->email}\n";
            $message .= "• Phone: {$requestContact->phone}\n";

            if ($requestContact->company_name) {
                $message .= "• Company: {$requestContact->company_name}\n";
            }

            if ($requestContact->gym_size) {
                $message .= "• Gym Size: {$requestContact->gym_size} members\n";
            }

            if ($requestContact->message) {
                $message .= "\n*Message:*\n{$requestContact->message}\n";
            }

            $message .=
                "\n⏰ Submitted: " .
                $requestContact->created_at->format('M d, Y H:i A');
            $message .=
                "\n\n_Please respond to this request as soon as possible._";

            // Use Twilio WhatsApp Service
            // Note: For system-level notifications, you might need to use master account
            // or create a system tenant ID configuration
            $tenantId = config('app.system_tenant_id', 'system');

            // $whatsappService = new TwilioWhatsAppService($tenantId);

            // $provider = WhatsAppProviderFactory::create($tenantId);

            // $result = $provider->sendMessage($adminPhone, $message, [
            //     'template_sid' => config(
            //         'services.twilio.demo_request_template_sid',
            //     ),
            //     'template_variables' => [
            //         '1' => $requestContact->name,
            //         '2' => $requestContact->email,
            //         '3' => $requestContact->phone,
            //         '4' => $requestContact->gym_size,
            //         '5' => $requestContact->message,
            //         '6' => $requestContact->created_at->format('M d, Y H:i A'),
            //     ],
            // ]);

            // if ($result['success']) {
            //     Log::info('Demo request WhatsApp sent successfully', [
            //         'request_id' => $requestContact->id,
            //         'message_id' => $result['message_id'] ?? null,
            //     ]);
            // } else {
            //     Log::warning('Demo request WhatsApp failed', [
            //         'request_id' => $requestContact->id,
            //         'error' => $result['error'] ?? 'Unknown error',
            //     ]);
            // }
        } catch (\Exception $e) {
            Log::error('WhatsApp notification error', [
                'error' => $e->getMessage(),
                'request_id' => $requestContact->id,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.frontend.request-demo')
            ->layout('frontend.layouts.app_new', [
                'seo' => [
                    'description' => "Book a free demo of India's best gym management software. Get a personalized demo and see how our software can help your gym grow.",
                    'keywords' => 'gym software demo, free gym management software demo, fitness software demo, book demo, gym CRM demo',
                ],
            ]);
    }
}
