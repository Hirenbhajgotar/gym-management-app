<?php

namespace App\Filament\Admin\Resources;

use App\Enums\WhatsAppUsageLogStatusEnum;
use App\Models\WhatsAppUsageLog;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsAppUsageLogResource extends Resource
{
    protected static ?string $model = WhatsAppUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Message Logs';

    protected static ?string $modelLabel = 'Message Log';

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    // Temporarily disable access to this resource
    public static function canAccess(array $parameters = []): bool
    {
        return false;
    }

    // Temporarily disable access to this resource
    public static function shouldRegisterNavigation(): bool
    {
        return false; // This will hide it from the menu
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('gym_id', Filament::getTenant()?->id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->placeholder('Not sent'),

                Tables\Columns\TextColumn::make('recipient')
                    ->searchable()
                    ->label('Recipient'),

                Tables\Columns\TextColumn::make('template.display_name')
                    ->label('Template')
                    ->placeholder('—')
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('credits_used')
                    ->label('Credits')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('twilio_cost')
                    ->label('Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime('g:i A')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('twilio_message_sid')
                    ->label('Message SID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(WhatsAppUsageLogStatusEnum::class)
                    ->label('Status'),

                Tables\Filters\Filter::make('sent_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('sent_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('sent_at', '<=', $data['until']));
                    }),

                Tables\Filters\TernaryFilter::make('has_error')
                    ->label('Has Error')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('error_message'),
                        false: fn ($query) => $query->whereNull('error_message'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\WhatsAppUsageLogResource\Pages\ListWhatsAppUsageLogs::route('/'),
        ];
    }
}
