<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Mail\WelcomeMemberMail;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Notifications\Member\WelcomeNotification;
use App\Notifications\MemberActionNotification;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class CreateGymMember extends CreateRecord
{
    use HasWizard;

    protected static string $resource = GymMemberResource::class;

    protected $paymentData = [];

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

    // protected function getFormSchema(): array
    // {
    //     $parentSchema = parent::getFormSchema();
    //     $parentSchema[] = Forms\Components\Section::make('Payment Option')
    //         ->icon('heroicon-o-currency-dollar')
    //         ->description('Optionally add an initial payment after creating the member')
    //         ->schema([
    //             Forms\Components\Checkbox::make('add_payment')
    //                 ->label('Add initial payment after creation')
    //                 ->default(true)
    //                 ->live(),
    //         ])
    //         ->collapsible();

    //     return $parentSchema;
    // }

    // protected function getFormActions(): array
    // {
    //     return [
    //         $this->getCreateFormAction()->label('Save Member'),
    //         Action::make('cancel')
    //             ->label('Cancel')
    //             ->action(fn() => $this->redirect(GymMemberResource::getUrl('index')))
    //             ->color('gray'),
    //     ];
    // }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store payment data for use in afterCreate
        $this->paymentData = [
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

        // Remove payment-related fields from the GymMember data
        unset(
            $data['membership_plan_id'],
            $data['amount'],
            $data['currency'],
            $data['payment_method'],
            $data['membership_start'],
            $data['membership_end'],
            $data['status'],
            $data['payment_date'],
            $data['notes']
        );

        return $data;
    }

    protected function afterCreate(): void
    {

        // if ($this->record->user) {
        //     $this->record->user->notify(new MemberActionNotification('created', $this->record));
        // } else {
        //     // Fallback: Notify current authenticated user
        //     auth()->user()->notify(new MemberActionNotification('created', $this->record));
        // }

        // Send email to the gym member
        try {
            if (empty($this->record->email)) {
                Log::warning('No email provided for gym member', [
                    'member_id' => $this->record->id,
                ]);

                return;
            }

            // Ensure required member data is available
            if (
                ! isset($this->paymentData['membership_plan_id']) ||
                ! isset($this->paymentData['membership_start']) ||
                ! isset($this->paymentData['membership_end'])
            ) {
                Log::error('Missing required member data for email notification', [
                    'member_id' => $this->record->id,
                    'has_plan' => isset($this->paymentData['membership_plan_id']),
                    'has_start_date' => isset($this->paymentData['membership_start']),
                    'has_end_date' => isset($this->paymentData['membership_end']),
                ]);

                return;
            }

            // Mail::to($this->record->email)->send(new WelcomeMemberMail($this->record));

            Notification::send($this->record, new WelcomeNotification($this->record));
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email to member', [
                'member_id' => $this->record->id,
                'email' => $this->record->email ?? 'not_set',
                'error' => $e->getMessage(),
            ]);
        }

        // Create the payment if payment data is provided
        if (! empty($this->paymentData['membership_plan_id'])) {
            $this->record->payments()->create([
                'member_id' => $this->record->id,
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
            $this->record->update([
                'gym_membership_plan_id' => $this->paymentData['membership_plan_id'],
                'membership_start' => $this->paymentData['membership_start'],
                'membership_end' => $this->paymentData['membership_end'],
                'payment_status' => $this->paymentData['status'],
            ]);
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
                ->default(fn() => auth()->id()),

            Forms\Components\Section::make('Membership Details')
                ->icon('heroicon-o-ticket')
                ->description('Configure membership settings')
                ->schema([
                    Forms\Components\Select::make('gym_id')
                        ->relationship('gym', 'name', function ($query) {
                            if (auth()->user()->hasRole('gym_owner')) {
                                return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
                            }

                            return $query;
                        })
                        ->required()
                        ->placeholder('Select a gym')
                        ->live()
                        ->rules([
                            'required_if:visible,true',
                            fn($get) => function ($attribute, $value, $fail) {
                                if (auth()->user()->hasRole('gym_owner') && ! $value) {
                                    $fail('You must create a gym before adding members.');
                                }
                            },
                        ]),

                    Forms\Components\Select::make('trainer_id')
                        ->label('Assign Trainer')
                        ->options(function (callable $get) {
                            $gymId = $get('gym_id');
                            if (! $gymId) {
                                return [];
                            }

                            return Trainer::where('gym_id', $gymId)
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
                            $gymId = $get('gym_id');
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
                        ->required()
                        ->default(now()),
                ])
                ->columns(2),

            Forms\Components\Section::make('Payment Details')
                ->icon('heroicon-o-credit-card')
                ->description('Record the member\'s payment')
                ->schema([
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
                            fn($method) => [$method->value => $method->getLabel()]
                        ))
                        ->required()
                        ->default(Currency::INR->value)
                        ->reactive(),

                    Forms\Components\Select::make('payment_method')
                        ->options(collect(PaymentMethod::cases())->mapWithKeys(
                            fn($method) => [$method->value => $method->getLabel()]
                        ))
                        ->required()
                        ->default(PaymentMethod::CASH->value),

                    Forms\Components\ToggleButtons::make('status')
                        ->inline()
                        ->options(PaymentStatus::class)
                        ->required(),

                    Forms\Components\DatePicker::make('payment_date')
                        ->required()
                        ->default(now()),

                    Forms\Components\Textarea::make('notes')
                        ->maxLength(65535)
                        ->placeholder('e.g., Paid via UPI ID: user@upi')
                        ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'])
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];
    }
}
