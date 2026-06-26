<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\ReportGoalResource\Pages\ListReportGoals;
use App\Filament\Admin\Resources\ReportGoalResource\Pages\CreateReportGoal;
use App\Filament\Admin\Resources\ReportGoalResource\Pages\EditReportGoal;
use App\Models\Gym;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\BaseResources\BaseResource;
use App\Models\ReportGoal;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportGoalResource extends BaseResource
{
    protected static ?string $model = ReportGoal::class;

    protected static ?string $navigationIcon = 'phosphor-target';

    protected static ?string $navigationLabel = 'Goals';

    protected static ?string $modelLabel = 'Goal';

    protected static ?string $pluralModelLabel = 'Goals';

    protected static ?string $cluster = Reports::class;

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Goal Details')
                    ->schema([
                        Select::make('metric_type')
                            ->label('Metric')
                            ->options(ReportGoal::getMetricTypes())
                            ->required()
                            ->native(false),

                        Select::make('period')
                            ->label('Period Type')
                            ->options(ReportGoal::getPeriodTypes())
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Auto-set period dates based on selection
                                $now = now();
                                match ($state) {
                                    'daily' => $set('period_start', $now->format('Y-m-d')) && $set('period_end', $now->format('Y-m-d')),
                                    'weekly' => $set('period_start', $now->startOfWeek()->format('Y-m-d')) && $set('period_end', $now->endOfWeek()->format('Y-m-d')),
                                    'monthly' => $set('period_start', $now->startOfMonth()->format('Y-m-d')) && $set('period_end', $now->endOfMonth()->format('Y-m-d')),
                                    'quarterly' => $set('period_start', $now->startOfQuarter()->format('Y-m-d')) && $set('period_end', $now->endOfQuarter()->format('Y-m-d')),
                                    'yearly' => $set('period_start', $now->startOfYear()->format('Y-m-d')) && $set('period_end', $now->endOfYear()->format('Y-m-d')),
                                    default => null,
                                };
                            }),

                        TextInput::make('target_value')
                            ->label('Target Value')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Get $get) => $get('metric_type') === 'revenue' ? '₹' : null)
                            ->suffix(fn (Get $get) => $get('metric_type') === 'retention' ? '%' : null),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active goals are tracked'),
                    ])
                    ->columns(2),

                Section::make('Period')
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Start Date')
                            ->required()
                            ->native(false),

                        DatePicker::make('period_end')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->after('period_start'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes (Optional)')
                            ->rows(3)
                            ->placeholder('Add any notes about this goal...'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('metric_type')
                    ->label('Metric')
                    ->formatStateUsing(fn ($state) => ReportGoal::getMetricTypes()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target_value')
                    ->label('Target')
                    ->formatStateUsing(function ($state, ReportGoal $record) {
                        if ($record->metric_type === 'revenue') {
                            return '₹' . number_format($state, 0);
                        }
                        if ($record->metric_type === 'retention') {
                            return $state . '%';
                        }

                        return number_format($state, 0);
                    })
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Period')
                    ->formatStateUsing(fn ($state) => ReportGoal::getPeriodTypes()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('period_start')
                    ->label('Start')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label('End')
                    ->date('M d, Y')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('phosphor-check-circle-fill')
                    ->falseIcon('phosphor-x-circle-fill')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('metric_type')
                    ->label('Metric')
                    ->options(ReportGoal::getMetricTypes()),

                SelectFilter::make('period')
                    ->options(ReportGoal::getPeriodTypes()),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('period_start', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportGoals::route('/'),
            'create' => CreateReportGoal::route('/create'),
            'edit' => EditReportGoal::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('gym_id', $gym?->id);
    }
}
