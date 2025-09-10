<?php

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WebhookLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookLogs';

    protected static ?string $title = 'Webhook Logs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('webhook_id')
                    ->label('Webhook ID')
                    ->maxLength(255),
                Forms\Components\Textarea::make('payload')
                    ->label('Payload')
                    ->rows(10),
                Forms\Components\Textarea::make('response')
                    ->label('Response')
                    ->rows(5),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'received' => 'Received',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'error' => 'Error',
                    ]),
                Forms\Components\Textarea::make('error_message')
                    ->label('Error Message')
                    ->rows(3),
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
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'received' => 'gray',
                        'processing' => 'yellow',
                        'success' => 'success',
                        'failed' => 'danger',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(100)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'received' => 'Received',
                        'processing' => 'Processing',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'error' => 'Error',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
