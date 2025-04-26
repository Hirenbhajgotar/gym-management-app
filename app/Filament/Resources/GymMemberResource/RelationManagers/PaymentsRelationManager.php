<?php

namespace App\Filament\Resources\GymMemberResource\RelationManagers;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->icon('heroicon-o-currency-dollar')
                    ->description('Record a payment for this member')
                    ->schema([
                        Forms\Components\Hidden::make('member_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->id),
                        Forms\Components\Select::make('membership_plan_id')
                            ->relationship('membershipPlan', 'name')
                            ->required()
                            ->default(fn ($livewire) => $livewire->ownerRecord->gym_membership_plan_id)
                            ->disabled()
                            ->dehydrated(true),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix(function ($get) {
                                $currency = Currency::tryFrom($get('currency') ?? Currency::INR->value);

                                return $currency?->getSymbol() ?? Currency::INR->getSymbol();
                            })
                            ->minValue(0)
                            ->step(0.01)
                            ->default(fn ($livewire) => $livewire->ownerRecord->membershipPlan->price ?? 0)
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\Select::make('currency')
                            ->options(collect(Currency::cases())->mapWithKeys(
                                fn (Currency $method) => [$method->value => $method->getLabel()]
                            ))
                            ->required()
                            ->default(Currency::INR->value)
                            ->reactive(),
                        Forms\Components\Select::make('payment_method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(
                                fn (PaymentMethod $method) => [$method->value => $method->getLabel()]
                            ))
                            ->required()
                            ->default(PaymentMethod::CASH->value),
                        Forms\Components\Select::make('status')
                            ->options([
                                'completed' => 'Completed',
                                'pending' => 'Pending',
                            ])
                            ->required()
                            ->default('completed'),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(65535)
                            ->placeholder('e.g., Paid via UPI ID: user@upi')
                            ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\DatePicker::make('payment_date')
                            ->required()
                            ->default(now())
                            ->native(false),
                    ])
                    ->collapsible()
                    ->columns(2),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === 'completed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'upi' => 'UPI',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->steps([
                        Step::make('password_confirmation')
                            ->description('Please enter your password to confirm this action')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Your Password')
                                    ->helperText('Please enter your password to confirm this action.')
                                    ->revealable()
                                    ->password()
                                    ->required()
                                    ->extraAttributes([
                                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }',
                                    ])
                                    ->rules([
                                        'required',
                                        fn ($get) => function ($attribute, $value, $fail) {
                                            if (! Hash::check($value, auth()->user()->password)) {
                                                $fail('The password is incorrect.');
                                            }
                                        },
                                    ]),
                            ]),
                        Step::make('payment_details')
                            ->description('Enter payment information')
                            ->schema($this->form(new Form($this))->getComponents()),
                    ])
                    ->action(function ($data) {
                        $record = $this->getRelationship()->create(collect($data)->except(['password'])->toArray());
                        Notification::make()
                            ->success()
                            ->title('Payment Created')
                            ->body('The payment has been created successfully.')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Edit Payment #{$record->id} - {$record->membershipPlan->name}")
                    ->steps([
                        Step::make('password_confirmation')
                            ->description('Please enter your password to confirm this action')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Your Password')
                                    ->helperText('Please enter your password to confirm this action.')
                                    ->revealable()
                                    ->password()
                                    ->required()
                                    ->extraAttributes([
                                        'onkeydown' => 'if(event.key === "Enter") { event.preventDefault(); }',
                                    ])
                                    ->rules([
                                        'required',
                                        fn ($get) => function ($attribute, $value, $fail) {
                                            if (! Hash::check($value, auth()->user()->password)) {
                                                $fail('The password is incorrect.');
                                            }
                                        },
                                    ]),
                            ]),
                        Step::make('payment_details')
                            ->description('Update payment information')
                            ->schema($this->form(new Form($this))->getComponents()),
                    ])
                    ->action(function ($data, $record) {
                        $record->update(collect($data)->except(['password'])->toArray());
                        Notification::make()
                            ->success()
                            ->title('Payment Updated')
                            ->body('The payment has been updated successfully.')
                            ->send();
                    }),
                // ->cancelParentActions(),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn () => auth()->user()->hasPermissionTo('delete_gym::member'))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Member Payment')
                    ->modalDescription('Are you sure you want to delete this payment? This action requires password confirmation.')
                    ->modalSubmitActionLabel('Confirm Delete')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Your Password')
                            ->revealable()
                            ->password()
                            ->required()
                            ->rules([
                                'required',
                                fn ($get) => function ($attribute, $value, $fail) {
                                    if (! Hash::check($value, auth()->user()->password)) {
                                        $fail('The password is incorrect.');
                                    }
                                },
                            ]),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
