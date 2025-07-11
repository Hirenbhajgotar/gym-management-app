<?php

namespace App\Livewire;

use App\Models\BusinessType;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\UserActionNotification;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

class GymOwnerRegister extends Component
{
    public $currentStep = 1;
    public $totalSteps = 4;

    // Personal Information
    #[Validate('required|string|max:50')]
    public $name = '';

    #[Validate('required|email|max:255')]
    public $email = '';

    #[Validate('required|string|max:20')]
    public $phone = '';

    #[Validate('required|string|min:8|confirmed')]
    public $password = '';

    #[Validate('required|string|min:8')]
    public $password_confirmation = '';

    // Location Details (AddressForm integration)
    public $address = [
        'street' => '',
        'city_id' => '',
        'state_id' => '',
        'country_id' => '',
        'postal_code' => '',
        'latitude' => null,
        'longitude' => null,
    ];

    // Business Information
    #[Validate('required|string|max:100')]
    public $gym_name = '';

    #[Validate('required|string')]
    public $business_type = '';

    #[Validate('required|string')]
    public $number_of_locations = '';

    #[Validate('nullable|integer|min:0|max:50')]
    public $years_in_business = '';

    #[Validate('nullable|string|max:500')]
    public $business_description = '';

    // Additional Information
    #[Validate('nullable|url|max:255')]
    public $website = '';

    #[Validate('nullable|string')]
    public $gym_size = '';

    #[Validate('nullable|string|max:500')]
    public $special_requirements = '';

    public $isLoading = false;

    public function getBusinessTypes()
    {
        return BusinessType::active()->orderBy('name')->get();
    }

    public function getGymSizes()
    {
        return [
            'small' => 'Small (< 2,000 sq ft)',
            'medium' => 'Medium (2,000-5,000 sq ft)',
            'large' => 'Large (5,000-10,000 sq ft)',
            'extra_large' => 'Extra Large (> 10,000 sq ft)',
        ];
    }

    public function nextStep()
    {
        $this->validateCurrentStep();
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep($step)
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            // Validate previous steps before jumping
            for ($i = 1; $i < $step; $i++) {
                $this->validateStep($i);
            }
            $this->currentStep = $step;
        }
    }

    private function validateCurrentStep()
    {
        $this->validateStep($this->currentStep);
    }

    private function validateStep($step)
    {
        switch ($step) {
            case 1:
                $this->validate([
                    'name' => 'required|string|max:50',
                    'email' => 'required|email|max:255|unique:users,email',
                    'phone' => 'required|string|max:20',
                    'password' => 'required|string|min:8|confirmed',
                    'password_confirmation' => 'required|string|min:8',
                ]);
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
            DB::beginTransaction();

            // Create User
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => $this->password,
            ]);

            // Assign gym_owner role
            $user->assignRole('gym_owner');

            // Create Gym with unique slug
            $gym = Gym::create([
                'name' => $this->gym_name,
                'slug' => Gym::generateUniqueSlug($this->gym_name),
            ]);

            // Create Address for Gym
            $address = $gym->address()->create([
                'street' => $this->address['street'],
                'city_id' => $this->address['city_id'],
                'state_id' => $this->address['state_id'],
                'country_id' => $this->address['country_id'],
                'postal_code' => $this->address['postal_code'],
                'latitude' => $this->address['latitude'] ?? null,
                'longitude' => $this->address['longitude'] ?? null,
            ]);

            // Create Business Detail for Gym
            $businessDetail = $gym->businessDetail()->create([
                'gym_id' => $gym->id,
                'business_type_id' => $this->business_type ?: null,
                'years_in_business' => $this->years_in_business,
                'number_of_locations' => $this->number_of_locations,
                'website' => $this->website,
                'gym_size' => $this->gym_size,
                'business_description' => $this->business_description,
                'special_requirements' => $this->special_requirements,
            ]);

            // Attach User to Gym in pivot table
            $user->gyms()->attach($gym->id, ['role' => 'gym_owner']);
            DB::commit();

            // Show success message
            session()->flash('success', 'Registration submitted successfully! We will review your application and contact you soon.');

            // Send notification to super-admins
            $superAdmins = User::role('super_admin')->get();
            foreach ($superAdmins as $superAdmin) {
                $superAdmin->notify(new UserActionNotification('created', $user, "Gym: {$gym->name}"));

                Notification::make()
                    ->title('New Business Added - ' . $gym->name)
                    ->body("A new business '{$gym->name}' has been registered by '{$user->name}'. Please review their application.")
                    ->success()
                    ->sendToDatabase($superAdmin)
                    ->send();
            }

            // Log in the user
            Auth::login($user);

            // Redirect to admin dashboard
            return redirect()->route('admin.dashboard');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            session()->flash('error', 'An error occurred while processing your registration. Please try again.');
        } finally {
            $this->isLoading = false;
        }
    }

    public function getStepTitle($step)
    {
        return match ($step) {
            1 => 'Personal Information',
            2 => 'Business Information',
            3 => 'Location Details',
            4 => 'Additional Information',
            default => 'Step ' . $step
        };
    }

    public function getStepDescription($step)
    {
        return match ($step) {
            1 => 'Tell us about yourself',
            2 => 'Details about your gym business',
            3 => 'Where is your gym located?',
            4 => 'Optional information to help us serve you better',
            default => ''
        };
    }

    public function getStepIcon($step)
    {
        return match ($step) {
            1 => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
            2 => 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-1.72L21.378 6.032a.75.75 0 00-.378-1.335l-1.318-.39a1.5 1.5 0 00-1.127.205l-1.19.658a3.004 3.004 0 01-3.073.084L12 4.25l-2.292 1.004a3.004 3.004 0 01-3.073-.084l-1.19-.658a1.5 1.5 0 00-1.127-.205l-1.318.39a.75.75 0 00-.378 1.335L21.378 6.032a.75.75 0 00-.378 1.335l-1.318.39a1.5 1.5 0 01-1.127-.205l-1.19-.658a3.004 3.004 0 00-3.073-.084L12 8.25l-2.292-1.004a3.004 3.004 0 00-3.073.084l-1.19.658a1.5 1.5 0 01-1.127.205l-1.318-.39a.75.75 0 01-.378-1.335z',
            3 => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
            4 => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0-1.125-.504-1.125-1.125V11.25a9 9 0 00-9-9z',
            default => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'
        };
    }

    #[Layout('frontend.layouts.app')]
    public function render()
    {
        return view('livewire.gym-owner-register');
    }
}
