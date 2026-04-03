<?php

namespace App\Livewire;

use App\Models\BusinessType;
use App\Models\City;
use App\Models\Country;
use App\Models\Plan;
use App\Models\State;
use App\Rules\ValidEmailDomain;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

class GymOwnerRegister extends Component
{
    public $currentStep = 1;

    public $totalSteps = 4;

    public $selectedPlan;

    // Personal Information
    public $name = '';

    public $email = '';

    public $phone = '';

    public $password = '';

    public $password_confirmation = '';

    // Location Details (AddressForm integration)
    public $address = [
        'street' => '',
        'city_id' => null,
        'state_id' => null,
        'country_id' => null,
        'postal_code' => '',
        'latitude' => null,
        'longitude' => null,
    ];

    // Business Information
    public $gym_name = '';

    public $business_type = '';

    public $number_of_locations = '';

    public $years_in_business = '';

    public $business_description = '';

    // Additional Information
    public $website = '';

    public $gym_size = '';

    public $special_requirements = '';

    public $isLoading = false;

    public function mount()
    {
        // Get selected plan from session
        $planId = session('selected_plan_id');

        $plan = Plan::find($planId) ?? Plan::query()->trial()->first();

        if (! $plan) {
            return redirect()->route('home')->with('error', 'Plan not selected');
        }

        $this->selectedPlan = $plan;
    }

    public function getBusinessTypes(): Collection
    {
        return BusinessType::active()->orderBy('name')->get();
    }

    #[Computed]
    public function countries()
    {
        return Cache::flexible(Country::COUNTRIES_LIST_CACHE_KEY, [now()->addDays(7), now()->addDays(14)], function () {
            return Country::active()->orderBy('name')->pluck('name', 'id');
        });
    }

    #[Computed]
    public function states()
    {
        if (! $this->address['country_id']) {
            return [];
        }

        return Cache::flexible(State::STATES_OF_COUNTRY_CACHE_KEY . $this->address['country_id'], [now()->addDays(7), now()->addDays(14)], function () {
            return State::where('country_id', $this->address['country_id'])
                ->active()
                ->orderBy('name')
                ->pluck('name', 'id');
        });
    }

    #[Computed]
    public function cities()
    {
        if (! $this->address['state_id']) {
            return [];
        }

        return Cache::flexible(City::CITIES_OF_STATE_CACHE_KEY . $this->address['state_id'], [now()->addDays(7), now()->addDays(14)], function () {
            return City::where('state_id', $this->address['state_id'])
                ->active()
                ->orderBy('name')
                ->pluck('name', 'id');
        });
    }

    public function getGymSizes(): array
    {
        return [
            'small' => 'Small (< 2,000 sq ft)',
            'medium' => 'Medium (2,000-5,000 sq ft)',
            'large' => 'Large (5,000-10,000 sq ft)',
            'extra_large' => 'Extra Large (> 10,000 sq ft)',
        ];
    }

