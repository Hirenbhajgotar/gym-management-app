<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\CreateWhatsAppPackage;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\EditWhatsAppPackage;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\ListWhatsAppPackages;
use App\Models\WhatsAppPackage;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class WhatsAppPackageResource extends BaseResource
{
    protected static ?string $model = WhatsAppPackage::class;

    protected static ?string $navigationIcon = 'phosphor-package';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Message Packages';

    public const MARGIN_PERCENTAGE = 0.010;

    public const TWILIO_COST_PER_MESSAGE = 1.53;

    public const TWILIO_NUMBER_COST = 136.41;

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
        return Cache::flexible(WhatsAppPackage::NAVIGATION_BADGE_CACHE_KEY, [600, 3600], function () {
            return (string) static::getModel()::where('is_active', true)->count();
        }) ?: null;
    }

    public static function updateSellingPrice(Get $get, Set $set): void
    {
        $msgCount = (int) $get('message_count');
        $msgCost = (float) $get('twilio_msg_cost_per_unit');
        $includesNumber = (bool) $get('includes_number');
        $numberCost = (float) $get('twilio_number_cost');

        $baseCost = ($msgCount * $msgCost) + ($includesNumber ? $numberCost : 0);

        $margin = (float) $msgCount * self::MARGIN_PERCENTAGE;
        $sellingPrice = $baseCost + $margin;
        // We update the selling price to the base cost. You can add a default markup here if desired.
        $set('selling_price', round($sellingPrice, 2));
    }

    public static function calculateCost(Get $get): string
    {
        $msgCount = (float) ($get('message_count') ?? 0);
        $msgCost = (float) ($get('twilio_msg_cost_per_unit') ?? 0);
        $includesNumber = (bool) $get('includes_number');
        $numberCost = (float) ($get('twilio_number_cost') ?? 0);

        return number_format(($msgCount * $msgCost) + ($includesNumber ? $numberCost : 0), 2, '.', '');
    }

    public static function calculateMargin(Get $get): string
    {
        return number_format((($get('message_count') ?? 0) * self::MARGIN_PERCENTAGE), 2, '.', '');
    }

    public static function calculateProfit(Get $get): string
    {
        $cost = (float) static::calculateCost($get);
        $sellingPrice = (float) ($get('selling_price') ?? 0);

        return number_format($sellingPrice - $cost, 2, '.', '');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Package Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->columnSpan(1),

                    TextInput::make('message_count')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::updateSellingPrice($get, $set))
                        ->label('Number of Messages')
                        ->columnSpan(1),

                    Textarea::make('description')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Pricing')
                ->schema([
                    TextInput::make('selling_price')
                        ->required()
                        ->numeric()
                        ->prefix('₹')
                        ->minValue(0)
                        ->step(0.01)
                        ->live(onBlur: true)
                        ->hint(fn (Get $get) => new HtmlString('Cost: ₹' . static::calculateCost($get) . ' + Margin: ₹' . static::calculateMargin($get) . ' | Profit: ₹' . static::calculateProfit($get)))
                        ->hintColor(fn (Get $get) => static::calculateProfit($get) > 0 ? 'success' : 'danger')
                        ->label('Selling Price'),

                    TextInput::make('twilio_number_cost')
                        ->numeric()
                        ->prefix('₹')
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::updateSellingPrice($get, $set))
                        ->label('Twilio Number Cost'),

                    TextInput::make('twilio_msg_cost_per_unit')
                        ->numeric()
                        ->prefix('₹')
                        ->default(0.15)
                        ->step(0.0001)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::updateSellingPrice($get, $set))
                        ->label('Twilio Cost Per Message')
                        ->suffixAction(
                            Action::make('fetch_pricing')
                                ->icon('phosphor-currency-inr')
                                ->tooltip('Fetching live pricing requires Twilio Pricing API. Assuming default rate.')
                                ->action(function (Set $set) {
                                    // Normally we would hit Twilio Pricing API here, e.g. /v1/Messaging/Countries/IN
                                    // Since Pricing API isn't enabled by default without special setup,
                                    // we set a safe default.
                                    $set('twilio_msg_cost_per_unit', self::TWILIO_COST_PER_MESSAGE); // Default assumed cost for India (WhatsApp utility/marketing average)
                                })
                        ),

                    TextInput::make('validity_days')
                        ->numeric()
                        ->default(365)
                        ->minValue(1)
                        ->label('Validity (Days)'),
                ])->columns(2),

            Section::make('Display Settings')
                ->schema([
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('Sort Order'),

                    Toggle::make('is_active')
                        ->default(true)
                        ->label('Active'),

                    Toggle::make('is_popular')
                        ->default(false)
                        ->label('Mark as Popular'),

                    Toggle::make('includes_number')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::updateSellingPrice($get, $set))
                        ->label('Includes Dedicated Number'),
                ])->columns(2),

            Section::make('Features List')
                ->schema([
                    TagsInput::make('features')
                        ->placeholder('Add a feature')
                        ->helperText('Press Enter after each feature')
                        ->columnSpanFull(),
                ]),
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

                TextColumn::make('message_count')
                    ->numeric()
                    ->sortable()
                    ->label('Messages'),

                TextColumn::make('selling_price')
                    ->money('INR')
                    ->sortable()
                    ->label('Price'),

                TextColumn::make('your_profit')
                    ->money('INR')
                    ->label('Profit'),

                TextColumn::make('margin_percentage')
                    ->numeric(2)
                    ->suffix('%')
                    ->label('Margin'),

                IconColumn::make('is_popular')
                    ->boolean()
                    ->label('Popular'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('is_popular')
                    ->label('Popular'),
            ])
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppPackages::route('/'),
            'create' => CreateWhatsAppPackage::route('/create'),
            'edit' => EditWhatsAppPackage::route('/{record}/edit'),
        ];
    }
}
