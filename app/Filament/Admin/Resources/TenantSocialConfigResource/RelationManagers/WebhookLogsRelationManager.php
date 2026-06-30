<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebhookLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookLogs';

    protected static ?string $title = 'Webhook Logs';

    protected static ?string $recordTitleAttribute = 'webhook_id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Webhook Information')
                    ->schema([
                        TextInput::make('webhook_id')
                            ->label('Webhook ID')
                            ->disabled(),

                        TextInput::make('platform')
                            ->label('Platform')
                            ->disabled(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'received' => 'Received',
                                'processing' => 'Processing',
                                'success' => 'Success',
                                'failed' => 'Failed',
                                'error' => 'Error',
                            ])
                            ->disabled(),

                        DateTimePicker::make('created_at')
                            ->label('Received At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),

                        DateTimePicker::make('processed_at')
                            ->label('Processed At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Payload Data')
                    ->schema([
                        Textarea::make('payload')
                            ->label('Webhook Payload')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->rows(10)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Response Data')
                    ->schema([
                        Textarea::make('response')
                            ->label('Processing Response')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->rows(5)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => filled($record?->response)),

                Section::make('Error Details')
                    ->schema([
                        Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => filled($record?->error_message)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('webhook_id')
            ->columns([
                TextColumn::make('webhook_id')
                    ->label('Webhook ID')
                    ->searchable()
                    ->copyable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->webhook_id),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'facebook' => 'blue',
                        'instagram' => 'pink',
                        'whatsapp' => 'green',
                        'youtube' => 'red',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'gray',
                        'processing' => 'warning',
                        'success' => 'success',
                        'failed', 'error' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),

                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'received' => 'Received',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'error' => 'Error',
                    ])
                    ->multiple(),

                SelectFilter::make('platform')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'youtube' => 'YouTube',
                    ])
                    ->multiple(),

                Filter::make('has_error')
                    ->label('Has Errors')
                    ->query(fn ($query) => $query->whereNotNull('error_message')),
            ])
            ->actions([
                ViewAction::make()
                    ->modalWidth('5xl'),

                Action::make('view_payload')
                    ->label('Payload')
                    ->icon('heroicon-o-code-bracket')
                    ->color('info')
                    ->modalContent(fn ($record) => view('filament.modals.json-viewer', [
                        'title' => 'Webhook Payload',
                        'data' => $record->payload,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No webhook logs yet')
            ->emptyStateDescription('Webhook logs will appear here when webhooks are received from social media platforms.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
