<?php

namespace App\Filament\Admin\Pages\Tenancy;

use App\Forms\Components\AddressForm;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Str;

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
            ->columns(1);
    }

    public function save(bool $shouldRedirect = true): void
    {
        parent::save(false);

        $redirectUrl = route($this->getRouteName(), ['tenant' => $this->tenant->slug]);

        $this->redirect($redirectUrl);
        // $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }
}
