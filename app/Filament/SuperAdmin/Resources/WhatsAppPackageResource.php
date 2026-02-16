<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\CreateWhatsAppPackage;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\EditWhatsAppPackage;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages\ListWhatsAppPackages;
use App\Models\WhatsAppPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class WhatsAppPackageResource extends Resource
{
    protected static ?string $model = WhatsAppPackage::class;

    protected static ?string $navigationIcon = 'phosphor-package';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Message Packages';

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(WhatsAppPackage::NAVIGATION_BADGE_CACHE_KEY, [now()->addMinutes(10), now()->addHour()], function () {
            $count = static::getModel()::where('is_active', true)->count();

            return $count > 0 ? (string) $count : null;
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Package Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('message_count')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->label('Number of Messages')
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('selling_price')
                        ->required()
                        ->numeric()
                        ->prefix('₹')
                        ->minValue(0)
                        ->step(0.01)
                        ->label('Selling Price'),

                    Forms\Components\TextInput::make('twilio_number_cost')
                        ->numeric()
                        ->prefix('₹')
                        ->default(0)
                        ->minValue(0)
                        ->step(0.01)
                        ->label('Twilio Number Cost'),

                    Forms\Components\TextInput::make('twilio_msg_cost_per_unit')
                        ->numeric()
                        ->prefix('₹')
                        ->default(0.15)
                        ->step(0.0001)
                        ->label('Twilio Cost Per Message'),

                    Forms\Components\TextInput::make('validity_days')
                        ->numeric()
                        ->default(365)
                        ->minValue(1)
                        ->label('Validity (Days)'),
                ])->columns(2),

            Forms\Components\Section::make('Display Settings')
                ->schema([
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('Sort Order'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Active'),

                    Forms\Components\Toggle::make('is_popular')
                        ->default(false)
                        ->label('Mark as Popular'),

                    Forms\Components\Toggle::make('includes_number')
                        ->default(false)
                        ->label('Includes Dedicated Number'),
                ])->columns(2),

            Forms\Components\Section::make('Features List')
                ->schema([
                    Forms\Components\TagsInput::make('features')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('message_count')
                    ->numeric()
                    ->sortable()
                    ->label('Messages'),

                Tables\Columns\TextColumn::make('selling_price')
                    ->money('INR')
                    ->sortable()
                    ->label('Price'),

                Tables\Columns\TextColumn::make('your_profit')
                    ->money('INR')
                    ->label('Profit'),

                Tables\Columns\TextColumn::make('margin_percentage')
                    ->numeric(2)
                    ->suffix('%')
                    ->label('Margin'),

                Tables\Columns\IconColumn::make('is_popular')
                    ->boolean()
                    ->label('Popular'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\TernaryFilter::make('is_popular')
                    ->label('Popular'),
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
