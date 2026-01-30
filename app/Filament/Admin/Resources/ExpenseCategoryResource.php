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

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'phosphor-folder-open';

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
                    ->description('Create or edit expense category')
                    ->schema([
                        Forms\Components\Hidden::make('gym_id')
                            ->default($gym->id),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship(
                                'parent',
                                'name',
                                fn ($query) => $query->where('gym_id', $gym->id)->whereNull('parent_id')
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Root Category)')
                            ->helperText('Leave empty for root category'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Utilities, Equipment')
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

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('color')
                    ->label('Color')
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
                    ->offColor('danger'),
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
                Tables\Actions\EditAction::make()
                    ->icon('phosphor-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->icon('phosphor-trash')
                    ->color('danger')
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order')
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

        return (string) static::getModel()::where('gym_id', $gym?->id)->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }
}
