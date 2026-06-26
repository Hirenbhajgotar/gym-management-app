<?php

namespace App\Filament\SuperAdmin\Resources;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\SuperAdmin\Resources\PlanResource\Pages\EditPlan;
use App\Filament\SuperAdmin\Resources\PlanResource\Pages\ListPlans;
use App\Models\Plan;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlanResource extends BaseResource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 3;

    // Restrict access to admins only
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(Plan::NAVIGATION_BADGE_CACHE_KEY, [21600, 604800], function () {
            return (string) static::getModel()::count();
        }) ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Plan Management')
                ->tabs([
                    Tab::make('Basic Information')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->reactive()
                                ->label('Plan Name')
                                ->placeholder('Enter plan name')
                                ->helperText(
                                    'A unique name for this subscription plan',
                                )
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (
                                    $state,
                                    callable $set,
                                ) {
                                    $set('slug', Str::slug($state));
                                }),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    Plan::class,
                                    'slug',
                                    ignoreRecord: true,
                                )
                                ->helperText(
                                    'Auto-generated from plan name, but you can edit it',
                                )
                                // ->reactive()
                                ->afterStateHydrated(function (
                                    $state,
                                    callable $set,
                                    $record,
                                ) {
                                    if (! $state && $record) {
                                        $set('slug', Str::slug($record->name));
                                    }
                                }),
                            TextInput::make('badge')
                                ->maxLength(255)
                                ->helperText(
                                    'Optional badge text like "Popular" or "Best Value"',
                                ),
                            RichEditor::make('description')
                                ->maxLength(5000)
                                ->columnSpanFull()
                                ->label('Plan Description')
                                ->placeholder(
                                    'Describe the benefits of this plan',
                                )
                                ->hint('Preview available below')
                                ->live(onBlur: true),
                            Toggle::make('is_enterprise')
                                ->label('Enterprise Plan')
                                ->helperText('Check this for enterprise pricing (Contact Us)')
                                ->reactive(),
                        ])
                        ->columns(2),
                    Tab::make('Pricing')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make(
                                    'price_monthly',
                                )
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->label('Monthly Price')
                                    ->placeholder('0.00')
                                    ->helperText('Monthly subscription price'),
                                TextInput::make('price_yearly')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->label('Yearly Price')
                                    ->placeholder('0.00')
                                    ->helperText('Yearly subscription price'),
                            ]),
                            Select::make('currency')
                                ->options([
                                    'USD' => 'United States Dollar (USD)',
                                    'EUR' => 'Euro (EUR)',
                                    'GBP' => 'British Pound (GBP)',
                                    'INR' => 'Indian Rupee (INR)',
                                ])
                                ->default('INR')
                                ->required()
                                ->helperText(
                                    'Select the currency for this plan',
                                ),
                            TextInput::make('trial_days')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->label('Trial Period (Days)')
                                ->placeholder('0')
                                ->helperText(
                                    'Number of trial days (leave empty for no trial)',
                                ),
                        ]),
                    Tab::make('Features')
                        ->icon('heroicon-o-star')
                        ->schema([
                            Repeater::make('features')
                                ->relationship('features')
                                ->schema([
                                    TextInput::make(
                                        'feature_name',
                                    )
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Feature Name')
                                        ->placeholder(
                                            'e.g., "Unlimited Access"',
                                        ),
                                    TextInput::make('value')
                                        ->maxLength(255)
                                        ->label('Feature Value')
                                        ->placeholder('e.g., "Yes" or "10 GB"'),
                                    RichEditor::make(
                                        'brief_description',
                                    )->label('Feature Description'),
                                ])
                                ->itemLabel(
                                    fn (array $state): ?string => $state[
                                        'feature_name'
                                    ] ?? null,
                                )
                                ->collapsible()
                                ->defaultItems(0)
                                ->addActionLabel('Add Feature')
                                ->orderColumn('order_column')
                                ->lazy(),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTab()
                ->afterStateUpdated(function (
                    $state,
                    callable $get,
                    callable $set,
                ) {
                    if (
                        $get('is_active') &&
                        ! $get('is_enterprise') &&
                        ! $get('price_monthly') &&
                        ! $get('price_yearly')
                    ) {
                        $set('is_active', false);
                        Notification::make()
                            ->warning()
                            ->title('Price Required')
                            ->body(
                                'At least one price (monthly or yearly) is required for an active plan (unless Enterprise).',
                            )
                            ->send();
                    }
                }),
            Toggle::make('is_active')
                ->default(true)
                ->label('Active Status')
                ->helperText('Toggle to enable or disable this plan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('price_monthly')
                    ->money(fn ($record) => $record->currency->value)
                    ->sortable(),
                TextColumn::make('price_yearly')
                    ->money(fn ($record) => $record->currency->value)
                    ->sortable(),
                TextColumn::make('currency'),
                ToggleColumn::make('is_active'),
                ToggleColumn::make('is_enterprise'),
                TextColumn::make('trial_days')
                    ->numeric()
                    ->sortable(),
                TextInputColumn::make('badge'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TernaryFilter::make('is_active')])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers later if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
