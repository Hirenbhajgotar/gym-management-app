<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\GymMemberResource\Forms\PaymentForm;
use App\Jobs\GenerateInvoiceJob;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Observers\MemberPaymentObserver;
use App\Traits\GuardsArchivedOwner;
use App\Traits\HasTenantAccess;
use Closure;
use DB;
use Exception;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Payments for a gym member.
 *
 * handleCreatePayment()/handleUpdatePayment() write payment_status,
 * membership_start, membership_end and the plan straight back onto the member, so
 * an unguarded write here is how a revoked record quietly becomes live again
 * without anyone restoring it. Reads stay open (no getRelationship() filter)
 * because archived payments still have to resolve to a person for revenue
 * reporting; {@see GuardsArchivedOwner} closes mutation only.
 */
class PaymentsRelationManager extends RelationManager
{
    use GuardsArchivedOwner;
    use HasTenantAccess;

    protected static string $relationship = 'payments';

    protected static ?string $icon = 'heroicon-o-credit-card';

    protected function archivedOwnerSubject(): string
    {
        return 'Payments';
    }

    protected function archivedOwnerNoticeBody(): string
    {
        return 'Payments stay visible as a record of what was billed and collected, but nothing here can be added, edited or deleted until the member is restored.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Membership Details')
                    ->icon('heroicon-o-ticket')
                    ->description('Configure membership settings')
                    ->schema([
                        ...PaymentForm::only(['membership_plan_id', 'membership_start', 'membership_end']),
                    ])
                    ->columns(2),

