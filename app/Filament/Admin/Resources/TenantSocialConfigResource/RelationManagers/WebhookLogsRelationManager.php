<?php

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
                Forms\Components\Section::make('Webhook Information')
                    ->schema([
                        Forms\Components\TextInput::make('webhook_id')
                            ->label('Webhook ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('platform')
                            ->label('Platform')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'received' => 'Received',
                                'processing' => 'Processing',
                                'success' => 'Success',
                                'failed' => 'Failed',
                                'error' => 'Error',
                            ])
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Received At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('processed_at')
                            ->label('Processed At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payload Data')
                    ->schema([
                        Forms\Components\Textarea::make('payload')
                            ->label('Webhook Payload')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->rows(10)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Response Data')
                    ->schema([
                        Forms\Components\Textarea::make('response')
                            ->label('Processing Response')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->rows(5)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => ! empty($record?->response)),

                Forms\Components\Section::make('Error Details')
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->disabled(),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => ! empty($record?->error_message)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('webhook_id')
            ->columns([
                Tables\Columns\TextColumn::make('webhook_id')
                    ->label('Webhook ID')
                    ->searchable()
                    ->copyable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->webhook_id),

                Tables\Columns\TextColumn::make('platform')
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

                Tables\Columns\TextColumn::make('status')
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'received' => 'Received',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'error' => 'Error',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'youtube' => 'YouTube',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('has_error')
                    ->label('Has Errors')
                    ->query(fn ($query) => $query->whereNotNull('error_message')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('5xl'),

                Tables\Actions\Action::make('view_payload')
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No webhook logs yet')
            ->emptyStateDescription('Webhook logs will appear here when webhooks are received from social media platforms.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
