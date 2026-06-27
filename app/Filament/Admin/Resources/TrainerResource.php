<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\TrainerResource\Pages\ManageTrainers;
use App\Filament\Admin\Resources\TrainerResource\Pages\CreateTrainer;
use App\Filament\Admin\Resources\TrainerResource\Pages\EditTrainer;
use App\Enums\Currency;
use App\Filament\Actions\ExportBulkAction;
use App\Filament\BaseResources\BaseResource;
use App\Filament\Exports\TrainerExporter;
use App\Models\Trainer;
use App\Rules\ValidEmailDomain;
use App\Tables\Columns\AssignedMembersColumn;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class TrainerResource extends BaseResource
{
    protected static ?string $model = Trainer::class;

    protected static ?string $navigationGroup = 'Users';

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(Trainer::NAVIGATION_BADGE_CACHE_KEY . Filament::getTenant()->id, [21600, 604800], function () {
            return (string) (static::$model)::query()->count();
        }) ?: null;
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
            Section::make('Basic Information')
                ->description('Enter trainer\'s personal details')
                ->schema([
                    FileUpload::make('profile_photo')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->circleCropper()
                        ->directory('employee-photos')
                        ->maxSize(2048)
                        ->columnSpanFull(),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter full name')
                                ->prefixIcon('heroicon-o-user')
                                ->autocomplete('given-name'),
                        ]),

                    TextInput::make('email')
                        ->email()
                        ->rule(new ValidEmailDomain)
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
            Section::make('Professional Information')
                ->description('Training specialization and experience details')
                ->schema([
                    Select::make('specialization')
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

                    TextInput::make('experience_years')
                        ->label('Years of Experience')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->step(1)
                        ->suffix('years')
                        ->placeholder('0')
                        ->columnSpan(1),

                    Textarea::make('certifications')
                        ->label('Certifications')
                        ->placeholder('List all relevant certifications (e.g., NASM-CPT, ACE, ACSM)')
                        ->maxLength(500)
                        ->rows(3)
                        ->columnSpan(2),
                ])
                ->columns(2)
                ->collapsible(),

            // Availability Section
            Section::make('Availability')
                ->description('Set trainer availability and working hours')
                ->schema([
                    Toggle::make('is_available')
                        ->label('Currently Available')
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),

                    TextInput::make('available_hours')
                        ->label('Available Hours')
                        ->placeholder('e.g., 9:00 AM - 5:00 PM, Monday-Friday')
                        ->maxLength(255)
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsible(),

            // Compensation Section
            Section::make('Compensation')
                ->description('Salary and payment information')
                ->schema([
                    Select::make('currency')
                        ->options(collect(Currency::cases())->mapWithKeys(
                            fn (Currency $currency) => [$currency->value => $currency->getLabel()]
                        ))
                        ->required()
                        ->default(Currency::INR->value)
                        ->reactive()
                        ->columnSpan(2),

                    TextInput::make('basic_salary')
                        ->label('Basic Salary')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->step(0.01)
                        ->prefix(fn ($get) => Currency::from($get('currency') ?? Currency::INR->value)->getSymbol())
                        ->placeholder('0.00')
                        ->columnSpan(1),

                    TextInput::make('pt_salary_per_member')
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
                ImageColumn::make('profile_photo')
                    ->circular()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->label(''),

                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('specialization'),
                TextColumn::make('experience_years')
                    ->label('Experience (Years)'),

                AssignedMembersColumn::make('assigned_members')
                    ->label('Assigned Members'),
                TextColumn::make('basic_salary')
                    ->money(fn ($record) => $record->currency),
                TextColumn::make('pt_salary_per_member')
                    ->label('PT Salary/Member')
                    ->money(fn ($record) => $record->currency),
                TextColumn::make('total_earnings')
                    ->label('Total Earnings')
                    ->money(fn ($record) => $record->currency)
                    ->getStateUsing(fn ($record) => $record->total_earnings),
                IconColumn::make('is_available')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_available'),

                Filter::make('basic_salary')
                    ->form([
                        TextInput::make('basic_salary_min')
                            ->numeric()
                            ->label('Min Salary'),
                        TextInput::make('basic_salary_max')
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
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Export Trainers')
                        ->exporter(TrainerExporter::class)
                        ->formats([ExportFormat::Xlsx]),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrainers::route('/'),
            'create' => CreateTrainer::route('/create'),
            'edit' => EditTrainer::route('/{record}/edit'),
        ];
    }
}
