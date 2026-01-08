<?php

namespace App\Filament\SuperAdmin\Resources\UserResource\RelationManagers;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->options(Plan::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive(),
                        Forms\Components\Select::make('billing_cycle')
                            ->label('Billing Cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(SubscriptionStatus::class)
                            ->default(SubscriptionStatus::ACTIVE)
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label('Currency')
                            ->options(\App\Enums\Currency::class)
                            ->default(\App\Enums\Currency::USD)
                            ->required(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial End Date')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->placeholder('Optional trial end date'),
                        Forms\Components\RichEditor::make('notes')
                            ->label('Notes')
                            ->placeholder('Add notes about this subscription...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Billing Periods')
                    ->schema([
                        Forms\Components\DateTimePicker::make('current_period_start')
                            ->label('Current Period Start')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('current_period_end')
                            ->label('Current Period End')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Subscription End Date')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->collapsed(),

                Forms\Components\Section::make('Cancellation & Grace Period')
                    ->schema([
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('grace_period_ends_at')
                            ->label('Grace Period Ends At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Metadata & Audit Information')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Created At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('updated_at')
                            ->label('Updated At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(1)
                    ->collapsed(),

                Forms\Components\Section::make('Related Information')
                    ->schema([
                        Forms\Components\Placeholder::make('user_info')
                            ->label('User')
                            ->content(fn ($record) => $record ? "ID: {$record->user->id} | Name: {$record->user->name} | Email: {$record->user->email}" : 'N/A'),
                        Forms\Components\Placeholder::make('plan_info')
                            ->label('Plan Details')
                            ->content(fn ($record) => $record ? "Name: {$record->plan->name} | Monthly: \${$record->plan->price_monthly} | Yearly: \${$record->plan->price_yearly}" : 'N/A'),
                        Forms\Components\Placeholder::make('features_count')
                            ->label('Subscription Features')
                            ->content(fn ($record) => $record ? "Total Features: {$record->features->count()}" : 'N/A'),
                    ])
                    ->columns(1)
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status->getColor()),
                Tables\Columns\TextColumn::make('current_period_start')
                    ->label('Period Start')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('Period End')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SubscriptionStatus::class)
                    ->label('Filter by Status'),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->label('Filter by Billing Cycle'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('regenerate_invoice')
                    ->label('Regenerate Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-document-arrow-down')
                    ->modalDescription('Are you sure you want to regenerate the latest invoice for this plan? This will overwrite existing files.')
                    ->action(function (\App\Models\Subscription $record) {
                        // Find the latest payment for this user and this plan
                        $payment = \App\Models\Payment::where('user_id', $record->user_id)
                            ->whereJsonContains('metadata->plan_id', $record->plan_id)
                            ->latest()
                            ->first();

                        if (! $payment) {
                            \Filament\Notifications\Notification::make()
                                ->title('No payment found for this subscription plan')
                                ->warning()
                                ->send();

                            return;
                        }

                        app(\App\Services\InvoiceService::class)->generateInvoice($payment, force: true);

                        \Filament\Notifications\Notification::make()
                            ->title('Invoice regenerated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
