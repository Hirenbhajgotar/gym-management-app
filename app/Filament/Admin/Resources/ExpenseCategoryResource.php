<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationLabel = 'Expense Categories';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('Category Details')
                    ->icon('phosphor-folder-open')
                    ->description('Create a custom expense category for your gym')
                    ->schema([
                        Forms\Components\Hidden::make('gym_id')
                            ->default($gym->id),

                        Forms\Components\Hidden::make('is_system')
                            ->default(false),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(fn () => ExpenseCategory::query()
                                ->availableForGym($gym->id)
                                ->whereNull('parent_id')
                                ->active()
                                ->orderBy('order')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('None (Root Category)')
                            ->helperText('Leave empty for root category'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Custom Category')
                            ->autofocus(),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->placeholder('Optional description for this category')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('icon')
                                    ->label('Icon')
                                    ->placeholder('phosphor-tag')
                                    ->default('phosphor-tag')
                                    ->helperText('Use Phosphor icon name'),

                                Forms\Components\Select::make('color')
                                    ->label('Badge Color')
                                    ->options([
                                        'gray' => 'Gray',
                                        'primary' => 'Primary',
                                        'success' => 'Success (Green)',
                                        'warning' => 'Warning (Yellow)',
                                        'danger' => 'Danger (Red)',
                                        'info' => 'Info (Blue)',
                                    ])
                                    ->default('gray'),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->helperText('Inactive categories cannot be selected for new expenses'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width(50),

                Tables\Columns\IconColumn::make('icon')
                    ->label('')
                    ->icon(fn ($state) => $state ?? 'phosphor-tag')
                    ->color(fn ($record) => $record->color ?? 'gray')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->parent?->name),

                Tables\Columns\TextColumn::make('is_system')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'System Default' : 'Custom')
                    ->color(fn ($state) => $state ? 'info' : 'success')
                    ->icon(fn ($state) => $state ? 'phosphor-lock-simple' : 'phosphor-user'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('color')
                    ->label('Color')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'gray'))
                    ->color(fn ($state) => $state ?? 'gray'),

                Tables\Columns\TextColumn::make('expenses_count')
                    ->counts('expenses')
                    ->label('Expenses')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Sub-categories')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled(fn ($record) => $record->is_system),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name')
                    ->preload()
                    ->placeholder('All categories'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->visible(fn ($record) => $record->is_system),
                Tables\Actions\EditAction::make()
                    ->icon('phosphor-pencil')
                    ->color('primary')
                    ->visible(fn ($record) => ! $record->is_system),
                Tables\Actions\DeleteAction::make()
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->is_system)
                    ->before(function (ExpenseCategory $record, Tables\Actions\DeleteAction $action) {
                        if ($record->expenses()->exists()) {
                            $action->cancel();
                            $action->failureNotificationTitle('Cannot delete category with expenses');
                        }
                        if ($record->children()->exists()) {
                            $action->cancel();
                            $action->failureNotificationTitle('Cannot delete category with sub-categories');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records, Tables\Actions\DeleteBulkAction $action) {
                            // Filter out system defaults from bulk delete
                            $customRecords = $records->reject(fn ($record) => $record->is_system);
                            if ($customRecords->isEmpty()) {
                                $action->cancel();
                                $action->failureNotificationTitle('Cannot delete system default categories');
                            }
                        }),
                ]),
            ])
            ->defaultSort('is_system', 'desc')
            ->reorderable('order')
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
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(ExpenseCategory::NAVIGATION_BADGE_CACHE_KEY . $gym?->id, [now()->addHour(), now()->addDay()], function () use ($gym) {
            return (string) static::getModel()::customForGym($gym?->id ?? 0)->count();
        });
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return parent::getEloquentQuery()
            ->availableForGym($gym?->id ?? 0)
            ->orderByDesc('is_system')
            ->orderBy('order');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }
}
