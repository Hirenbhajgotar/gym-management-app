<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\ExpenseBudgetResource\Pages\ListExpenseBudgets;
use App\Filament\Admin\Resources\ExpenseBudgetResource\Pages\CreateExpenseBudget;
use App\Filament\Admin\Resources\ExpenseBudgetResource\Pages\EditExpenseBudget;
use App\Models\Gym;
use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Models\ExpenseBudget;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ExpenseBudgetResource extends BaseResource
{
    protected static ?string $model = ExpenseBudget::class;

    protected static ?string $navigationLabel = 'Budgets';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'reorder',
            ],

            DefaultRole::RECEPTIONIST->value => ['view', 'view_any'],
            DefaultRole::TRAINER->value => ['view', 'view_any'],
        ];
    }

    public static function form(Form $form): Form
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Section::make('Budget Details')
                    ->icon('phosphor-chart-pie-slice')
                    ->description('Create or edit expense budget')
                    ->schema([
                        Hidden::make('gym_id')
                            ->default($gym->id),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Utilities Budget')
                            ->autofocus(),

                        Select::make('expense_category_id')
                            ->label('Category (Optional)')
                            ->options(fn () => ExpenseCategory::query()
                                ->availableForGym($gym->id)
                                ->active()
                                ->orderByDesc('is_system')
                                ->orderBy('order')
                                ->get()
                                ->mapWithKeys(fn ($cat) => [
                                    $cat->id => $cat->is_system
                                        ? "★ {$cat->name}"
                                        : $cat->name,
                                ]))
                            ->searchable()
                            ->preload()
                            ->placeholder('All Categories')
                            ->helperText('Leave empty for overall budget'),

                        Textarea::make('description')
                            ->maxLength(500)
                            ->placeholder('Optional description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->step(0.01)
                                    ->prefix(fn ($get) => Currency::tryFrom($get('currency') ?? 'INR')?->getSymbol() ?? '₹')
                                    ->placeholder('0.00'),

                                Select::make('currency')
                                    ->options(Currency::toSelectOptions())
                                    ->default('INR')
                                    ->required()
                                    ->reactive(),

                                Select::make('period')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->default('monthly')
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->required()
                                    ->default(now()->startOfMonth())
                                    ->native(false),

                                DatePicker::make('end_date')
                                    ->required()
                                    ->default(now()->endOfMonth())
                                    ->native(false)
                                    ->afterOrEqual('start_date'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('alert_threshold')
                                    ->label('Alert at (%)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(80)
                                    ->suffix('%')
                                    ->helperText('Send alert when budget usage reaches this percentage'),

                                Toggle::make('is_active')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray')
                    ->placeholder('All Categories'),

                TextColumn::make('period')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('amount')
                    ->label('Budget')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->sortable(),

                ViewColumn::make('progress')
                    ->label('Usage')
                    ->view('filament.tables.columns.budget-progress'),

                TextColumn::make('start_date')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => $record->start_date->format('d M') . ' - ' . $record->end_date->format('d M Y'))
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('period')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ]),

                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),

                Filter::make('current')
                    ->label('Current Period Only')
                    ->query(fn ($query) => $query->where('start_date', '<=', now())->where('end_date', '>=', now()))
                    ->toggle(),

                Filter::make('exceeded')
                    ->label('Exceeded Budgets')
                    ->query(function ($query) {
                        return $query->get()->filter(fn ($budget) => $budget->isExceeded());
                    })
                    ->toggle(),
            ])
            ->actions([
                EditAction::make()
                    ->icon('phosphor-pencil'),
                DeleteAction::make()
                    ->icon('phosphor-trash'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListExpenseBudgets::route('/'),
            'create' => CreateExpenseBudget::route('/create'),
            'edit' => EditExpenseBudget::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(ExpenseBudget::NAVIGATION_BADGE_CACHE_KEY . $gym?->id, [3600, 86400], function () use ($gym) {
            return (string) static::getModel()::where('gym_id', $gym?->id)
                ->active()
                ->current()
                ->get()
                ->filter(fn ($budget) => $budget->isThresholdExceeded())
                ->count();
        }) ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
