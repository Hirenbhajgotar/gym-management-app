<?php

namespace App\Filament\Admin\Pages;

use App\Models\GymSetting;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GymSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.gym-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $slug = 'settings';

    protected ?string $heading = 'Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = GymSetting::where('gym_id', Filament::getTenant()?->id)->pluck('value', 'key')->all();

        $this->form->fill(
            collect($settings)->map(function ($value) {
                if ($value === 'true') {
                    return true;
                }

                if ($value === 'false') {
                    return false;
                }

                return $value;
            })->all()
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        $this->getNotificationTab(),
                        // $this->getMembershipTab(),
                        // $this->getAttendanceTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function getNotificationTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('notifications')
            ->label('Notifications')
            ->icon('heroicon-o-bell')
            ->schema([
                Forms\Components\Tabs::make('Notification Preferences')
                    ->tabs([
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
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $settings = GymSetting::where('gym_id', Filament::getTenant()?->id)->pluck('value', 'key')->all();
                                    $set('membership_expiry_notifications', isset($settings['membership_expiry_notifications']) && $settings['membership_expiry_notifications'] === 'true' ? true : false);
                                    $set('birthday_notifications', isset($settings['birthday_notifications']) && $settings['birthday_notifications'] === 'true' ? true : false);
                                    $set('attendance_reminders', isset($settings['attendance_reminders']) && $settings['attendance_reminders'] === 'true' ? true : false);
                                    $set('payment_reminders', isset($settings['payment_reminders']) && $settings['payment_reminders'] === 'true' ? true : false);
                                    $set('followup_reminders', isset($settings['followup_reminders']) && $settings['followup_reminders'] === 'true' ? true : false);
                                } else {
                                    $set('membership_expiry_notifications', false);
                                    $set('birthday_notifications', false);
                                    $set('attendance_reminders', false);
                                    $set('payment_reminders', false);
                                    $set('followup_reminders', false);
                                }
                            })
                            ->helperText('Enable/disable WhatsApp notifications for all notification types'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Specific Notification Types')
                    ->description('Enable/disable specific notification types.')
                    ->schema([
                        Forms\Components\Toggle::make('membership_expiry_notifications')
                            ->label('Membership Expiry Notifications')
                            ->helperText('Send notifications before and after membership expiry'),

                        Forms\Components\Toggle::make('birthday_notifications')
                            ->label('Birthday Notifications')
                            ->helperText('Send birthday wishes to members'),

                        Forms\Components\Toggle::make('attendance_reminders')
                            ->label('Attendance Reminders')
                            ->helperText('Send reminders for regular attendance'),

                        Forms\Components\Toggle::make('payment_reminders')
                            ->label('Payment Reminders')
                            ->helperText('Send payment reminders for pending dues'),

                        Forms\Components\Toggle::make('followup_reminders')
                            ->label('Follow-up Reminders')
                            ->helperText('Send follow-up reminders for new leads'),
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
                        Forms\Components\Select::make('default_membership_duration')
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
                            ->default(3)
                            ->helperText('Number of days after expiry when member still has access'),

                        Forms\Components\Toggle::make('auto_renew_memberships')
                            ->label('Auto Renew Memberships')
                            ->default(false)
                            ->helperText('Automatically renew memberships when they expire'),

                        Forms\Components\Toggle::make('require_membership_for_attendance')
                            ->label('Require Active Membership for Attendance')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing Settings')
                    ->schema([
                        Forms\Components\TextInput::make('minimum_membership_amount')
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
                            ->helperText('Maximum number of visits per day allowed'),

                        Forms\Components\Toggle::make('send_attendance_summary')
                            ->label('Send Daily Attendance Summary')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $gym = Filament::getTenant();

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
            ['value' => $value, 'type' => gettype($value)]
        );
    }
}
