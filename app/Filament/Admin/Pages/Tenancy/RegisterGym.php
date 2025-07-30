<?php

namespace App\Filament\Admin\Pages\Tenancy;

use App\Enums\DefaultRole;
use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Str;

class RegisterGym extends RegisterTenant
{
    protected static string $view = 'filament.admin.pages.register-gym';

    public $totalGyms = 10; // Maximum gyms allowed per owner
    public $authUserGyms = 0;

    public function __construct()
    {
        $this->authUserGyms = auth()->user()?->gyms()->count();
    }

    public static function getLabel(): string
    {
        return 'Register Gym';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Fitness Hub')
                            ->autofocus()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state, $context) {
                                // if ($context === 'create') {
                                // }
                                $set('slug', Gym::generateUniqueSlug($state));
                            })
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        TextInput::make('slug')
                            ->required()
                            ->unique(Gym::class, 'slug', ignoreRecord: true)
                            ->helperText('This will be used in the URL (e.g., /admin/your-slug)')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('slug', Str::slug($state));
                            }),

                        AddressForm::make('address')
                            ->columnSpan('full'),
                    ])
                    ->columns(2) // This creates a 2-column layout within the section
                    ->columnSpan('full'), // This makes the section span full width
            ])
            ->columns(1); // This sets the main form to single column (full width)
    }

    protected function hasReachedGymLimit(): bool
    {
        $gymCount = $this->authUserGyms;
        // $gymCount = Gym::where('owner_id', auth()->id())->count();
        return $gymCount >= $this->totalGyms;
    }

    protected function getGymLimitDescription(): string
    {
        // $currentCount = Gym::where('owner_id', auth()->id())->count();
        $currentCount = $this->authUserGyms;
        $remaining = $this->totalGyms - $currentCount;

        if ($remaining <= 0) {
            return '⚠️ You have reached the maximum limit of ' . $this->totalGyms . ' gyms. Contact support to register more gyms.';
        }

        return "📍 You can register {$remaining} more gym(s). Current: {$currentCount}/{$this->totalGyms}";
    }

    protected function getFormActions(): array
    {
        if ($this->hasReachedGymLimit()) {
            return [];
        }

        return parent::getFormActions();
    }

    public function register(): void
    {
        // Double-check the limit before processing
        if ($this->hasReachedGymLimit()) {
            Notification::make()
                ->title('Registration Failed')
                ->body('You have reached the maximum limit of 3 gyms.')
                ->danger()
                ->send();

            return;
        }

        parent::register();
    }

    protected function handleRegistration(array $data): Gym
    {
        // Final check before creating the gym
        if ($this->hasReachedGymLimit()) {
            throw new \Exception('Gym limit exceeded. Cannot register more than 3 gyms.');
        }

        $gym = Gym::create($data);

        // Associate the user with the new gym and assign the 'gym_owner' role.
        $gym->users()->attach(auth()->user(), ['role' => DefaultRole::GYM_OWNER->value]);


        // Show success notification with remaining count
        $remainingCount = 2 - $this->authUserGyms;

        if ($remainingCount > 0) {
            Notification::make()
                ->title('Gym Registered Successfully!')
                ->body("You can register {$remainingCount} more gym(s).")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Gym Registered Successfully!')
                ->body('You have reached the maximum limit of 3 gyms.')
                ->success()
                ->send();
        }

        return $gym;
    }
}
