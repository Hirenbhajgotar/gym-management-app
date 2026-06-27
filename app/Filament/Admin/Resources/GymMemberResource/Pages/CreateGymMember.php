<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use Filament\Forms\Components\Select;
use App\Models\GymMember;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendNotificationJob;
use App\Models\Trainer;
use App\Notifications\Member\WelcomeNotification;
use App\Traits\HasTenantAccess;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class CreateGymMember extends CreateRecord
{
    use HasTenantAccess, HasWizard;

    protected static string $resource = GymMemberResource::class;

    protected $paymentData = [];

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
        // Store measurement data
        $this->measurementData = [
            'weight' => $data['weight'] ?? null,
            'height' => $data['height'] ?? null,
        ];

        // Store payment data for use in afterCreate
        $this->paymentData = [
            'gym_id' => $data['gym_id'] ?? null,
            'membership_plan_id' => $data['membership_plan_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'membership_start' => $data['membership_start'] ?? null,
            'membership_end' => $data['membership_end'] ?? null,
            'status' => $data['status'] ?? null,
            'payment_date' => $data['payment_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // Remove payment-related fields and measurement fields from the GymMember data
        unset(
            $data['membership_plan_id'],
            $data['amount'],
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
        /** @var GymMember $member */
        $member = $this->record;

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
            $payment = $member->payments()->create([
                'member_id' => $member->id,
                'gym_id' => $member->gym_id,
                'membership_plan_id' => $this->paymentData['membership_plan_id'],
                'amount' => $this->paymentData['amount'],
                'currency' => $this->paymentData['currency'],
                'payment_method' => $this->paymentData['payment_method'],
                'membership_start' => $this->paymentData['membership_start'],
                'membership_end' => $this->paymentData['membership_end'],
                'status' => $this->paymentData['status'],
                'payment_date' => $this->paymentData['payment_date'],
                'notes' => $this->paymentData['notes'],
            ]);

            // Update the member's payment_status and membership dates
            $member->update([
                'gym_membership_plan_id' => $this->paymentData['membership_plan_id'],
                'membership_start' => $this->paymentData['membership_start'],
                'membership_end' => $this->paymentData['membership_end'],
                'payment_status' => $this->paymentData['status'],
                'latest_payment_at' => $this->paymentData['payment_date'],
            ]);

            // Chain Invoice Generation and Welcome Notification
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
                    Section::make()->schema($this->getMembershipFormSchema())->columns(),

                ]),
        ];
    }

    public function getMembershipFormSchema(): array
    {
        return [
            Hidden::make('user_id')
                ->default(fn () => auth()->id()),

            Section::make('Membership Details')
                ->icon('heroicon-o-ticket')
                ->description('Configure membership settings')
                ->schema([
                    Hidden::make('gym_id')
                        ->default(fn () => $this->getGymId()),

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

                    ...GymMemberResource::getMembershipPlanFormSchema($this->getGymId()),
                ])
                ->columns(2),

            ...GymMemberResource::getPaymentFormSchema(),

        ];
    }
}