    public function nextStep(): void
    {
        $this->validateCurrentStep();
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep($step): void
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            // Validate previous steps before jumping
            for ($i = 1; $i < $step; $i++) {
                $this->validateStep($i);
            }
            $this->currentStep = $step;
        }
    }

    private function validateCurrentStep(): void
    {
        $this->validateStep($this->currentStep);
    }

    private function validateStep($step): void
    {
        switch ($step) {
            case 1:
                $this->validate(
                    [
                        'name' => 'required|string|max:50',
                        'email' => ['required', 'email', 'max:255', 'unique:users,email', new ValidEmailDomain],
                        'phone' => 'required|phone:IN',
                        'password' => [
                            'required',
                            'string',
                            'confirmed',
                            Password::min(8)
                                ->letters()
                                ->mixedCase()
                                ->numbers()
                                ->symbols()
                                ->uncompromised(),
                        ],
                        'password_confirmation' => 'required|string|min:8',
                    ],
                    [
                        'phone.required' => 'The phone number is required.',
                        'phone.phone' => 'Please enter a valid phone number.',
                        'email.unique' => 'The email has already been taken. Please use a different email address.',
                    ],
                );
                break;
            case 2:
                $this->validate([
                    'gym_name' => 'required|string|max:100',
                    'business_type' => 'required|string',
                    'number_of_locations' => 'required|string',
                    'years_in_business' => 'nullable|integer|min:0|max:50',
                    'business_description' => 'nullable|string|max:500',
                ]);
                break;
            case 3:
                $this->validate([
                    'address.street' => 'required|string|max:255',
                    'address.city_id' => 'required|exists:cities,id',
                    'address.state_id' => 'required|exists:states,id',
                    'address.country_id' => 'required|exists:countries,id',
                    'address.postal_code' => 'required|string|max:20',
                ]);
                break;
            case 4:
                $this->validate([
                    'website' => 'nullable|url|max:255',
                    'gym_size' => 'nullable|string',
                    'special_requirements' => 'nullable|string|max:500',
                ]);
                break;
        }
    }

    public function submit()
    {
        $this->isLoading = true;

        // Validate all steps
        for ($i = 1; $i <= $this->totalSteps; $i++) {
            $this->validateStep($i);
        }

        try {
            // Store all registration data in session
            session()->put('gym_owner_registration', [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => $this->password,
                'gym_name' => $this->gym_name,
                'business_type' => $this->business_type,
                'number_of_locations' => $this->number_of_locations,
                'years_in_business' => $this->years_in_business,
                'business_description' => $this->business_description,
                'address' => $this->address,
                'website' => $this->website,
                'gym_size' => $this->gym_size,
                'special_requirements' => $this->special_requirements,
                'selected_plan_id' => $this->selectedPlan?->id,
            ]);

            // Show a message
            session()->flash(
                'info',
                'Please complete the payment to finalize your registration.',
            );

            // Redirect to Razorpay checkout
            return redirect()->route('gym-owner.checkout');
        } catch (Exception $e) {
            Log::error($e);
            session()->flash(
                'error',
                'An error occurred while processing your registration. Please try again.',
            );
        } finally {
            $this->isLoading = false;
        }
    }

    public function getStepTitle($step): string
    {
        return match ($step) {
            1 => 'Personal Information',
            2 => 'Business Information',
            3 => 'Location Details',
            4 => 'Additional Information',
            default => 'Step ' . $step,
        };
    }

    public function getStepDescription($step): string
    {
        return match ($step) {
            1 => 'Tell us about yourself',
            2 => 'Details about your business',
            3 => 'Where is your business located?',
            4 => 'Optional information to help us serve you better',
            default => '',
        };
    }

    public function getStepIcon($step): string
    {
        return match ($step) {
            1 => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
            2 => 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-1.72L21.378 6.032a.75.75 0 00-.378-1.335l-1.318-.39a1.5 1.5 0 00-1.127.205l-1.19.658a3.004 3.004 0 01-3.073.084L12 4.25l-2.292 1.004a3.004 3.004 0 01-3.073-.084l-1.19-.658a1.5 1.5 0 00-1.127-.205l-1.318.39a.75.75 0 00-.378 1.335L21.378 6.032a.75.75 0 00-.378 1.335l-1.318.39a1.5 1.5 0 01-1.127-.205l-1.19-.658a3.004 3.004 0 00-3.073-.084L12 8.25l-2.292-1.004a3.004 3.004 0 00-3.073.084l-1.19.658a1.5 1.5 0 01-1.127.205l-1.318-.39a.75.75 0 01-.378-1.335z',
            3 => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
            4 => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0-1.125-.504-1.125-1.125V11.25a9 9 0 00-9-9z',
            default => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }

    public function generatePassword(): void
    {
        // Laravel's built-in password generator
        $this->password = Str::password(12);
        $this->password_confirmation = $this->password;
    }

    #[Layout('frontend.layouts.app')]
    public function render(): View
    {
        return view('livewire.gym-owner-register');
    }
}
