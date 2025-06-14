<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Filament\Exports\TrainerExporter;
use App\Filament\Admin\Resources\TrainerResource\Pages;
use App\Models\Gym;
use App\Models\Trainer;
use App\Tables\Columns\AssignedMembersColumn;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainerResource extends Resource
{
    protected static ?string $model = Trainer::class;

    // protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationIcon = 'phosphor-person-simple-throw';

    protected static ?string $navigationGroup = 'Users';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        return $user->hasRole(['super_admin', 'gym_owner']);
    }

    public static function getNavigationBadge(): ?string
    {
        return cache()->remember('trainer_navigation_badge_count_' . auth()->id(), now()->addMinutes(15), function () {
            /** @var class-string<Model> $modelClass */
            $modelClass = static::$model;
            $query = $modelClass::query();
            if (auth()->user()->hasRole('gym_owner')) {
                $query->whereHas('gym', function ($q) {
                    $q->where('owner_id', auth()->id());
                });
            }
            return (string) $query->count();
        });
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->select('trainers.*')
            ->join('gyms', function ($join) {
                $join->on('trainers.gym_id', '=', 'gyms.id');
            })
            ->with(['gym', 'members'])
            ->withCount('members');

        if (auth()->user()->hasRole('gym_owner')) {
            $query->whereIn('gyms.owner_id', [auth()->id()]);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('gym_id')
                    ->relationship('gym', 'name')
                    ->required()
                    ->options(function () {
                        // Cache gym options to avoid duplicate queries
                        // return cache()->remember('trainers_gym_options_' . auth()->id(), now()->addHours(2), function () {
                        //     if (auth()->user()->hasRole('gym_owner')) {
                        //         return Gym::where('owner_id', auth()->id())->pluck('name', 'id');
                        //     }
                        //     return Gym::pluck('name', 'id');
                        // });

                        if (auth()->user()->hasRole('gym_owner')) {
                            return Gym::where('owner_id', auth()->id())->pluck('name', 'id');
                        }
                        return Gym::pluck('name', 'id');
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(Trainer::class, 'email', ignoreRecord: true),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Select::make('specialization')
                    ->options([
                        'Strength Training' => 'Strength Training',
                        'Yoga' => 'Yoga',
                        'Cardio' => 'Cardio',
                        'CrossFit' => 'CrossFit',
                    ]),
                Forms\Components\TextInput::make('experience_years')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(50),
                Forms\Components\TextInput::make('certifications')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_available')
                    ->default(true),
                Forms\Components\TextInput::make('available_hours')
                    ->placeholder('Enter the hours the trainer is available (e.g., 9 AM - 5 PM)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('basic_salary')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0.00),
                Forms\Components\TextInput::make('pt_salary_per_member')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0.00),
                Forms\Components\Select::make('currency')
                    ->options(collect(Currency::cases())->mapWithKeys(
                        fn(Currency $currency) => [$currency->value => $currency->getLabel()]
                    ))
                    ->required()
                    ->default(Currency::INR->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gym.name')
                    ->sortable(),
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
                    ->money(fn($record) => $record->currency),
                Tables\Columns\TextColumn::make('pt_salary_per_member')
                    ->label('PT Salary/Member')
                    ->money(fn($record) => $record->currency),
                Tables\Columns\TextColumn::make('total_earnings')
                    ->label('Total Earnings')
                    ->money(fn($record) => $record->currency)
                    ->getStateUsing(fn($record) => $record->total_earnings),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_available'),
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name', function ($query) {
                        if (auth()->user()->hasRole('gym_owner')) {
                            return $query->whereIn('id', auth()->user()?->gyms()->pluck('id'));
                        }
                        if (auth()->user()->hasRole('super_admin')) {
                            return $query;
                        }
                        // Cache gym options for the filter
                        // return cache()->remember('trainers_gym_filter_options_' . auth()->id(), now()->addHours(2), function () use ($query) {
                        //     if (auth()->user()->hasRole('gym_owner')) {
                        //         return $query->whereIn('id', auth()->user()?->gyms()->pluck('id'));
                        //     }
                        //     if (auth()->user()->hasRole('super_admin')) {
                        //         return $query;
                        //     }
                        // });
                    })
                    ->label('Gym'),
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
                            ->when($data['basic_salary_min'], fn($q, $min) => $q->where('basic_salary', '>=', $min))
                            ->when($data['basic_salary_max'], fn($q, $max) => $q->where('basic_salary', '<=', $max));
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
        ];
    }
}
