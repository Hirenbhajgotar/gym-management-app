<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Filament\Admin\Resources\ExpenseBudgetResource\Pages;
use App\Models\ExpenseBudget;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseBudgetResource extends Resource
{
    protected static ?string $model = ExpenseBudget::class;

    protected static ?string $navigationIcon = 'phosphor-chart-pie-slice';

    protected static ?string $navigationLabel = 'Budgets';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('Budget Details')
                    ->icon('phosphor-chart-pie-slice')
                    ->description('Create or edit expense budget')
                    ->schema([
                        Forms\Components\Hidden::make('gym_id')
                            ->default($gym->id),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Utilities Budget')
                            ->autofocus(),

                        Forms\Components\Select::make('expense_category_id')
                            ->label('Category (Optional)')
                            ->relationship(
                                'category',
                                'name',
                                fn ($query) => $query->where('gym_id', $gym->id)->active()->whereNull('parent_id')
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('All Categories')
                            ->helperText('Leave empty for overall budget'),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->placeholder('Optional description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->step(0.01)
                                    ->prefix(fn ($get) => Currency::tryFrom($get('currency') ?? 'INR')?->getSymbol() ?? '₹')
                                    ->placeholder('0.00'),

                                Forms\Components\Select::make('currency')
                                    ->options(Currency::toSelectOptions())
                                    ->default('INR')
                                    ->required()
                                    ->reactive(),

                                Forms\Components\Select::make('period')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->default('monthly')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->required()
                                    ->default(now()->startOfMonth())
                                    ->native(false),

                                Forms\Components\DatePicker::make('end_date')
                                    ->required()
                                    ->default(now()->endOfMonth())
                                    ->native(false)
                                    ->afterOrEqual('start_date'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('alert_threshold')
                                    ->label('Alert at (%)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(80)
                                    ->suffix('%')
                                    ->helperText('Send alert when budget usage reaches this percentage'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                            ]),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray')
                    ->placeholder('All Categories'),

                Tables\Columns\TextColumn::make('period')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Budget')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->sortable(),

                Tables\Columns\ViewColumn::make('progress')
                    ->label('Usage')
                    ->view('filament.tables.columns.budget-progress'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => $record->start_date->format('d M') . ' - ' . $record->end_date->format('d M Y'))
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('period')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ]),

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),

                Tables\Filters\Filter::make('current')
                    ->label('Current Period Only')
                    ->query(fn ($query) => $query->where('start_date', '<=', now())->where('end_date', '>=', now()))
                    ->toggle(),

                Tables\Filters\Filter::make('exceeded')
                    ->label('Exceeded Budgets')
                    ->query(function ($query) {
                        return $query->get()->filter(fn ($budget) => $budget->isExceeded());
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('phosphor-pencil'),
                Tables\Actions\DeleteAction::make()
                    ->icon('phosphor-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->striped();
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
            'index' => Pages\ListExpenseBudgets::route('/'),
            'create' => Pages\CreateExpenseBudget::route('/create'),
            'edit' => Pages\EditExpenseBudget::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        // Count budgets that have exceeded threshold
        $exceededCount = static::getModel()::where('gym_id', $gym?->id)
            ->active()
            ->current()
            ->get()
            ->filter(fn ($budget) => $budget->isThresholdExceeded())
            ->count();

        return $exceededCount > 0 ? (string) $exceededCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
