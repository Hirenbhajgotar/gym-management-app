<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppCreditTransactionEnum;
use App\Filament\Admin\Resources\WhatsAppTransactionResource\Pages\ListWhatsAppTransactions;
use App\Filament\BaseResources\BaseResource;
use App\Models\WhatsAppCreditTransaction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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

    public static function canEdit(Model $record): bool
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
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('credits')
                    ->numeric()
                    ->prefix(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() || $record->isAdjustment() ? '+' : '-')
                    ->color(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('balance_before')
                    ->numeric()
                    ->label('Before')
                    ->toggleable(),

                TextColumn::make('balance_after')
                    ->numeric()
                    ->label('After')
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->money('INR')
                    ->label('Amount')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('payment_reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(WhatsAppCreditTransactionEnum::class)
                    ->label('Transaction Type'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Transaction Details')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date & Time')
                            ->dateTime('M d, Y g:i A'),
                        TextEntry::make('type')
                            ->badge(),
                    ])->columns(2),

                Section::make('Credit Information')
                    ->schema([
                        TextEntry::make('credits')
                            ->label('Credits')
                            ->formatStateUsing(fn ($record) => ($record->isPurchase() || $record->isRefund() || $record->isBonus() || $record->isAdjustment() ? '+' : '-') . $record->credits)
                            ->color(fn ($record) => $record->isPurchase() || $record->isRefund() || $record->isBonus() ? 'success' : 'danger')
                            ->weight('bold'),
                        TextEntry::make('balance_before')
                            ->label('Balance Before')
                            ->numeric(),
                        TextEntry::make('balance_after')
                            ->label('Balance After')
                            ->numeric()
                            ->weight('bold'),
                    ])->columns(3),

                Section::make('Payment Details')
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money('INR')
                            ->default('—'),
                        TextEntry::make('payment_reference')
                            ->label('Payment Reference')
                            ->copyable()
                            ->default('—'),
                        TextEntry::make('package.name')
                            ->label('Package')
                            ->default('—'),
                    ])->columns(3),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->default('—')
                            ->limit(400),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppTransactions::route('/'),
        ];
    }
}
