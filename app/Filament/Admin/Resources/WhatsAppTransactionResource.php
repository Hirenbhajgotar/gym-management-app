<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppCreditTransactionEnum;
use App\Filament\BaseResources\BaseResource;
use App\Models\WhatsAppCreditTransaction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsAppTransactionResource extends BaseResource
{
    protected static ?string $model = WhatsAppCreditTransaction::class;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Credit Transactions';

    protected static ?string $modelLabel = 'Credit Transaction';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
            ],

            DefaultRole::RECEPTIONIST->value => ['view', 'view_any'],
            DefaultRole::TRAINER->value => ['view', 'view_any'],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('gym_id', Filament::getTenant()?->id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('credits')
                    ->numeric()
                    ->prefix(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() || $record->isAdjustment() ? '+' : '-')
                    ->color(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_before')
                    ->numeric()
                    ->label('Before')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->numeric()
                    ->label('After')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('INR')
                    ->label('Amount')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(WhatsAppCreditTransactionEnum::class)
                    ->label('Transaction Type'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date & Time')
                            ->dateTime('M d, Y g:i A'),
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                    ])->columns(2),

                Infolists\Components\Section::make('Credit Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('credits')
                            ->label('Credits')
                            ->formatStateUsing(fn ($record) => ($record->isPurchase() || $record->isRefund() || $record->isBonus() || $record->isAdjustment() ? '+' : '-') . $record->credits)
                            ->color(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() ? 'success' : 'danger')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('balance_before')
                            ->label('Balance Before')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('balance_after')
                            ->label('Balance After')
                            ->numeric()
                            ->weight('bold'),
                    ])->columns(3),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Amount')
                            ->money('INR')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->label('Payment Reference')
                            ->copyable()
                            ->default('—'),
                        Infolists\Components\TextEntry::make('package.name')
                            ->label('Package')
                            ->default('—'),
                    ])->columns(3),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->default('—')
                            ->limit(400),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\WhatsAppTransactionResource\Pages\ListWhatsAppTransactions::route('/'),
        ];
    }
}
