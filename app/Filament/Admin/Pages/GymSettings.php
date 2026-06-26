<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Services\InvoiceService;
use App\Enums\DefaultRole;
use App\Filament\BasePages\BasePage;
use App\Models\GymSetting;
use App\Models\Payment;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

/**
 * @property Form $form
 */
class GymSettings extends BasePage implements HasActions, HasForms
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
        'qr_checkin_otp_enabled',
    ];

    public function defaultRolePermissions(): array
    {
        return [];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ]) ?? false;
    }

    public function mount(): void
    {
        $settings = GymSetting::query()->where('gym_id', $this->getGym()->id)
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
            Tabs::make('Settings')
                ->tabs([
                    $this->getNotificationTab(),
                    // $this->getWhatsAppTab(),
                    // $this->getMembershipTab(),
                    // $this->getAttendanceTab(),
                ])
                ->columnSpanFull()
                ->persistTab()
                ->id('tenant-settings-tabs'),
        ]);
    }

    private function getNotificationTab(): Tab
    {
        return Tab::make('notifications')
            ->label('Notifications')
            ->icon('heroicon-o-bell')
            ->schema([
                Tabs::make('Notification Preferences')->tabs([
                    $this->getWhatsAppNotificationTab(),
                ]),
            ]);
    }

    private function getWhatsAppNotificationTab(): Tab
    {
        $gym = $this->getGym();
        $account = $gym->whatsAppAccount;
        $whatsappConfigured = $account?->isActive() && $account?->isVerified();

        return Tab::make('whatsapp')
            ->label('WhatsApp Settings')
            ->icon('heroicon-o-device-phone-mobile')
            ->schema([
                Section::make('WhatsApp Configuration Status')
                    ->schema([
                        ViewField::make('whatsapp_status')
                            ->view('filament.forms.components.whatsapp-status-indicator')
                            ->viewData([
                                'configured' => $whatsappConfigured,
                                'gym' => $gym,
                            ]),
                    ])
                    ->columns(1),

                Section::make('Notification Master Switch')
                    ->description('Enable or disable all WhatsApp notifications at once.')
                    ->schema([
                        Toggle::make('whatsapp_notifications')
                            ->label('WhatsApp Notifications')
                            ->default(true)
                            ->live()
                            ->disabled(! $whatsappConfigured)
                            ->afterStateUpdated(function (
                                callable $set,
                                $state,
                            ) {
                                $settings = [];
                                if ($state) {
                                    $settings = GymSetting::query()->where('gym_id', $this->getGym()->id)
                                        ->whereIn('key', self::NOTIFICATION_KEYS)
                                        ->pluck('value', 'key')
                                        ->all();
                                }

                                foreach (self::NOTIFICATION_KEYS as $key) {
                                    $set($key, $state && ($settings[$key] ?? 'false') === 'true');
                                }
                            })
                            ->helperText(
                                $whatsappConfigured
                                ? 'Enable/disable WhatsApp notifications for all notification types'
                                : 'Configure your WhatsApp account first to enable notifications',
                            ),
                    ])
                    ->columns(1),

                Section::make('Specific Notification Types')
                    ->description('Enable/disable specific notification types.')
                    ->schema([
                        Toggle::make(
                            'membership_expiry_notifications',
                        )
                            ->label('Membership Expiry Notifications')
                            ->helperText(
                                'Send notifications before and after membership expiry',
                            ),

                        Toggle::make('birthday_notifications')
                            ->label('Birthday Notifications')
                            ->helperText('Send birthday wishes to members'),

                        Toggle::make('attendance_reminders')
                            ->label('Attendance Reminders')
                            ->helperText(
                                'Send reminders for regular attendance',
                            ),

                        Toggle::make('payment_reminders')
                            ->label('Payment Reminders')
                            ->helperText(
                                'Send payment reminders for pending dues',
                            ),

                        Toggle::make('followup_reminders')
                            ->label('Follow-up Reminders')
                            ->helperText(
                                'Send follow-up reminders for new leads',
                            ),

                        Toggle::make('lead_reminders_notifications')
                            ->label('Lead Reminders')
                            ->helperText(
                                'Send reminders for pending leads that need attention',
                            ),

                        Toggle::make('qr_checkin_otp_enabled')
                            ->label('CheckIn/CheckOut OTP')
                            ->helperText(
                                'Require OTP verification for QR code attendance check-in',
                            )
                            // ->disabled(! $whatsappConfigured)
                            ->live(),
                    ])
                    ->columns(2),
            ]);
    }

    private function getMembershipTab(): Tab
    {
        return Tab::make('membership')
            ->label('Membership Settings')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Section::make('Membership Configuration')
                    ->schema([
                        Select::make(
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

                        TextInput::make('grace_period_days')
                            ->label('Grace Period (Days)')
                            ->numeric()
                            ->default(
                                config('subscription.grace_period_days', 7),
                            )
                            ->helperText(
                                'Number of days after subscription expiry when gym owner still has access',
                            ),

                        Toggle::make('auto_renew_memberships')
                            ->label('Auto Renew Memberships')
                            ->default(false)
                            ->helperText(
                                'Automatically renew memberships when they expire',
                            ),

                        Toggle::make(
                            'require_membership_for_attendance',
                        )
                            ->label('Require Active Membership for Attendance')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Pricing Settings')
                    ->schema([
                        TextInput::make(
                            'minimum_membership_amount',
                        )
                            ->label('Minimum Membership Amount')
                            ->numeric()
                            ->prefix('₹'),

                        Toggle::make('show_prices_to_members')
                            ->label('Show Prices to Members')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    private function getAttendanceTab(): Tab
    {
        return Tab::make('attendance')
            ->label('Attendance Settings')
            ->icon('heroicon-o-user-group')
            ->schema([
                Section::make('Attendance Configuration')
                    ->schema([
                        Toggle::make('require_check_in')
                            ->label('Require Check-in/Check-out')
                            ->default(true)
                            ->helperText('Members must check in and out'),

                        Select::make('check_in_method')
                            ->label('Check-in Method')
                            ->options([
                                'qr' => 'QR Code',
                                'manual' => 'Manual Entry',
                                'rfid' => 'RFID Card',
                            ])
                            ->default('qr'),

                        TextInput::make('max_daily_visits')
                            ->label('Maximum Daily Visits')
                            ->numeric()
                            ->default(2)
                            ->helperText(
                                'Maximum number of visits per day allowed',
                            ),

                        Toggle::make('send_attendance_summary')
                            ->label('Send Daily Attendance Summary')
                            ->default(false),
                    ])
                    ->columns(2),
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
        GymSetting::query()->updateOrCreate(['gym_id' => $gymId, 'key' => $key], ['value' => $value, 'type' => gettype($value)]);
    }

    public function downloadInvoice(int $paymentId)
    {
        $payment = Payment::query()->where('gym_id', $this->getGym()->id)
            ->findOrFail($paymentId);

        return resolve(InvoiceService::class)->downloadInvoice($payment);
    }
}
