<?php

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Date;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Traits\HasTenantAccess;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class PaymentsRelationManager extends RelationManager
{
    use HasTenantAccess;

    protected static string $relationship = 'payments';

    protected static ?string $icon = 'heroicon-o-credit-card';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Payment Details')
                    ->icon('heroicon-o-credit-card')
                    ->description('Record a payment for this member')
                    ->schema([
                        Hidden::make('member_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->id),

                        Hidden::make('gym_id')
                            ->default(fn ($livewire) => $this->getGymId()),

                        Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->placeholder('Select a membership plan')
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn ($state, callable $set, $get) => $this->updateMembershipDates($state, $set, $get))
                            ->options(fn () => $this->getActiveMembershipPlans())
                            ->dehydrated(true),

                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix(fn ($get) => $this->getCurrencySymbol($get))
                            ->minValue(0)
                            ->step(0.01)
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        Select::make('currency')
                            ->options(Currency::class)
                            ->required()
                            ->default(Currency::INR->value)
                            ->reactive(),

                        Select::make('payment_method')
                            ->options(PaymentMethod::toSelectOptions())
                            ->required()
                            ->default(PaymentMethod::CASH->value),

                        DatePicker::make('membership_start')
                            ->placeholder('Select start date')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->live()
                            ->required()
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, $get) => $this->updateEndDate($state, $set, $get)),

                        DatePicker::make('membership_end')
                            ->placeholder('Auto-calculated based on plan')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->required(),

                        ToggleButtons::make('status')
                            ->inline()
                            ->options(PaymentStatus::class)
                            ->required(),

                        Textarea::make('notes')
                            ->maxLength(65535)
                            ->placeholder('e.g., Paid via UPI ID: user@upi')
                            ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        DatePicker::make('payment_date')
                            ->native(false)
                            ->closeOnDateSelection(true)
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
                TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),

                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency->value)
                    ->summarize(Sum::make()),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('payment_date')
                    ->date()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::toSelectOptions()),
                SelectFilter::make('status')
                    ->options(PaymentStatus::toSelectOptions()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->steps([
                        $this->getPasswordConfirmationStep(),
                        $this->getPaymentDetailsStep('Enter payment information'),
                    ])
                    ->action(fn (array $data) => $this->handleCreatePayment($data)),
            ])
            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Edit Payment #{$record->id} - {$record->membershipPlan->name}")
                    ->visible(fn ($record) => $record->status->value !== PaymentStatus::EXPIRED->value)
                    ->before(fn ($record) => $this->preventExpiredPaymentEdit($record))
                    ->steps([
                        $this->getPasswordConfirmationStep(),
                        $this->getPaymentDetailsStep('Update payment information'),
                    ])
                    ->action(fn (array $data, $record) => $this->handleUpdatePayment($data, $record)),

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record) => $this->canDeletePayment($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Member Payment')
                    ->modalDescription('Are you sure you want to delete this payment? This action requires password confirmation.')
                    ->modalSubmitActionLabel('Confirm Delete')
                    ->form([$this->getPasswordField()]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $this->sortPayments($query));
    }

    /**
     * Get password confirmation step for wizards
     */
    protected function getPasswordConfirmationStep(): Step
    {
        return Step::make('Password Confirmation')
            ->icon('heroicon-o-lock-closed')
            ->description('Please enter your password to confirm this action')
            ->schema([$this->getPasswordField()]);
    }

    /**
     * Get payment details step for wizards
     */
    protected function getPaymentDetailsStep(string $description): Step
    {
        return Step::make('Payment Details')
            ->icon('heroicon-o-credit-card')
            ->description($description)
            ->schema($this->form(new Form($this))->getComponents());
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
                fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Hash::check($value, auth()->user()->password)) {
                        $fail('The password is incorrect.');
                    }
                },
            ]);
    }

    /**
     * Get active membership plans for the member's gym
     */
    protected function getActiveMembershipPlans(): array
    {
        /** @var GymMember $member */
        $member = $this->ownerRecord;

        if (! $member->gym_id) {
            return [];
        }

        return MembershipPlan::query()
            ->where('gym_id', $member->gym_id)
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Update membership dates based on selected plan
     */
    protected function updateMembershipDates(mixed $state, callable $set, callable $get): void
    {
        if (! $state) {
            return;
        }

        $plan = MembershipPlan::query()->find($state);
        if (! $plan || ! $plan->duration) {
            return;
        }

        $start = $get('membership_start') ?? now();
        $set('membership_end', Date::parse($start)->addMonths($plan->duration)->toDateString());
        $set('amount', $plan->price);
    }

    /**
     * Update end date when start date changes
     */
    protected function updateEndDate(mixed $state, callable $set, callable $get): void
    {
        $planId = $get('membership_plan_id');

        if (! $planId || ! $state) {
            return;
        }

        $plan = MembershipPlan::query()->find($planId);
        if (! $plan || ! $plan->duration) {
            return;
        }

        $set('membership_end', Date::parse($state)->addMonths($plan->duration)->toDateString());
    }

    /**
     * Get currency symbol based on selected currency
     */
    protected function getCurrencySymbol(callable $get): string
    {
        $currency = Currency::tryFrom($get('currency') ?? Currency::INR->value);

        return $currency?->getSymbol() ?? Currency::INR->getSymbol();
    }

    /**
     * Handle payment creation
     */
    protected function handleCreatePayment(array $data): void
    {
        /** @var GymMember $member */
        $member = $this->ownerRecord;

        // Get and expire previous completed payment
        $lastPayment = $this->getLastCompletedPayment($member->id);

        // Create the new payment
        $paymentData = collect($data)->except(['password'])->toArray();
        $this->getRelationship()->create($paymentData);

        // Update member with new payment data
        $this->updateMemberFromPayment($member, $paymentData);

        // Expire previous payment if exists
        if ($lastPayment) {
            $this->expirePayment($lastPayment);
        }

        $this->sendNotification('Payment Created', 'The payment has been created successfully.');
    }

    /**
     * Handle payment update
     */
    protected function handleUpdatePayment(array $data, MemberPayment $record): void
    {
        $paymentData = collect($data)->except(['password'])->toArray();
        $record->update($paymentData);

        /** @var GymMember $member */
        $member = $this->ownerRecord;
        $this->updateMemberFromPayment($member, $paymentData);

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
        ]);
    }

    /**
     * Get last completed payment for a member
     */
    protected function getLastCompletedPayment(int $memberId): ?MemberPayment
    {
        return $this->getRelationship()
            ->where('member_id', $memberId)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->latest('payment_date')
            ->first();
    }

    /**
     * Mark a payment as expired
     */
    protected function expirePayment(MemberPayment $payment): void
    {
        $payment->update(['status' => PaymentStatus::EXPIRED->value]);
    }

    /**
     * Prevent editing expired payments
     */
    protected function preventExpiredPaymentEdit(MemberPayment $record): void
    {
        if ($record->status->value !== PaymentStatus::EXPIRED->value) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Action Not Allowed')
            ->body('This payment is expired and cannot be edited. Please create a new payment for corrections.')
            ->persistent()
            ->send();

        throw new \Exception('Cannot edit an expired payment.');
    }

    /**
     * Check if user can delete a payment
     */
    protected function canDeletePayment(MemberPayment $record): bool
    {
        return auth()->user()->hasPermissionTo('delete_gym_member')
            && $record->status->value !== PaymentStatus::EXPIRED->value;
    }

    /**
     * Sort payments by status and date
     */
    protected function sortPayments(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
            ->latest('payment_date')->latest();
    }

    /**
     * Send success notification to current user
     */
    protected function sendNotification(string $title, string $body): void
    {
        $recipient = auth()->user();

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->sendToDatabase($recipient)
            ->send();

        event(new DatabaseNotificationsSent($recipient));
    }
}
