<?php

namespace App\Filament\Admin\Pages\Tenancy;

use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class EditGymProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Gym profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                FileUpload::make('logo')
                                    ->label('Gym Logo')
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->directory('gym-logos')
                                    ->columnSpanFull(),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Fitness Hub')
                                    ->autofocus()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, $state, $context) {
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
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Security')
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        TextInput::make('current_password')
                                            ->label('Current Password')
                                            ->password()
                                            ->revealable()
                                            // ->dehydrated(false)
                                            ->requiredWith('new_password')
                                            ->rule(function () {
                                                return function (string $attribute, $value, \Closure $fail) {
                                                    if (! \Illuminate\Support\Facades\Hash::check($value, auth()->user()->password)) {
                                                        $fail('The provided password does not match your current password.');
                                                    }
                                                };
                                            }),
                                    ]),

                                TextInput::make('new_password')
                                    ->label('New Password')
                                    ->password()
                                    ->revealable()
                                    ->rule(Password::default())
                                    ->hint('Need a strong password?')
                                    ->hintAction(
                                        Action::make('generatePassword')
                                            ->label('Generate')
                                            ->icon('heroicon-o-key')
                                            ->action(function (Set $set) {
                                                $password = Str::password(12);
                                                $set('new_password', $password);
                                                $set('new_password_confirmation', $password);
                                            })
                                    ),

                                TextInput::make('new_password_confirmation')
                                    ->label('Confirm Password')
                                    ->password()
                                    ->revealable()
                                    ->same('new_password')
                                    ->requiredWith('new_password'),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public function save(bool $shouldRedirect = true): void
    {
        $data = $this->form->getState();

        if (! empty($data['new_password'])) {
            $user = auth()->user();

            $user->password = $data['new_password'];
            $user->save();

            Notification::make()
                ->success()
                ->title('Password Updated')
                ->body('Your password has been successfully updated.')
                ->send();
        }

        parent::save(false);

        /**
         * @var Gym $tenant
         */
        $tenant = $this->tenant;

        $redirectUrl = route($this->getRouteName(), ['tenant' => $tenant->slug]);

        $this->redirect($redirectUrl);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Gym Profile Updated')
            ->body('Your gym profile has been successfully updated.');
    }
}
