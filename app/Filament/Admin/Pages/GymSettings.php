<?php

namespace App\Filament\Admin\Pages;

use App\Models\GymSetting;
use App\Models\GymWhatsAppBalance;
use App\Models\Payment;
use App\Models\WhatsAppMessagePackage;
use App\Services\RazorpayService;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * @property Form $form
 */
class GymSettings extends Page implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static string $view = 'filament.admin.pages.gym-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $slug = 'settings';

    protected ?string $heading = 'Settings';

    public ?array $data = [];

    private const NOTIFICATION_KEYS = [
        'membership_expiry_notifications',
        'birthday_notifications',
        'attendance_reminders',
        'payment_reminders',
        'followup_reminders',
        'lead_reminders_notifications',
    ];

    public function mount(): void
    {
        $settings = GymSetting::where('gym_id', $this->getGym()->id)
            ->pluck('value', 'key')
            ->all();

        $this->form->fill(
            collect($settings)
                ->map(function ($value) {
                    if ($value === 'true') {
                        return true;
                    }

                    if ($value === 'false') {
                        return false;
                    }

                    return $value;
                })
                ->all(),
        );
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make('Settings')
                ->tabs([
                    $this->getNotificationTab(),
                    $this->getWhatsAppTab(),
                    // $this->getMembershipTab(),
                    // $this->getAttendanceTab(),
                ])
                ->columnSpanFull()
                ->persistTab()
                ->id('tenant-settings-tabs'),
        ]);
    }

    private function getNotificationTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('notifications')
            ->label('Notifications')
            ->icon('heroicon-o-bell')
            ->schema([
                Forms\Components\Tabs::make('Notification Preferences')->tabs([
                    $this->getWhatsAppNotificationTab(),
                ]),
            ]);
    }

    private function getWhatsAppNotificationTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('whatsapp')
            ->label('WhatsApp Settings')
            ->icon('heroicon-o-device-phone-mobile')
            ->schema([
                Forms\Components\Section::make('Notification Preferences')
                    ->description('Configure whatsapp notifications.')
                    ->schema([
                        Forms\Components\Toggle::make('whatsapp_notifications')
                            ->label('WhatsApp Notifications')
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(function (
                                callable $set,
                                $state,
                            ) {
                                $settings = [];
                                if ($state) {
                                    $settings = GymSetting::where('gym_id', $this->getGym()->id)
                                        ->whereIn('key', self::NOTIFICATION_KEYS)
                                        ->pluck('value', 'key')
                                        ->all();
                                }

                                foreach (self::NOTIFICATION_KEYS as $key) {
                                    $set($key, $state && ($settings[$key] ?? 'false') === 'true');
                                }
                            })
                            ->helperText(
                                'Enable/disable WhatsApp notifications for all notification types',
                            ),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Specific Notification Types')
                    ->description('Enable/disable specific notification types.')
                    ->schema([
                        Forms\Components\Toggle::make(
                            'membership_expiry_notifications',
                        )
                            ->label('Membership Expiry Notifications')
                            ->helperText(
                                'Send notifications before and after membership expiry',
                            ),

                        Forms\Components\Toggle::make('birthday_notifications')
                            ->label('Birthday Notifications')
                            ->helperText('Send birthday wishes to members'),

                        Forms\Components\Toggle::make('attendance_reminders')
                            ->label('Attendance Reminders')
                            ->helperText(
                                'Send reminders for regular attendance',
                            ),

                        Forms\Components\Toggle::make('payment_reminders')
                            ->label('Payment Reminders')
                            ->helperText(
                                'Send payment reminders for pending dues',
                            ),

                        Forms\Components\Toggle::make('followup_reminders')
                            ->label('Follow-up Reminders')
                            ->helperText(
                                'Send follow-up reminders for new leads',
                            ),

                        Forms\Components\Toggle::make('lead_reminders_notifications')
                            ->label('Lead Reminders')
                            ->helperText(
                                'Send reminders for pending leads that need attention',
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    private function getMembershipTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('membership')
            ->label('Membership Settings')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Forms\Components\Section::make('Membership Configuration')
                    ->schema([
                        Forms\Components\Select::make(
                            'default_membership_duration',
                        )
                            ->label('Default Membership Duration')
                            ->options([
                                '1' => '1 Month',
                                '3' => '3 Months',
                                '6' => '6 Months',
                                '12' => '1 Year',
                            ])
                            ->default('1'),

                        Forms\Components\TextInput::make('grace_period_days')
                            ->label('Grace Period (Days)')
                            ->numeric()
                            ->default(
                                config('subscription.grace_period_days', 7),
                            )
                            ->helperText(
                                'Number of days after subscription expiry when gym owner still has access',
                            ),

                        Forms\Components\Toggle::make('auto_renew_memberships')
                            ->label('Auto Renew Memberships')
                            ->default(false)
                            ->helperText(
                                'Automatically renew memberships when they expire',
                            ),

                        Forms\Components\Toggle::make(
                            'require_membership_for_attendance',
                        )
                            ->label('Require Active Membership for Attendance')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing Settings')
                    ->schema([
                        Forms\Components\TextInput::make(
                            'minimum_membership_amount',
                        )
                            ->label('Minimum Membership Amount')
                            ->numeric()
                            ->prefix('₹'),

                        Forms\Components\Toggle::make('show_prices_to_members')
                            ->label('Show Prices to Members')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    private function getAttendanceTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('attendance')
            ->label('Attendance Settings')
            ->icon('heroicon-o-user-group')
            ->schema([
                Forms\Components\Section::make('Attendance Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('require_check_in')
                            ->label('Require Check-in/Check-out')
                            ->default(true)
                            ->helperText('Members must check in and out'),

                        Forms\Components\Select::make('check_in_method')
                            ->label('Check-in Method')
                            ->options([
                                'qr' => 'QR Code',
                                'manual' => 'Manual Entry',
                                'rfid' => 'RFID Card',
                            ])
                            ->default('qr'),

                        Forms\Components\TextInput::make('max_daily_visits')
                            ->label('Maximum Daily Visits')
                            ->numeric()
                            ->default(2)
                            ->helperText(
                                'Maximum number of visits per day allowed',
                            ),

                        Forms\Components\Toggle::make('send_attendance_summary')
                            ->label('Send Daily Attendance Summary')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    private function getWhatsAppTab(): Forms\Components\Tabs\Tab
    {
        $gymId = $this->getGym()->id;
        $balance = GymWhatsAppBalance::where('gym_id', $gymId)->first();

        return Forms\Components\Tabs\Tab::make('whatsapp')
            ->label('WhatsApp Messages')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->schema([
                Forms\Components\Section::make('Message Balance')
                    ->description(
                        'Current WhatsApp message balance and usage statistics.',
                    )
                    ->schema([
                        Forms\Components\Placeholder::make('remaining_messages')
                            ->label('Remaining Messages')
                            ->content($balance?->balance_messages ?? 0),

                        Forms\Components\Placeholder::make('total_purchased')
                            ->label('Total Purchased')
                            ->content($balance?->total_messages_purchased ?? 0),

                        Forms\Components\Placeholder::make('messages_used')
                            ->label('Messages Used')
                            ->content($balance?->messages_used ?? 0),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Purchase Message Packages')
                    ->description('Buy additional WhatsApp message packages.')
                    ->schema([
                        Forms\Components\Select::make('message_package')
                            ->label('Select Package')
                            ->options(
                                WhatsAppMessagePackage::active()
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(function ($package) {
                                        $label =
                                            $package->name .
                                            ' (' .
                                            $package->message_count .
                                            ' messages - ₹' .
                                            $package->price .
                                            ')';

                                        return [$package->id => $label];
                                    }),
                            )
                            ->placeholder('Choose a package')
                            ->helperText(
                                'Select a message package to purchase',
                            ),
                    ])
                    ->columns(1)
                    ->headerActions([$this->getPurchaseAction()]),

                Forms\Components\Section::make('Low Balance Alerts')
                    ->description(
                        'Configure alerts when message balance is low.',
                    )
                    ->schema([
                        Forms\Components\TextInput::make(
                            'low_balance_threshold',
                        )
                            ->label('Low Balance Threshold')
                            ->numeric()
                            ->default(100)
                            ->helperText(
                                'Send alert when remaining messages drop below this number',
                            ),

                        Forms\Components\Toggle::make(
                            'enable_low_balance_alerts',
                        )
                            ->label('Enable Low Balance Alerts')
                            ->default(true)
                            ->helperText(
                                'Receive notifications when message balance is low',
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Purchase History')
                    ->description('Recent WhatsApp package purchases and invoices.')
                    ->schema([
                        Forms\Components\ViewField::make('whatsapp_invoices')
                            ->view('filament.forms.components.whatsapp-invoice-table')
                            ->hiddenLabel(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $gym = $this->getGym();

        foreach ($data as $key => $value) {
            $this->saveSetting($gym->id, $key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully!')
            ->success()
            ->send();
    }

    private function saveSetting(int $gymId, string $key, $value): void
    {
        GymSetting::updateOrCreate(
            ['gym_id' => $gymId, 'key' => $key],
            ['value' => $value, 'type' => gettype($value)],
        );
    }

    public function getPurchaseAction(): Action
    {
        return Action::make('purchasePackage')
            ->label('Purchase Messages')
            ->icon('heroicon-o-shopping-cart')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirm Purchase')
            ->modalDescription(function () {
                $packageId = $this->form->getState()['message_package'] ?? null;
                if (! $packageId) {
                    return 'Please select a package first.';
                }

                $package = WhatsAppMessagePackage::find($packageId);
                if (! $package) {
                    return 'Invalid package selected.';
                }

                return "Are you sure you want to purchase the {$package->name} package for ₹{$package->price}? This will add {$package->message_count} messages to your balance.";
            })
            ->modalSubmitActionLabel('Purchase')
            ->action(function () {
                $packageId = $this->form->getState()['message_package'] ?? null;

                if (! $packageId) {
                    Notification::make()
                        ->title('No package selected')
                        ->body('Please select a message package to purchase.')
                        ->danger()
                        ->send();

                    return;
                }

                $package = WhatsAppMessagePackage::find($packageId);
                if (! $package) {
                    Notification::make()
                        ->title('Invalid package')
                        ->body('The selected package is not available.')
                        ->danger()
                        ->send();

                    return;
                }

                $gymId = $this->getGym()->id;

                // Create Razorpay order
                try {
                    $razorpayService = app(RazorpayService::class);

                    $orderData = $razorpayService->createOrder([
                        'amount' => $package->price,
                        'currency' => 'INR',
                        'receipt' => 'WAP-' .
                            $package->id .
                            '-' .
                            $gymId .
                            '-' .
                            uniqid(),
                        'gym_id' => $gymId,
                        'user_id' => Auth::id(),
                        'package_id' => $package->id,
                        'payment_type' => 'whatsapp_package',
                        'description' => "Purchase of {$package->name} - {$package->message_count} messages",
                        'notes' => [
                            'package_name' => $package->name,
                            'plan_name' => $package->name,
                            'message_count' => $package->message_count,
                        ],
                    ]);

                    // Reset the form field
                    $this->form->fill(['message_package' => null]);

                    // Redirect to payment page with order data
                    return redirect()->route('payments.checkout', [
                        'order_id' => $orderData['order_id'],
                        'amount' => $orderData['amount'],
                        'key' => $orderData['key'],
                        'package_name' => $package->name,
                        'message_count' => $package->message_count,
                        'tenantId' => $gymId,
                    ]);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Payment Error')
                        ->body('Failed to initiate payment. Please try again.')
                        ->danger()
                        ->send();

                    return;
                }
            });
    }

    public function downloadInvoice(int $paymentId)
    {
        $payment = Payment::where('gym_id', $this->getGym()->id)
            ->findOrFail($paymentId);

        return app(\App\Services\InvoiceService::class)->downloadInvoice($payment);
    }
}
