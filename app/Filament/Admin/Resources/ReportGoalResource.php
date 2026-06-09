<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Resources\ReportGoalResource\Pages;
use App\Filament\BaseResources\BaseResource;
use App\Models\ReportGoal;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
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
                Forms\Components\Section::make('Goal Details')
                    ->schema([
                        Forms\Components\Select::make('metric_type')
                            ->label('Metric')
                            ->options(ReportGoal::getMetricTypes())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('period')
                            ->label('Period Type')
                            ->options(ReportGoal::getPeriodTypes())
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
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

                        Forms\Components\TextInput::make('target_value')
                            ->label('Target Value')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Forms\Get $get) => $get('metric_type') === 'revenue' ? '₹' : null)
                            ->suffix(fn (Forms\Get $get) => $get('metric_type') === 'retention' ? '%' : null),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active goals are tracked'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Period')
                    ->schema([
                        Forms\Components\DatePicker::make('period_start')
                            ->label('Start Date')
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->after('period_start'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
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
                Tables\Columns\TextColumn::make('metric_type')
                    ->label('Metric')
                    ->formatStateUsing(fn ($state) => ReportGoal::getMetricTypes()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_value')
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

                Tables\Columns\TextColumn::make('period')
                    ->label('Period')
                    ->formatStateUsing(fn ($state) => ReportGoal::getPeriodTypes()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Start')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('End')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('phosphor-check-circle-fill')
                    ->falseIcon('phosphor-x-circle-fill')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metric_type')
                    ->label('Metric')
                    ->options(ReportGoal::getMetricTypes()),

                Tables\Filters\SelectFilter::make('period')
                    ->options(ReportGoal::getPeriodTypes()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            ->defaultSort('period_start', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportGoals::route('/'),
            'create' => Pages\CreateReportGoal::route('/create'),
            'edit' => Pages\EditReportGoal::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('gym_id', $gym?->id);
    }
}
