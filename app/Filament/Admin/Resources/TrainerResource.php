<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Filament\Admin\Resources\TrainerResource\Pages;
use App\Filament\Exports\TrainerExporter;
use App\Models\Trainer;
use App\Tables\Columns\AssignedMembersColumn;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class TrainerResource extends Resource
{
    protected static ?string $model = Trainer::class;

    protected static ?string $navigationGroup = 'Users';

    public static function canAccess(array $parameters = []): bool
    {
        if (filament()->getId() === 'admin' || filament()->getId() === 'staff') {
            // return $user->hasRole('super_admin') || ($user->hasRole('gym_owner'));
            return true;
        }

        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(Trainer::NAVIGATION_BADGE_CACHE_KEY . Filament::getTenant()->id, [now()->addHours(6), now()->addWeek()], function () {
            $modelClass = static::$model;
            $query = $modelClass::query();

            return $query->count();
        });
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     $query = parent::getEloquentQuery()
    //         ->select('trainers.*')
    //         ->join('gyms', function ($join) {
    //             $join->on('trainers.gym_id', '=', 'gyms.id');
    //         })
    //         ->with(['gym', 'members'])
    //         ->withCount('members');

    //     if (auth()->user()->hasRole('gym_owner')) {
    //         $query->whereIn('gyms.owner_id', [auth()->id()]);
    //     }

    //     return $query;
    // }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            // Basic Information Section
            Forms\Components\Section::make('Basic Information')
                ->description('Enter trainer\'s personal details')
                ->schema([
                    Forms\Components\FileUpload::make('profile_photo')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->circleCropper()
                        ->directory('employee-photos')
                        ->maxSize(2048)
                        ->columnSpanFull(),

                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter full name')
                                ->prefixIcon('heroicon-o-user')
                                ->autocomplete('given-name'),
                        ]),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(Trainer::class, 'email', ignoreRecord: true)
                        ->placeholder('trainer@example.com')
                        ->prefixIcon('heroicon-m-envelope')
                        ->columnSpan(1),

                    PhoneInput::make('phone')
                        ->defaultCountry('IN')
                        ->required()
                        ->validateFor(
                            country: ['IN', 'PK', 'BD', 'US'],
                            type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                            lenient: true,
                        ),
                ])
                ->columns(2)
                ->collapsible(),

            // Professional Information Section
            Forms\Components\Section::make('Professional Information')
                ->description('Training specialization and experience details')
                ->schema([
                    Forms\Components\Select::make('specialization')
                        ->label('Primary Specialization')
                        ->options([
                            'Strength Training' => 'Strength Training',
                            'Yoga' => 'Yoga',
                            'Cardio' => 'Cardio',
                            'CrossFit' => 'CrossFit',
                            'Pilates' => 'Pilates',
                            'Functional Training' => 'Functional Training',
                            'Martial Arts' => 'Martial Arts',
                            'Dance Fitness' => 'Dance Fitness',
                            'Nutrition Coaching' => 'Nutrition Coaching',
                            'Rehabilitation' => 'Rehabilitation',
                        ])
                        ->searchable()
                        ->placeholder('Select specialization')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('experience_years')
                        ->label('Years of Experience')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->step(1)
                        ->suffix('years')
                        ->placeholder('0')
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('certifications')
                        ->label('Certifications')
                        ->placeholder('List all relevant certifications (e.g., NASM-CPT, ACE, ACSM)')
                        ->maxLength(500)
                        ->rows(3)
                        ->columnSpan(2),
                ])
                ->columns(2)
                ->collapsible(),

            // Availability Section
            Forms\Components\Section::make('Availability')
                ->description('Set trainer availability and working hours')
                ->schema([
                    Forms\Components\Toggle::make('is_available')
                        ->label('Currently Available')
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('available_hours')
                        ->label('Available Hours')
                        ->placeholder('e.g., 9:00 AM - 5:00 PM, Monday-Friday')
                        ->maxLength(255)
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsible(),

            // Compensation Section
            Forms\Components\Section::make('Compensation')
                ->description('Salary and payment information')
                ->schema([
                    Forms\Components\Select::make('currency')
                        ->options(collect(Currency::cases())->mapWithKeys(
                            fn (Currency $currency) => [$currency->value => $currency->getLabel()]
                        ))
                        ->required()
                        ->default(Currency::INR->value)
                        ->reactive()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('basic_salary')
                        ->label('Basic Salary')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->step(0.01)
                        ->prefix(fn ($get) => Currency::from($get('currency') ?? Currency::INR->value)->getSymbol())
                        ->placeholder('0.00')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('pt_salary_per_member')
                        ->label('Personal Training Rate (Per Member)')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->step(0.01)
                        ->prefix(fn ($get) => Currency::from($get('currency') ?? Currency::INR->value)->getSymbol())
                        ->placeholder('0.00')
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsible(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->circular()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->label(''),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('specialization'),
                Tables\Columns\TextColumn::make('experience_years')
                    ->label('Experience (Years)'),

                AssignedMembersColumn::make('assigned_members')
                    ->label('Assigned Members'),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('pt_salary_per_member')
                    ->label('PT Salary/Member')
                    ->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('total_earnings')
                    ->label('Total Earnings')
                    ->money(fn ($record) => $record->currency)
                    ->getStateUsing(fn ($record) => $record->total_earnings),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_available'),

                Tables\Filters\Filter::make('basic_salary')
                    ->form([
                        Forms\Components\TextInput::make('basic_salary_min')
                            ->numeric()
                            ->label('Min Salary'),
                        Forms\Components\TextInput::make('basic_salary_max')
                            ->numeric()
                            ->label('Max Salary'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['basic_salary_min'], fn ($q, $min) => $q->where('basic_salary', '>=', $min))
                            ->when($data['basic_salary_max'], fn ($q, $max) => $q->where('basic_salary', '<=', $max));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Export Trainers')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->exporter(TrainerExporter::class)
                        ->formats([ExportFormat::Xlsx]),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTrainers::route('/'),
            'create' => Pages\CreateTrainer::route('/create'),
            'edit' => Pages\EditTrainer::route('/{record}/edit'),
        ];
    }
}
