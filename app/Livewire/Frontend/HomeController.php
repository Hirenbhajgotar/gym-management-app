<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Actions\Frontend\RequestCallbackAction;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gym Management Software for Indian Gyms | FluxFitness')]
#[Layout('frontend.layouts.app_new', [
    'seo' => [
        'description' => 'Fluxfitness is an all-in-one gym management software with membership tracking, WhatsApp reminders, billing & attendance. Made for Indian gyms. Start free trial today',
        'keywords' => 'gym software, gym software india, gym management software for Indian gyms, fitness center management, gym membership software, gym scheduling software, gym billing software, gym attendance software, gym analytics software, gym CRM software, fitness center management system, branded gym member app, Automated WhatsApp Reminders for Gyms',
    ],
])]
class HomeController extends Component
{
    #[Locked]
    public ?int $selectedPlan = null;

    public string $callbackName = '';

    public string $callbackPhone = '';

    public string $callbackGym = '';

    public bool $callbackSuccess = false;

    public bool $callbackSubmitting = false;

    public function render(): View
    {
        return view('livewire.frontend.home-controller');
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed(persist: true, seconds: 86400, key: Plan::ALL_PLANS_FOR_HOME_CACHE_KEY)]
    public function plans(): Collection
    {
        return Plan::with(['features'])
            ->active()
            ->orderBy('price_monthly')
            ->standard()
            ->get();
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed(persist: true, seconds: 86400, key: Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY)]
    public function enterprisePlans(): Collection
    {
        return Plan::with(['features'])
            ->active()
            ->orderBy('price_monthly')
            ->enterprise()
            ->get();
    }

    public function selectPlan(int|string $planId): void
    {
        $plan = Plan::with(['features'])->active()->find($planId);

        if ($plan === null) {
            $this->addError('selectedPlan', 'The selected plan is currently unavailable.');

            return;
        }

        // Store selected plan in session
        session(['selected_plan_id' => $plan->id]);

        // Redirect to gym owner registration
        $this->redirectRoute('gym.register');
    }

    public function startFreeTrial(): void
    {
        // Get the free plan (price_monthly = 0)
        $freePlan = Plan::with(['features'])
            ->active()
            ->where('price_monthly', 0)
            ->first();

        if ($freePlan) {
            session(['selected_plan_id' => $freePlan->id]);
        }

        // Redirect to registration
        $this->redirectRoute('gym.register');
    }

    public function requestDemo(): void
    {
        $this->redirectRoute('request-demo');
    }

    public function requestCallback(RequestCallbackAction $action): void
    {
        $this->resetErrorBag();

        $clientIp = request()->ip() ?? '127.0.0.1';
        $key = 'request-callback:' . $clientIp;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = (int) ceil($seconds / 60);
            $minuteWord = Str::plural('minute', $minutes);
            $this->addError('callbackPhone', "Too many callback requests. Please try again in {$minutes} {$minuteWord}.");

            return;
        }

        $this->validate([
            'callbackPhone' => ['required', 'string', 'phone:US,IN,PK'],
            'callbackName' => ['nullable', 'string', 'max:255'],
            'callbackGym' => ['nullable', 'string', 'max:255'],
        ], [], [
            'callbackPhone' => 'mobile number',
            'callbackName' => 'name',
            'callbackGym' => 'gym name',
        ]);

        RateLimiter::hit($key, 3600);

        $this->callbackSubmitting = true;

        try {
            $action(
                phone: $this->callbackPhone,
                name: $this->callbackName !== '' ? $this->callbackName : null,
                gymName: $this->callbackGym !== '' ? $this->callbackGym : null,
                ipAddress: $clientIp,
                userAgent: request()->userAgent() ? mb_substr(request()->userAgent(), 0, 255) : null,
            );

            $this->callbackSuccess = true;
            $this->reset(['callbackName', 'callbackPhone', 'callbackGym']);
        } finally {
            $this->callbackSubmitting = false;
        }
    }

    public function resetCallback(): void
    {
        $this->callbackSuccess = false;
        $this->reset(['callbackName', 'callbackPhone', 'callbackGym']);
    }
}
