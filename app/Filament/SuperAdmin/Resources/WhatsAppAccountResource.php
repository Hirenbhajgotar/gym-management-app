<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\WhatsAppAccountResource\Pages\ListWhatsAppAccounts;
use App\Filament\SuperAdmin\Resources\WhatsAppAccountResource\Pages\ViewWhatsAppAccount;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppBillingService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class WhatsAppAccountResource extends BaseResource
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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

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
        return Cache::flexible(WhatsAppAccount::NAVIGATION_BADGE_CACHE_KEY, [1800, 3600], function () {
            return (string) static::getModel()::count();
        }) ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gym.name')
                    ->searchable()
                    ->sortable()
                    ->label('Gym'),

                TextColumn::make('business_name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('twilio_account_sid')
                    ->label('Twilio SID')
                    ->copyable()
                    ->limit(20)
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('verification_status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('phoneNumber.phone_number')
                    ->label('Phone Number')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatsAppAccountStatusEnum::class),
                SelectFilter::make('verification_status')
                    ->options(WhatsAppAccountVerificationStatusEnum::class),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('adjustCredits')
                    ->label('Adjust Credits')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->label('Credit Amount')
                            ->helperText('Positive to add, negative to deduct'),
                        Textarea::make('reason')
                            ->required()
                            ->label('Reason')
                            ->maxLength(500),
                    ])
                    ->action(function (WhatsAppAccount $record, array $data): void {
                        try {
                            $service = resolve(WhatsAppBillingService::class);
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
                Section::make('Account Details')
                    ->schema([
                        TextEntry::make('gym.name')
                            ->label('Gym'),
                        TextEntry::make('business_name'),
                        TextEntry::make('business_email'),
                        TextEntry::make('twilio_account_sid')
                            ->label('Twilio SID')
                            ->copyable(),
                        TextEntry::make('waba_id')
                            ->label('WABA ID')
                            ->default('—'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('verification_status')
                            ->badge(),
                        TextEntry::make('verified_at')
                            ->dateTime()
                            ->default('—'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])->columns(3),

                Section::make('Phone Numbers')
                    ->schema([
                        RepeatableEntry::make('phoneNumber')
                            ->schema([
                                TextEntry::make('phone_number')
                                    ->label('Number'),
                                TextEntry::make('display_name')
                                    ->label('Display Name')
                                    ->default('—'),
                                TextEntry::make('status')
                                    ->badge(),
                            ])->columns(3),
                    ]),

                Section::make('Templates')
                    ->schema([
                        RepeatableEntry::make('templates')
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('category')
                                    ->badge(),
                                TextEntry::make('status')
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
