<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories;
use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages\CreateExpenseCategory;
use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages\EditExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Gym;
use App\Enums\DefaultRole;
use App\Enums\FilamentColor;
use App\Filament\BaseResources\BaseResource;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ExpenseCategoryResource extends BaseResource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationLabel = 'Expense Categories';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'reorder',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function form(Form $form): Form
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Section::make('Category Details')
                    ->icon('phosphor-folder-open')
                    ->description('Create a custom expense category for your gym')
                    ->schema([
                        Hidden::make('gym_id')
                            ->default($gym->id),

                        Hidden::make('is_system')
                            ->default(false),

                        Select::make('parent_id')
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

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Custom Category')
                            ->autofocus(),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->placeholder('Optional description for this category')
                            ->rows(3)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('icon')
                                    ->label('Icon')
                                    ->placeholder('phosphor-tag')
                                    ->default('phosphor-tag')
                                    ->helperText('Use Phosphor icon name'),

                                Select::make('color')
                                    ->label('Badge Color')
                                    ->options(FilamentColor::toSelectOptions())
                                    ->default('gray'),
                            ]),

                        Toggle::make('is_active')
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
                TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width(50),

                IconColumn::make('icon')
                    ->label('')
                    ->icon(fn ($state) => $state ?? 'phosphor-tag')
                    ->color(fn ($record) => $record->color ?? 'gray')
                    ->size('lg'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->parent?->name),

                TextColumn::make('is_system')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'System Default' : 'Custom')
                    ->color(fn ($state) => $state ? 'info' : 'success')
                    ->icon(fn ($state) => $state ? 'phosphor-lock-simple' : 'phosphor-user'),

                TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('color')
                    ->label('Color')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'gray'))
                    ->color(fn ($state) => $state ?? 'gray'),

                TextColumn::make('expenses_count')
                    ->counts('expenses')
                    ->label('Expenses')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Sub-categories')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled(fn ($record) => $record->is_system),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name')
                    ->preload()
                    ->placeholder('All categories'),
            ])
            ->actions([
                ViewAction::make()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->visible(fn ($record) => $record->is_system),
                EditAction::make()
                    ->icon('phosphor-pencil')
                    ->color('primary')
                    ->visible(fn ($record) => ! $record->is_system),
                DeleteAction::make()
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->is_system)
                    ->before(function (ExpenseCategory $record, DeleteAction $action) {
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
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records, DeleteBulkAction $action) {
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
            'index' => ListExpenseCategories::route('/'),
            'create' => CreateExpenseCategory::route('/create'),
            'edit' => EditExpenseCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(ExpenseCategory::NAVIGATION_BADGE_CACHE_KEY . $gym?->id, [3600, 86400], function () use ($gym) {
            return (string) static::getModel()::customForGym($gym?->id ?? 0)->count();
        }) ?: null;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Gym $gym */
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
