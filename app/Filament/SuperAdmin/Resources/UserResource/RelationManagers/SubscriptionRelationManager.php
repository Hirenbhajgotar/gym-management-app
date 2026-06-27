<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\UserResource\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Enums\Currency;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use App\Models\Subscription;
use App\Models\Payment;
use Filament\Notifications\Notification;
use App\Services\InvoiceService;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SubscriptionRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Subscription Details')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(Plan::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive(),
                        Select::make('billing_cycle')
                            ->label('Billing Cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(SubscriptionStatus::class)
                            ->default(SubscriptionStatus::ACTIVE)
                            ->required(),
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Select::make('currency')
                            ->label('Currency')
                            ->options(Currency::class)
                            ->default(Currency::USD)
                            ->required(),
                        DateTimePicker::make('trial_ends_at')
                            ->label('Trial End Date')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->placeholder('Optional trial end date'),
                        RichEditor::make('notes')
                            ->label('Notes')
                            ->placeholder('Add notes about this subscription...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Billing Periods')
                    ->schema([
                        DateTimePicker::make('current_period_start')
                            ->label('Current Period Start')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        DateTimePicker::make('current_period_end')
                            ->label('Current Period End')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        DateTimePicker::make('ends_at')
                            ->label('Subscription End Date')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->collapsed(),

                Section::make('Cancellation & Grace Period')
                    ->schema([
                        DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        DateTimePicker::make('grace_period_ends_at')
                            ->label('Grace Period Ends At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Metadata & Audit Information')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->disabled(),
                        DateTimePicker::make('created_at')
                            ->label('Created At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                        DateTimePicker::make('updated_at')
                            ->label('Updated At')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->disabled(),
                    ])
                    ->columns(1)
                    ->collapsed(),

                Section::make('Related Information')
                    ->schema([
                        Placeholder::make('user_info')
                            ->label('User')
                            ->content(fn ($record) => $record ? "ID: {$record->user->id} | Name: {$record->user->name} | Email: {$record->user->email}" : 'N/A'),
                        Placeholder::make('plan_info')
                            ->label('Plan Details')
                            ->content(fn ($record) => $record ? "Name: {$record->plan->name} | Monthly: \${$record->plan->price_monthly} | Yearly: \${$record->plan->price_yearly}" : 'N/A'),
                        Placeholder::make('features_count')
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
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->badge()
                    ->color('info'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status->getColor()),
                TextColumn::make('current_period_start')
                    ->label('Period Start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('current_period_end')
                    ->label('Period End')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime()
                    ->placeholder('N/A'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class)
                    ->label('Filter by Status'),
                SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->label('Filter by Billing Cycle'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('regenerate_invoice')
                    ->label('Regenerate Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-document-arrow-down')
                    ->modalDescription('Are you sure you want to regenerate the latest invoice for this plan? This will overwrite existing files.')
                    ->action(function (Subscription $record) {
                        // Find the latest payment for this user and this plan
                        $payment = Payment::query()->where('user_id', $record->user_id)
                            ->whereJsonContains('metadata->plan_id', $record->plan_id)
                            ->latest()
                            ->first();

                        if (! $payment) {
                            Notification::make()
                                ->title('No payment found for this subscription plan')
                                ->warning()
                                ->send();

                            return;
                        }

                        resolve(InvoiceService::class)->generateInvoice($payment, force: true);

                        Notification::make()
                            ->title('Invoice regenerated successfully')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
