<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 3;

    // Restrict access to admins only
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function getNavigationBadge(): ?string
    {
        return cache()->remember('plan_navigation_badge_count_' . auth()->id(), now()->addMinutes(15), function () {
            return static::getModel()::count();
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Plan Management')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->reactive()
                                    ->label('Plan Name')
                                    ->placeholder('Enter plan name')
                                    ->helperText('A unique name for this subscription plan')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('slug', Str::slug($state));
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Plan::class, 'slug', ignoreRecord: true)
                                    ->helperText('Auto-generated from plan name, but you can edit it')
                                    // ->reactive()
                                    ->afterStateHydrated(function ($state, callable $set, $record) {
                                        if (! $state && $record) {
                                            $set('slug', Str::slug($record->name));
                                        }
                                    }),
                                Forms\Components\RichEditor::make('description')
                                    ->maxLength(5000)
                                    ->columnSpanFull()
                                    ->label('Plan Description')
                                    ->placeholder('Describe the benefits of this plan')
                                    ->hint('Preview available below')
                                    ->live(onBlur: true),
                            ])->columns(2),
                        Forms\Components\Tabs\Tab::make('Pricing')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('price_monthly')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->label('Monthly Price')
                                            ->placeholder('0.00')
                                            ->helperText('Monthly subscription price'),
                                        Forms\Components\TextInput::make('price_yearly')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->label('Yearly Price')
                                            ->placeholder('0.00')
                                            ->helperText('Yearly subscription price'),
                                    ]),
                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'USD' => 'United States Dollar (USD)',
                                        'EUR' => 'Euro (EUR)',
                                        'GBP' => 'British Pound (GBP)',
                                        'INR' => 'Indian Rupee (INR)',
                                    ])
                                    ->default('INR')
                                    ->required()
                                    ->helperText('Select the currency for this plan'),
                                Forms\Components\TextInput::make('trial_days')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable()
                                    ->label('Trial Period (Days)')
                                    ->placeholder('0')
                                    ->helperText('Number of trial days (leave empty for no trial)'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Features')
                            ->icon('heroicon-o-star')
                            ->schema([
                                Forms\Components\Repeater::make('features')
                                    ->relationship('features')
                                    ->schema([
                                        Forms\Components\TextInput::make('feature_name')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Feature Name')
                                            ->placeholder('e.g., "Unlimited Access"'),
                                        Forms\Components\TextInput::make('value')
                                            ->maxLength(255)
                                            ->label('Feature Value')
                                            ->placeholder('e.g., "Yes" or "10 GB"'),
                                    ])
                                    ->itemLabel(fn(array $state): ?string => $state['feature_name'] ?? null)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Feature')
                                    ->orderColumn('order_column')
                                    ->lazy(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTab()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        if ($get('is_active') && ! $get('price_monthly') && ! $get('price_yearly')) {
                            $set('is_active', false);
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Price Required')
                                ->body('At least one price (monthly or yearly) is required for an active plan.')
                                ->send();
                        }
                    }),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Active Status')
                    ->helperText('Toggle to enable or disable this plan')
                    ->onColor('success')
                    ->offColor('danger'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('price_monthly')->money(fn($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('price_yearly')->money(fn($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('currency')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('trial_days')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
