<?php

namespace App\Filament\Admin\Resources;

use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use App\Filament\Admin\Resources\ReceptionistResource\Pages;
use App\Filament\Admin\Resources\ReceptionistResource\RelationManagers;
use App\Forms\Components\AddressForm;
use App\Models\EmployeeDetail;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ReceptionistResource extends Resource
{
    use HasWizard;

    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $tenantOwnershipRelationshipName = 'gyms';

    public static function getLabel(): string
    {
        return 'Receptionist';
    }

    public static function getPluralLabel(): string
    {
        return 'Receptionists';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['gym_owner']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employeeDetails', 'address']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make(self::getSteps())
                    ->columnSpanFull()
                    ->skippable()
                    ->submitAction(
                        Action::make('create')
                            ->label('Save')
                            ->icon('heroicon-o-check-circle')
                            ->submit('create')
                            ->keyBindings(['mod+s'])
                            ->extraAttributes([
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'create',
                            ])
                    )
                    ->contained(true)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'DESC');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceptionists::route('/'),
            'create' => Pages\CreateReceptionist::route('/create'),
            'edit' => Pages\EditReceptionist::route('/{record}/edit'),
        ];
    }

    public static function getSteps()
    {
        return [
            Wizard\Step::make('Personal Information')
                ->icon('heroicon-o-user')
                ->completedIcon('heroicon-o-check')
                ->description('Basic personal details')
                ->schema(self::getPersonalInformationSchema()),

            Wizard\Step::make('Employment Details')
                ->icon('heroicon-o-briefcase')
                ->completedIcon('heroicon-o-check')
                ->description('Job-related information')
                ->schema(self::getEmployeeDetailsSchema()),

            Wizard\Step::make('Account Setup')
                ->icon('heroicon-o-lock-closed')
                ->completedIcon('heroicon-o-check')
                ->description('Login credentials and final setup')
                ->schema(self::getAccountSetupSchema()),
        ];
    }

    public static function getPersonalInformationSchema(): array
    {
        return [
            Section::make('Personal Details')
                ->description('Enter the receptionists personal information')
                ->icon('heroicon-o-identification')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(50)
                                ->placeholder('Enter full name')
                                ->prefixIcon('heroicon-o-user')
                                ->autocomplete('given-name'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique(User::class, 'email', ignoreRecord: true)
                                ->maxLength(100)
                                ->placeholder('receptionist@gym.com')
                                ->prefixIcon('heroicon-o-envelope')
                                ->autocomplete('email')
                                ->columnSpan(1),

                            PhoneInput::make('phone')
                                ->defaultCountry('IN')
                                ->required()
                                ->validateFor(
                                    country: ['IN', 'PK', 'BD', 'US'], // default: 'AUTO'
                                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE], // default: null
                                    lenient: true, // default: false
                                )
                        ]),

                    Grid::make(3)
                        ->schema([
                            Forms\Components\DatePicker::make('employeeDetails.date_of_birth')
                                ->label('Date of Birth')
                                ->required()
                                ->maxDate(now()->subYears(16))
                                ->displayFormat('M d, Y')
                                ->prefixIcon('heroicon-o-calendar-days')
                                ->native(false),

                            Forms\Components\Select::make('employeeDetails.gender')
                                ->label('Gender')
                                ->required()
                                ->options(GenderEnum::class)
                                ->prefixIcon('heroicon-o-users'),

                            Forms\Components\Select::make('employeeDetails.marital_status')
                                ->label('Marital Status')
                                ->options(MaritalStatusEnum::class)
                                ->prefixIcon('heroicon-o-heart'),
                        ]),
                ]),

            Section::make('Emergency Contact')
                ->description('Person to contact in case of emergency')
                ->icon('heroicon-o-phone')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('employeeDetails.emergency_contact_name')
                                ->label('Emergency Contact Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('John Doe')
                                ->prefixIcon('heroicon-o-user'),

                            Forms\Components\Select::make('employeeDetails.emergency_contact_relationship')
                                ->label('Relationship')
                                ->required()
                                ->options([
                                    'spouse' => 'Spouse',
                                    'parent' => 'Parent',
                                    'sibling' => 'Sibling',
                                    'child' => 'Child',
                                    'friend' => 'Friend',
                                    'relative' => 'Relative',
                                    'other' => 'Other',
                                ])
                                ->prefixIcon('heroicon-o-heart'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            PhoneInput::make('employeeDetails.emergency_contact_phone')
                                ->defaultCountry('IN')
                                ->required()
                                ->validateFor(
                                    country: ['IN', 'PK', 'BD', 'US'], // default: 'AUTO'
                                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                                    lenient: true,
                                ),

                            Forms\Components\TextInput::make('employeeDetails.emergency_contact_email')
                                ->label('Emergency Contact Email')
                                ->email()
                                ->maxLength(100)
                                ->placeholder('emergency@example.com')
                                ->prefixIcon('heroicon-o-envelope'),
                        ]),
                ]),

            Section::make('Address Information')
                ->description('Residential address details')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    AddressForm::make('address')
                        ->columnSpan('full')
                ]),
        ];
    }

    public static function getEmployeeDetailsSchema(): array
    {
        return [
            Section::make('Position Information')
                ->description('Employment and role details')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('employeeDetails.hire_date')
                                ->label('Hire Date')
                                ->required()
                                ->default(now())
                                ->maxDate(now())
                                ->displayFormat('M d, Y')
                                ->prefixIcon('heroicon-o-calendar')
                                ->native(false),
                        ]),

                    Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('employeeDetails.employment_type')
                                ->label('Employment Type')
                                ->required()
                                ->options([
                                    'full_time' => 'Full Time',
                                    'part_time' => 'Part Time',
                                    'contract' => 'Contract',
                                    'temporary' => 'Temporary',
                                ])
                                ->default('full_time')
                                ->prefixIcon('heroicon-o-clock'),

                            Forms\Components\TextInput::make('employeeDetails.monthly_salary')
                                ->label('Monthly Salary')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.01)
                                ->minValue(0)
                                ->placeholder('3000.00')
                                ->hint('Fixed monthly salary (if applicable)'),
                        ]),
                ]),

            Section::make('Skills')
                ->description('Receptionist capabilities')
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\CheckboxList::make('employeeDetails.skills')
                        ->label('Skills & Competencies')
                        ->options([
                            'customer_service' => 'Customer Service Excellence',
                            'phone_handling' => 'Phone Handling & Communication',
                            'appointment_scheduling' => 'Appointment Scheduling',
                            'payment_processing' => 'Payment Processing',
                            'membership_management' => 'Membership Management',
                            'conflict_resolution' => 'Conflict Resolution',
                            'multilingual' => 'Multilingual Communication',
                            'computer_skills' => 'Computer & Software Skills',
                            'cash_handling' => 'Cash Handling',
                            'first_aid' => 'First Aid Certified',
                        ])
                        ->columns(2)
                        ->gridDirection('row'),
                ]),
        ];
    }

    public static function getAccountSetupSchema()
    {
        return [
            Section::make('Login Credentials')
                ->description('Set up login access for the receptionist')
                ->icon('heroicon-o-key')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('password')
                                ->label('Password')
                                ->revealable()
                                ->password()
                                ->required(fn(string $operation): bool => $operation === 'create')
                                ->minLength(8)
                                ->rules([
                                    fn($state) => filled($state) ? 'regex:/[A-Z]/' : '',
                                    fn($state) => filled($state) ? 'regex:/[a-z]/' : '',
                                    fn($state) => filled($state) ? 'regex:/[0-9]/' : '',
                                    fn($state) => filled($state) ? 'regex:/[!@#$%^&*(),.?":{}|<>]/' : '',
                                ])
                                ->prefixIcon('heroicon-o-lock-closed')
                                ->validationMessages([
                                    'regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
                                ])
                                ->hint('Need a strong password?')
                                ->hintAction(
                                    Action::make('generatePassword')
                                        ->label('Generate')
                                        ->icon('heroicon-o-key')
                                        ->action(function (Set $set) {
                                            $password = Str::password(12);
                                            $set('password', $password);
                                            $set('passwordConfirmation', $password);
                                        })
                                ),

                            Forms\Components\TextInput::make('passwordConfirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->required(fn(string $operation): bool => $operation === 'create')
                                ->minLength(8)
                                ->same('password')
                                ->dehydrated(false)
                                ->revealable()
                                ->prefixIcon('heroicon-o-lock-closed'),
                        ])
                ]),

            Section::make('Account Settings')
                ->description('Additional account configuration')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('is_active')
                                ->label('Account Active')
                                ->helperText('Enable/disable account access')
                                ->default(true)
                                ->onIcon('heroicon-o-check-circle')
                                ->offIcon('heroicon-o-x-circle'),

                            Forms\Components\Toggle::make('email_verified')
                                ->label('Email Verified')
                                ->helperText('Mark email as verified')
                                ->default(false)
                                ->onIcon('heroicon-o-check-badge')
                                ->offIcon('heroicon-o-exclamation-triangle'),
                        ]),

                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes')
                        ->placeholder('Any additional information about this receptionist')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
