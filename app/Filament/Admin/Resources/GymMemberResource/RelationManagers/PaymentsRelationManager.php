<?php

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->icon('heroicon-o-credit-card')
                    ->description('Record a payment for this member')
                    ->schema([
                        Forms\Components\Hidden::make('member_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->id),

                        Forms\Components\Hidden::make('gym_id')
                            ->default(fn ($livewire) => Filament::getTenant()->id),

                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->placeholder('Select a membership plan')
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if ($state) {
                                    $plan = MembershipPlan::find($state);
                                    if ($plan && $plan->duration) {
                                        $start = $get('membership_start') ?? now();
                                        $set('membership_end', Carbon::parse($start)->addMonths($plan->duration)->toDateString());
                                        $set('amount', $plan->price);
                                    }
                                }
                            })
                            ->options(function (callable $get) {
                                $gymId = $this->ownerRecord->gym_id;
                                if (! $gymId) {
                                    return [];
                                }

                                return MembershipPlan::query()
                                    ->where('gym_id', $gymId)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
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

                        Forms\Components\DatePicker::make('membership_start')
                            ->placeholder('Select start date')
                            ->live()
                            ->required()
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $planId = $get('membership_plan_id');
                                if ($planId && $state) {
                                    $plan = MembershipPlan::find($planId);
                                    if ($plan && $plan->duration) {
                                        $set('membership_end', Carbon::parse($state)->addMonths($plan->duration)->toDateString());
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('membership_end')
                            ->placeholder('Auto-calculated based on plan')
                            ->required(),

                        Forms\Components\ToggleButtons::make('status')
                            ->inline()
                            ->options(PaymentStatus::class)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(65535)
                            ->placeholder('e.g., Paid via UPI ID: user@upi')
                            ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        Forms\Components\DatePicker::make('payment_date')
                            ->required()
                            ->default(now()),
                    ])
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
                    ->badge(),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        PaymentMethod::CASH->value => PaymentMethod::CASH->getLabel(),
                        PaymentMethod::UPI->value => PaymentMethod::UPI->getLabel(),
                        PaymentMethod::CREDIT_DEBIT_CARD->value => PaymentMethod::CREDIT_DEBIT_CARD->getLabel(),
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PaymentStatus::COMPLETED->value => PaymentStatus::COMPLETED->getLabel(),
                        PaymentStatus::PENDING->value => PaymentStatus::PENDING->getLabel(),
                        PaymentStatus::EXPIRED->value => PaymentStatus::EXPIRED->getLabel(),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->steps([
                        Step::make('Password Confirmation')
                            ->icon('heroicon-o-lock-closed')
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
                        Step::make('Payment Details')
                            ->icon('heroicon-o-credit-card')
                            ->description('Enter payment information')
                            ->schema($this->form(new Form($this))->getComponents()),
                    ])
                    ->action(function ($data) {
                        // Get the member record
                        $member = $this->ownerRecord;

                        // Get the previous payment (if any) to mark it as expired
                        $lastPayment = $this->getRelationship()
                            ->where('member_id', $member->id)
                            ->where('status', 'completed')
                            ->orderBy('payment_date', 'desc')
                            ->first();

                        // Create the new payment
                        $paymentData = collect($data)->except(['password'])->toArray();
                        $record = $this->getRelationship()->create($paymentData);

                        // Update the member's payment_status based on the new payment's status
                        $member->update([
                            'payment_status' => $paymentData['status'] ?? null,
                            'membership_start' => $paymentData['membership_start'] ?? $member->membership_start,
                            'membership_end' => $paymentData['membership_end'] ?? $member->membership_end,
                            'gym_membership_plan_id' => $paymentData['membership_plan_id'] ?? $member->gym_membership_plan_id,
                        ]);

                        // If there was a previous payment, mark it as expired
                        if ($lastPayment) {
                            $lastPayment->update([
                                'status' => 'expired',
                            ]);
                        }

                        // $record = $this->getRelationship()->create(collect($data)->except(['password'])->toArray());
                        $recipient = auth()->user();

                        Notification::make()
                            ->success()
                            ->title('Payment Created')
                            ->body('The payment has been created successfully.')
                            ->sendToDatabase($recipient)
                            ->send();

                        event(new DatabaseNotificationsSent($recipient));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Edit Payment #{$record->id} - {$record->membershipPlan->name}")
                    ->visible(function ($record) {
                        return $record->status->value !== PaymentStatus::EXPIRED->value; // Hide Edit button for expired payments
                    })
                    ->before(function ($record) {
                        // Prevent access via URL for expired payments
                        if ($record->status->value === PaymentStatus::EXPIRED->value) {
                            Notification::make()
                                ->warning()
                                ->title('Action Not Allowed')
                                ->body('This payment is expired and cannot be edited. Please create a new payment for corrections.')
                                ->persistent()
                                ->send();

                            throw new \Exception('Cannot edit an expired payment.');
                        }
                    })
                    ->steps([
                        Step::make('Password Confirmation')
                            ->icon('heroicon-o-lock-closed')
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
                        Step::make('Payment Details')
                            ->icon('heroicon-o-credit-card')
                            ->description('Update payment information')
                            ->schema($this->form(new Form($this))->getComponents()),
                    ])
                    ->action(function ($data, $record) {
                        // Update the payment record
                        $paymentData = collect($data)->except(['password'])->toArray();
                        $record->update($paymentData);

                        // Update the member's payment_status based on the updated payment's status
                        $member = $this->ownerRecord;
                        $member->update([
                            'payment_status' => $paymentData['status'] ?? null,
                            'membership_start' => $paymentData['membership_start'] ?? $member->membership_start,
                            'membership_end' => $paymentData['membership_end'] ?? $member->membership_end,
                            'gym_membership_plan_id' => $paymentData['membership_plan_id'] ?? $member->gym_membership_plan_id,
                        ]);

                        $recipient = auth()->user();

                        Notification::make()
                            ->success()
                            ->title('Payment Updated')
                            ->body('The payment has been updated successfully.')
                            ->sendToDatabase($recipient)
                            ->send();

                        event(new DatabaseNotificationsSent($recipient));
                    }),
                // ->cancelParentActions(),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(function ($record) {
                        return auth()->user()->hasPermissionTo('delete_gym_member') && $record->status->value !== PaymentStatus::EXPIRED->value; // Hide Delete button for expired payments
                    })
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

                // ActivityLogTimelineTableAction::make('Activities'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Sort by status (completed first) and then by payment_date (desc)
                return $query->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
                    ->orderBy('payment_date', 'desc')->orderBy('created_at', 'desc');
            });
    }
}
