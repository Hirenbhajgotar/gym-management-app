<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Admin\Resources\GymMemberResource\Forms\PaymentForm;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendNotificationJob;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Notifications\Member\WelcomeNotification;
use App\Traits\HasTenantAccess;
use DB;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class CreateGymMember extends CreateRecord
{
    use HasTenantAccess, HasWizard;

    protected static string $resource = GymMemberResource::class;

    /** @var array<string, mixed> */
    protected $paymentData = [];

    /** @var array<string, mixed> */
    protected $measurementData = [];

    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(true),
            ])
            ->columns(null);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gym_id'] = $this->getGymId();
        $data['user_id'] = Auth::id();

        // Store measurement data
        $this->measurementData = [
            'weight' => $data['weight'] ?? null,
            'height' => $data['height'] ?? null,
        ];

        // Store payment data for use in afterCreate
        $this->paymentData = [
            'gym_id' => $data['gym_id'],
            'membership_plan_id' => $data['membership_plan_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'membership_start' => $data['membership_start'] ?? null,
            'membership_end' => $data['membership_end'] ?? null,
            'status' => $data['status'] ?? null,
            'payment_date' => $data['payment_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        unset(
            $data['membership_plan_id'],
            $data['amount'],
            $data['discount_amount'],
            $data['amount_paid'],
            $data['currency'],
            $data['payment_method'],
            $data['membership_start'],
            $data['membership_end'],
            $data['status'],
            $data['payment_date'],
            $data['notes'],
            $data['weight'],
            $data['height']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            /** @var GymMember $member */
            $member = $this->record;
            $member = GymMember::query()->lockForUpdate()->findOrFail($member->id);

            /** @var MembershipPlan $plan */
            $plan = MembershipPlan::query()->findOrFail($this->paymentData['membership_plan_id']);

            $rawDiscount = $this->paymentData['discount_amount'] ?? 0;
            $discountAmount = (float) (is_numeric($rawDiscount) ? $rawDiscount : 0);
            $freshAmount = ((float) $plan->price) - $discountAmount;

            // Create measurement if provided
            if (filled($this->measurementData['weight']) && filled($this->measurementData['height'])) {
                $member->measurements()->create([
                    'weight' => $this->measurementData['weight'],
                    'height' => $this->measurementData['height'],
                    'measured_at' => now(),
                ]);
            }

            // Create the payment if payment data is provided
            if (filled($this->paymentData['membership_plan_id'])) {
                /** @var array<string, mixed> $data */
                $data = $this->paymentData;
                $data['amount'] = max(0, $freshAmount);
                $payment = $member->payments()->create([
                    'member_id' => $member->id,
                    'gym_id' => $member->gym_id,
                    'membership_plan_id' => $data['membership_plan_id'],
                    'amount' => $data['amount'],
                    'discount_amount' => $data['discount_amount'] ?? 0,
                    'currency' => $data['currency'],
                    'payment_method' => $data['payment_method'],
                    'membership_start' => $data['membership_start'],
                    'membership_end' => $data['membership_end'],
                    'status' => $data['status'],
                    'payment_date' => $data['payment_date'],
                    'notes' => $data['notes'],
                ]);

                $amountPaid = $data['amount_paid'] ?? $data['amount'];
                $payment->transactions()->create([
                    'member_payment_id' => $payment->id,
                    'amount' => $amountPaid,
                    'payment_method' => $data['payment_method'],
                    'transaction_date' => $data['payment_date'],
                    'status' => 'completed',
                    'currency' => $data['currency'],
                ]);

                $payment->syncStatusFromTransactions();

                $member->update([
                    'gym_membership_plan_id' => $data['membership_plan_id'],
                    'membership_start' => $data['membership_start'],
                    'membership_end' => $data['membership_end'],
                    'payment_status' => $payment->status?->value,
                    'latest_payment_at' => $data['payment_date'],
                ]);

                // if ($member->gym_id) {
                //     MemberPaymentObserver::clearPaymentCaches($member->gym_id);
                // }

                Bus::chain([
                    new GenerateInvoiceJob($payment),
                    new SendNotificationJob($member, new WelcomeNotification($member)),
                ])->dispatch();

            } else {
                // If no payment, just send the notification immediately
                // Ensure required member data is available before sending
                if (blank($member->email)) {
                    Log::warning('No email provided for gym member', [
                        'member_id' => $member->id,
                    ]);

                    return;
                }
                dispatch(new SendNotificationJob($member, new WelcomeNotification($member)));
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return GymMemberResource::getUrl('index');
    }

    /** @return Step[] */
    protected function getSteps(): array
    {
        return [
            Step::make('Personal Information')
                ->icon('heroicon-o-user')
                ->description('Enter the member\'s personal details')
                ->schema([
                    Section::make()->schema(GymMemberResource::getDetailsFormSchema())->columns(),
                ]),

            Step::make('Membership Details')
                ->icon('heroicon-o-ticket')
                ->description('Configure membership settings')
                ->schema([
                    Section::make('Membership Details')
                        ->icon('heroicon-o-ticket')
                        ->description('Configure membership settings')
                        ->schema([
                            Select::make('trainer_id')
                                ->label('Assign Trainer')
                                ->options(function (callable $get) {
                                    return Trainer::query()->where('gym_id', $this->getGymId())
                                        ->available()
                                        ->pluck('name', 'id')
                                        ->prepend('None', null); // Allow "No Trainer" option
                                })
                                ->reactive()
                                ->nullable()
                                ->afterStateHydrated(function ($state, callable $set, $record) {
                                    if (! $state && $record) {
                                        $set('trainer_id', null);
                                    }
                                })
                                ->helperText('Leave as "None" if the member does not want a personal trainer.'),

                            ...PaymentForm::only(['membership_plan_id', 'membership_start', 'membership_end']),
                        ])
                        ->columns(2),

                    Section::make('Payment Details')
                        ->icon('heroicon-o-credit-card')
                        ->description('Record the member\'s payment')
                        ->schema([
                            ...PaymentForm::only(['amount', 'discount_amount', 'amount_paid', 'currency', 'payment_method', 'status', 'payment_date', 'notes']),
                        ])
                        ->columns(2),
                ]),
        ];
    }
}