                Section::make('Payment Details')
                    ->icon('heroicon-o-credit-card')
                    ->description('Record a payment for this member')
                    ->schema([
                        Hidden::make('member_id')
                            ->default(fn (RelationManager $livewire) => $livewire->ownerRecord->getKey()),

                        Hidden::make('gym_id')
                            ->default(fn ($livewire) => $this->getGymId()),

                        ...PaymentForm::only(['amount', 'discount_amount', 'amount_paid', 'payment_method', 'payment_date', 'currency', 'status', 'notes']),
                    ])
                    ->columns(2),
            ])
            ->columns(null);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->header(fn () => $this->archivedOwnerHeader())
            ->columns([
                TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),

                TextColumn::make('amount')
                    ->money(function (?MemberPayment $record) {
                        return $record?->currency->value ?? Currency::INR->value;
                    })
                    ->summarize(Sum::make()),

                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money(function (MemberPayment $record) {
                        return $record->currency->value ?? Currency::INR->value;
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Sum')
                            ->using(function (\Illuminate\Database\Query\Builder $query): float {
                                return (float) $query->join(
                                    'member_payment_transactions',
                                    'member_payments.id',
                                    '=',
                                    'member_payment_transactions.member_payment_id',
                                )
                                    ->where('member_payment_transactions.status', 'completed')
                                    ->sum('member_payment_transactions.amount');
                            })
                    ),

                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money(function (MemberPayment $record) {
                        return $record->currency->value ?? Currency::INR->value;
                    }),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money(function (MemberPayment $record) {
                        return $record->currency->value ?? Currency::INR->value;
                    }),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('payment_date')
                    ->date()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('membership_start')
                    ->date()
                    ->badge()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('membership_end')
                    ->date()
                    ->badge()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::toSelectOptions()),
                SelectFilter::make('status')
                    ->options(PaymentStatus::toSelectOptions()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Payment')
                    ->action(fn (array $data) => $this->handleCreatePayment($data)),
            ])
            ->actions([
                Action::make('record_installment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (MemberPayment $record) => ! $this->isOwnerArchived() && ! $record->is_fully_paid)
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn (MemberPayment $record) => $record->remaining_amount)
                            ->suffix(fn (MemberPayment $record) => 'Max: ' . number_format($record->remaining_amount, 2)),
                        Select::make('payment_method')
                            ->options(PaymentMethod::toSelectOptions())
                            ->required()
                            ->default(PaymentMethod::CASH->value),
                        DatePicker::make('transaction_date')
                            ->required()
                            ->default(now()),
                        Textarea::make('notes')
                            ->maxLength(65535)
                            ->rows(2),
                    ])
                    ->action(function (array $data, MemberPayment $record) {
                        $this->guardArchivedOwner();

                        DB::transaction(function () use ($data, $record) {
                            $record = MemberPayment::query()->lockForUpdate()->findOrFail($record->id);

                            if ($data['amount'] > $record->remaining_amount) {
                                Notification::make()
                                    ->title('Invalid Payment Amount')
                                    ->body('Payment amount exceeds remaining amount.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->transactions()->create([
                                'member_payment_id' => $record->id,
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'transaction_date' => $data['transaction_date'],
                                'status' => 'completed',
                                'notes' => $data['notes'] ?? null,
                                'currency' => $record->currency,
                            ]);

                            $record->syncStatusFromTransactions();

                            $member = $record->member;
                            if ($member) {
                                /** @var PaymentStatus|null $paymentStatus */
                                $paymentStatus = $record->status;
                                $member->update([
                                    'payment_status' => $paymentStatus?->value,
                                    'latest_payment_at' => $data['transaction_date'],
                                ]);
                            }

                            if ($record->gym_id) {
                                MemberPaymentObserver::clearPaymentCaches($record->gym_id);
                            }

                            Notification::make()
                                ->success()
                                ->title('Payment Recorded')
                                ->body('Payment recorded successfully.')
                                ->send();
                        });
                    }),

                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalHeading(fn (MemberPayment $record): string => sprintf(
                        'Edit Payment #%d - %s',
                        $record->id,
                        $record->membershipPlan->name ?? 'Unknown Plan',
                    ))
                    ->visible(function (MemberPayment $record) {
                        return $record->status?->value !== PaymentStatus::EXPIRED->value;
                    })
                    ->before(fn ($record) => $this->preventExpiredPaymentEdit($record))
                    ->action(fn (array $data, $record) => $this->handleUpdatePayment($data, $record)),

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record) => $this->canDeletePayment($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Member Payment')
                    ->modalDescription('Are you sure you want to delete this payment? This action requires password confirmation.')
                    ->modalSubmitActionLabel('Confirm Delete')
                    ->before(fn () => $this->guardArchivedOwner())
                    ->form([$this->getPasswordField()]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn () => $this->guardArchivedOwner()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $this->sortPayments($query));
    }

    /**
     * Get password input field with validation
     */
    protected function getPasswordField(): TextInput
    {
        return TextInput::make('password')
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
                fn () => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Hash::check($value, Auth::user()->password)) {
                        $fail('The password is incorrect.');
                    }
                },
            ]);
    }

    /**
     * Handle payment creation
     */
    protected function handleCreatePayment(array $data): void
    {
        $this->guardArchivedOwner();

        DB::transaction(function () use ($data) {
            /** @var GymMember $member */
            $member = $this->ownerRecord;
            $member = GymMember::query()->lockForUpdate()->findOrFail($member->id);

            /** @var MembershipPlan $plan */
            $plan = MembershipPlan::query()->findOrFail($data['membership_plan_id']);

            $rawDiscount = $data['discount_amount'] ?? 0;
            $discountAmount = (float) (is_numeric($rawDiscount) ? $rawDiscount : 0);
            $freshAmount = ((float) $plan->price) - $discountAmount;
            $data['amount'] = max(0, $freshAmount);

            $lastPayment = $this->getLastCompletedPayment($member->id, lock: true);

            $amountPaid = $data['amount_paid'] ?? $data['amount'];
            $paymentData = collect($data)->except(['password', 'amount_paid'])->toArray();

            /** @var MemberPayment $payment */
            $payment = $this->getRelationship()->create($paymentData);

            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => $amountPaid,
                'payment_method' => $data['payment_method'],
                'transaction_date' => $data['payment_date'],
                'status' => 'completed',
                'currency' => $data['currency'] ?? null,
            ]);

            $payment->syncStatusFromTransactions();

            /** @var PaymentStatus|null $paymentStatus */
            $paymentStatus = $payment->status;
            $this->updateMemberFromPayment($member, array_merge($paymentData, ['status' => $paymentStatus instanceof PaymentStatus ? $paymentStatus->value : null]));

            if ($lastPayment) {
                $this->expirePayment($lastPayment);
            }

            dispatch(new GenerateInvoiceJob($payment, 'member_payment'));

            $this->sendNotification('Payment Created', 'The payment has been created successfully.');
        });
    }

    /**
     * Handle payment update
     */
    protected function handleUpdatePayment(array $data, MemberPayment $record): void
    {
        $this->guardArchivedOwner();

        DB::transaction(function () use ($data, $record) {
            $record = MemberPayment::query()->lockForUpdate()->findOrFail($record->id);

            if ($data['membership_plan_id'] ?? null) {
                /** @var MembershipPlan $plan */
                $plan = MembershipPlan::query()->findOrFail($data['membership_plan_id']);

                $rawDiscount = $data['discount_amount'] ?? 0;
                $discountAmount = (float) (is_numeric($rawDiscount) ? $rawDiscount : 0);
                $freshAmount = ((float) $plan->price) - $discountAmount;
                $data['amount'] = max(0, $freshAmount);
            }

            $paymentData = collect($data)->except(['password'])->toArray();
            $record->update($paymentData);

            /** @var GymMember $member */
            $member = $this->ownerRecord;
            $this->updateMemberFromPayment($member, $paymentData);
        });

        $this->sendNotification('Payment Updated', 'The payment has been updated successfully.');
    }

    /**
     * Update member record from payment data
     */
    protected function updateMemberFromPayment(GymMember $member, array $paymentData): void
    {
        $member->update([
            'payment_status' => $paymentData['status'] ?? $member->payment_status,
            'membership_start' => $paymentData['membership_start'] ?? $member->membership_start,
            'membership_end' => $paymentData['membership_end'] ?? $member->membership_end,
            'gym_membership_plan_id' => $paymentData['membership_plan_id'] ?? $member->gym_membership_plan_id,
            'latest_payment_at' => $paymentData['payment_date'] ?? $member->latest_payment_at ?? null,
        ]);
    }

    /**
     * Get last completed payment for a member
     */
    protected function getLastCompletedPayment(int $memberId, bool $lock = false): ?MemberPayment
    {
        $query = $this->getRelationship()
            ->where('member_id', $memberId)
            ->where('status', PaymentStatus::COMPLETED)
            ->latest('payment_date');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Mark a payment as expired
     */
    protected function expirePayment(MemberPayment $payment): void
    {
        $payment->update(['status' => PaymentStatus::EXPIRED]);
    }

    /**
     * Prevent editing expired payments
     */
    protected function preventExpiredPaymentEdit(MemberPayment $record): void
    {
        /** @var PaymentStatus|null $status */
        $status = $record->status;
        if ($status === null || $status->value !== PaymentStatus::EXPIRED->value) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Action Not Allowed')
            ->body('This payment is expired and cannot be edited. Please create a new payment for corrections.')
            ->persistent()
            ->send();

        throw new Exception('Cannot edit an expired payment.');
    }

    /**
     * Check if user can delete a payment
     */
    protected function canDeletePayment(MemberPayment $record): bool
    {
        /** @var PaymentStatus|null $status */
        $status = $record->status;

        return Auth::user()->hasPermissionTo('delete_gym_member')
            && ($status === null || $status->value !== PaymentStatus::EXPIRED->value);
    }

    /**
     * Sort payments by status and date
     */
    protected function sortPayments(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PaymentStatus::COMPLETED->value])
            ->latest('payment_date')->latest();
    }

    /**
     * Send success notification to current user
     */
    protected function sendNotification(string $title, string $body): void
    {
        $recipient = Auth::user();

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->sendToDatabase($recipient)
            ->send();

        event(new DatabaseNotificationsSent($recipient));
    }
}
