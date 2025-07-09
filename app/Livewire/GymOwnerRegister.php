<?php

namespace App\Livewire;

use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

class GymOwnerRegister extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('data.gym_name')
                    ->label('Gym Name')
                    ->placeholder('Enter your gym name')
                    ->required()
                    ->prefixIcon('heroicon-o-building-storefront'),
                // Wizard::make([
                //     Wizard\Step::make('Personal Information')
                //         ->description('Tell us about yourself')
                //         ->icon('heroicon-o-user-circle')
                //         ->schema([
                //             Grid::make(2)
                //                 ->schema([
                //                     TextInput::make('first_name')
                //                         ->label('First Name')
                //                         ->placeholder('John')
                //                         ->required()
                //                         ->maxLength(50)
                //                         ->prefixIcon('heroicon-o-user')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     TextInput::make('last_name')
                //                         ->label('Last Name')
                //                         ->placeholder('Doe')
                //                         ->required()
                //                         ->maxLength(50)
                //                         ->prefixIcon('heroicon-o-user')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                 ]),
                //             Grid::make(2)
                //                 ->schema([
                //                     TextInput::make('email')
                //                         ->label('Email Address')
                //                         ->placeholder('john@example.com')
                //                         ->email()
                //                         ->required()
                //                         ->prefixIcon('heroicon-o-envelope')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     TextInput::make('phone')
                //                         ->label('Phone Number')
                //                         ->placeholder('+1 (555) 123-4567')
                //                         ->tel()
                //                         ->required()
                //                         ->prefixIcon('heroicon-o-phone')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                 ]),
                //         ]),

                //     Wizard\Step::make('Location Details')
                //         ->description('Where is your gym located?')
                //         ->icon('heroicon-o-map-pin')
                //         ->schema([
                //             TextInput::make('address')
                //                 ->label('Street Address')
                //                 ->placeholder('123 Main Street')
                //                 ->required()
                //                 ->maxLength(255)
                //                 ->prefixIcon('heroicon-o-map-pin')
                //                 ->extraAttributes(['class' => 'w-full'])
                //                 ->columnSpanFull(),
                //             Grid::make(3)
                //                 ->schema([
                //                     TextInput::make('city')
                //                         ->label('City')
                //                         ->placeholder('Los Angeles')
                //                         ->required()
                //                         ->maxLength(100)
                //                         ->prefixIcon('heroicon-o-building-office-2')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     Select::make('state')
                //                         ->label('State')
                //                         ->options([
                //                             'AL' => 'Alabama',
                //                             'AK' => 'Alaska',
                //                             'AZ' => 'Arizona',
                //                             'AR' => 'Arkansas',
                //                             'CA' => 'California',
                //                             'CO' => 'Colorado',
                //                             'CT' => 'Connecticut',
                //                             'DE' => 'Delaware',
                //                             'FL' => 'Florida',
                //                             'GA' => 'Georgia',
                //                             'HI' => 'Hawaii',
                //                             'ID' => 'Idaho',
                //                             'IL' => 'Illinois',
                //                             'IN' => 'Indiana',
                //                             'IA' => 'Iowa',
                //                             'KS' => 'Kansas',
                //                             'KY' => 'Kentucky',
                //                             'LA' => 'Louisiana',
                //                             'ME' => 'Maine',
                //                             'MD' => 'Maryland',
                //                             'MA' => 'Massachusetts',
                //                             'MI' => 'Michigan',
                //                             'MN' => 'Minnesota',
                //                             'MS' => 'Mississippi',
                //                             'MO' => 'Missouri',
                //                             'MT' => 'Montana',
                //                             'NE' => 'Nebraska',
                //                             'NV' => 'Nevada',
                //                             'NH' => 'New Hampshire',
                //                             'NJ' => 'New Jersey',
                //                             'NM' => 'New Mexico',
                //                             'NY' => 'New York',
                //                             'NC' => 'North Carolina',
                //                             'ND' => 'North Dakota',
                //                             'OH' => 'Ohio',
                //                             'OK' => 'Oklahoma',
                //                             'OR' => 'Oregon',
                //                             'PA' => 'Pennsylvania',
                //                             'RI' => 'Rhode Island',
                //                             'SC' => 'South Carolina',
                //                             'SD' => 'South Dakota',
                //                             'TN' => 'Tennessee',
                //                             'TX' => 'Texas',
                //                             'UT' => 'Utah',
                //                             'VT' => 'Vermont',
                //                             'VA' => 'Virginia',
                //                             'WA' => 'Washington',
                //                             'WV' => 'West Virginia',
                //                             'WI' => 'Wisconsin',
                //                             'WY' => 'Wyoming',
                //                         ])
                //                         ->required()
                //                         ->searchable()
                //                         ->prefixIcon('heroicon-o-flag')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     TextInput::make('zip')
                //                         ->label('ZIP Code')
                //                         ->placeholder('90001')
                //                         ->required()
                //                         ->maxLength(10)
                //                         ->prefixIcon('heroicon-o-hashtag')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                 ]),
                //         ]),

                //     Wizard\Step::make('Business Information')
                //         ->description('Details about your gym business')
                //         ->icon('heroicon-o-building-storefront')
                //         ->schema([
                //             TextInput::make('gym_name')
                //                 ->label('Gym Name')
                //                 ->placeholder('PowerFit Gym')
                //                 ->required()
                //                 ->maxLength(100)
                //                 ->prefixIcon('heroicon-o-building-storefront')
                //                 ->extraAttributes(['class' => 'w-full']),
                //             Grid::make(2)
                //                 ->schema([
                //                     Select::make('business_type')
                //                         ->label('Business Type')
                //                         ->options([
                //                             'traditional' => 'Traditional Gym',
                //                             'crossfit' => 'CrossFit Box',
                //                             'yoga' => 'Yoga Studio',
                //                             'pilates' => 'Pilates Studio',
                //                             'martial_arts' => 'Martial Arts',
                //                             'boutique' => 'Boutique Fitness',
                //                             'powerlifting' => 'Powerlifting Gym',
                //                             'bodybuilding' => 'Bodybuilding Gym',
                //                             'functional' => 'Functional Training',
                //                             'other' => 'Other',
                //                         ])
                //                         ->required()
                //                         ->searchable()
                //                         ->prefixIcon('heroicon-o-building-office')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     TextInput::make('years_in_business')
                //                         ->label('Years in Business')
                //                         ->placeholder('5')
                //                         ->numeric()
                //                         ->minValue(0)
                //                         ->maxValue(50)
                //                         ->suffix('years')
                //                         ->prefixIcon('heroicon-o-calendar')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                 ]),
                //             Textarea::make('business_description')
                //                 ->label('Business Description')
                //                 ->placeholder('Tell us about your gym, services offered, target audience, etc.')
                //                 ->rows(3)
                //                 ->minLength(10)
                //                 ->maxLength(500)
                //                 ->columnSpanFull()
                //                 ->extraAttributes(['class' => 'w-full']),
                //         ]),

                //     Wizard\Step::make('Additional Information')
                //         ->description('Optional information to help us serve you better')
                //         ->icon('heroicon-o-document-text')
                //         ->schema([
                //             Grid::make(2)
                //                 ->schema([
                //                     TextInput::make('website')
                //                         ->label('Website URL')
                //                         ->placeholder('https://yourgymnamehere.com')
                //                         ->url()
                //                         ->prefixIcon('heroicon-o-globe-alt')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                     Select::make('gym_size')
                //                         ->label('Gym Size')
                //                         ->options([
                //                             'small' => 'Small (< 2,000 sq ft)',
                //                             'medium' => 'Medium (2,000-5,000 sq ft)',
                //                             'large' => 'Large (5,000-10,000 sq ft)',
                //                             'extra_large' => 'Extra Large (> 10,000 sq ft)',
                //                         ])
                //                         ->prefixIcon('heroicon-o-squares-2x2')
                //                         ->extraAttributes(['class' => 'w-full']),
                //                 ]),
                //             Textarea::make('special_requirements')
                //                 ->label('Special Requirements or Notes')
                //                 ->placeholder('Any specific needs, requests, or additional information...')
                //                 ->rows(3)
                //                 ->maxLength(500)
                //                 ->columnSpanFull()
                //                 ->extraAttributes(['class' => 'w-full']),
                //         ]),
                // ])
                //     ->submitAction(
                //         ActionsAction::make('submit')
                //             ->label('Submit Registration')
                //             ->action('submit')
                //             ->color('primary')
                //             ->icon('heroicon-o-check-circle')
                //     )
                //     ->extraAttributes(['class' => 'w-full max-w-3xl mx-auto'])
                //     ->skippable(),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();

        // Here you would typically save the data to your database
        // For now, we'll just show a success notification

        Notification::make()
            ->title('Registration Submitted Successfully!')
            ->body('Thank you for registering your gym. We will review your application and contact you soon.')
            ->success()
            ->send();

        // You can redirect or perform other actions here
        // return redirect()->route('registration.success');

        // For demonstration, we'll still dump the data
        dd($data);
    }

    #[Layout('frontend.layouts.app')]
    public function render()
    {
        return view('livewire.gym-owner-register');
    }
}
