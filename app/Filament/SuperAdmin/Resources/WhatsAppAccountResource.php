<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\WhatsAppAccountResource\Pages\ListWhatsAppAccounts;
use App\Filament\SuperAdmin\Resources\WhatsAppAccountResource\Pages\ViewWhatsAppAccount;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppBillingService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class WhatsAppAccountResource extends Resource
{
    protected static ?string $model = WhatsAppAccount::class;

    protected static ?string $navigationIcon = 'phosphor-whatsapp-logo';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Gym Accounts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(WhatsAppAccount::NAVIGATION_BADGE_CACHE_KEY, [now()->addMinutes(30), now()->addHour()], function () {
            $count = static::getModel()::count();

            return $count > 0 ? (string) $count : null;
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gym.name')
                    ->searchable()
                    ->sortable()
                    ->label('Gym'),

                Tables\Columns\TextColumn::make('business_name')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('twilio_account_sid')
                    ->label('Twilio SID')
                    ->copyable()
                    ->limit(20)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('verification_status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phoneNumber.phone_number')
                    ->label('Phone Number')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(\App\Enums\WhatsAppAccountStatusEnum::class),
                Tables\Filters\SelectFilter::make('verification_status')
                    ->options(\App\Enums\WhatsAppAccountVerificationStatusEnum::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('adjustCredits')
                    ->label('Adjust Credits')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->label('Credit Amount')
                            ->helperText('Positive to add, negative to deduct'),
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->label('Reason')
                            ->maxLength(500),
                    ])
                    ->action(function (WhatsAppAccount $record, array $data): void {
                        try {
                            $service = app(WhatsAppBillingService::class);
                            $service->adjustCredits(
                                (string) $record->gym_id,
                                (int) $data['amount'],
                                $data['reason']
                            );

                            Notification::make()
                                ->success()
                                ->title('Credits Adjusted')
                                ->body("Adjusted {$data['amount']} credits for {$record->gym->name}.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Adjustment Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Account Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('gym.name')
                            ->label('Gym'),
                        Infolists\Components\TextEntry::make('business_name'),
                        Infolists\Components\TextEntry::make('business_email'),
                        Infolists\Components\TextEntry::make('twilio_account_sid')
                            ->label('Twilio SID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('waba_id')
                            ->label('WABA ID')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('verification_status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('verified_at')
                            ->dateTime()
                            ->default('—'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make('Phone Numbers')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('phoneNumber')
                            ->schema([
                                Infolists\Components\TextEntry::make('phone_number')
                                    ->label('Number'),
                                Infolists\Components\TextEntry::make('display_name')
                                    ->label('Display Name')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ])->columns(3),
                    ]),

                Infolists\Components\Section::make('Templates')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('templates')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('category')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ])->columns(3),
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
            'index' => ListWhatsAppAccounts::route('/'),
            'view' => ViewWhatsAppAccount::route('/{record}'),
        ];
    }
}
