<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\ViewAction;
use App\Filament\Admin\Resources\WhatsAppUsageLogResource\Pages\ListWhatsAppUsageLogs;
use App\Models\Gym;
use App\Enums\DefaultRole;
use App\Enums\WhatsAppUsageLogStatusEnum;
use App\Filament\BaseResources\BaseResource;
use App\Models\WhatsAppUsageLog;
use Filament\Facades\Filament;
use Filament\Tables\Table;

class WhatsAppUsageLogResource extends BaseResource
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

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                /** @var Gym|null $tenant */
                $tenant = Filament::getTenant();

                return $query->where('gym_id', $tenant?->id);
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->placeholder('Not sent'),

                TextColumn::make('recipient')
                    ->searchable()
                    ->label('Recipient'),

                TextColumn::make('template.display_name')
                    ->label('Template')
                    ->placeholder('—')
                    ->limit(30),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('credits_used')
                    ->label('Credits')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('twilio_cost')
                    ->label('Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime('g:i A')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('twilio_message_sid')
                    ->label('Message SID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatsAppUsageLogStatusEnum::class)
                    ->label('Status'),

                Filter::make('sent_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('sent_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('sent_at', '<=', $data['until']));
                    }),

                TernaryFilter::make('has_error')
                    ->label('Has Error')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('error_message'),
                        false: fn ($query) => $query->whereNull('error_message'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppUsageLogs::route('/'),
        ];
    }
}
