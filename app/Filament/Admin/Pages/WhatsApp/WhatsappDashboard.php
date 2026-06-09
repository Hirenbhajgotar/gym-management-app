<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Enums\DefaultRole;
use App\Filament\BasePages\BasePage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppUsageLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class WhatsappDashboard extends BasePage implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.tenant.pages.whatsapp-dashboard';

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public array $usageStats = [];

    public array $recentMessages = [];

    public ?array $data = [];

    public bool $hasWhatsAppSetup = false;

    public function defaultRolePermissions(): array
    {
        return [];
    }

    // Only access for super_admin, admin and gym_owner
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
        $this->loadData();
    }

    private function loadData(): void
    {
        $gymId = $this->getGymId();

        $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();
        $this->hasWhatsAppSetup = $this->whatsappAccount?->isSetupComplete() ?? false;
        $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        $this->usageStats = $this->getUsageStats();
        $this->recentMessages = $this->getRecentMessages();
    }

    private function getUsageStats(): array
    {
        $stats = WhatsAppUsageLog::where('gym_id', $this->getGymId())
            ->where('sent_at', '>=', now()->startOfMonth())
            ->selectRaw('
                COUNT(*) as messages_this_month,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed
            ', ['delivered', 'failed'])
            ->first();

        $messages = (int) $stats->messages_this_month;
        $delivered = (int) $stats->delivered;

        return [
            'messages_this_month' => $messages,
            'delivered' => $delivered,
            'failed' => (int) $stats->failed,
            'delivery_rate' => $messages > 0 ? round(($delivered / $messages) * 100, 1) : 0,
        ];
    }

    private function getRecentMessages(): array
    {
        return WhatsAppUsageLog::where('gym_id', $this->getGymId())
            ->orderBy('sent_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Credit Balance')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Placeholder::make('total_credits')
                                ->label('Total Credits')
                                ->content(fn () => $this->creditBalance?->total_credits ?? 0),

                            Forms\Components\Placeholder::make('remaining_credits')
                                ->label('Remaining')
                                ->content(fn () => $this->creditBalance?->remaining_credits ?? 0),

                            Forms\Components\Placeholder::make('usage_percentage')
                                ->label('Usage')
                                ->content(fn () => ($this->creditBalance?->getUsagePercentage() ?? 0) . '%'),
                        ]),

                        /*
                        Forms\Components\ProgressBar::make('credits_usage')
                            ->label('Credit Usage')
                            ->value(fn () => $this->creditBalance?->getUsagePercentage() ?? 0)
                            ->color('success')
                            ->visible(fn () => $this->creditBalance !== null),
                        */
                    ]),

                Forms\Components\Section::make('Monthly Usage')
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Placeholder::make('messages')
                                ->label('Messages Sent')
                                ->content($this->usageStats['messages_this_month'] ?? 0),

                            Forms\Components\Placeholder::make('delivered')
                                ->label('Delivered')
                                ->content($this->usageStats['delivered'] ?? 0),

                            Forms\Components\Placeholder::make('failed')
                                ->label('Failed')
                                ->content($this->usageStats['failed'] ?? 0),

                            Forms\Components\Placeholder::make('delivery_rate')
                                ->label('Delivery Rate')
                                ->content(($this->usageStats['delivery_rate'] ?? 0) . '%'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    // public function sendTestMessageAction(): Action
    // {
    //     return Action::make('sendTestMessage')
    //         ->label('Send Test Message')
    //         ->color('info')
    //         ->form([
    //             PhoneInput::make('phone_number')
    //                 ->label('Phone Number')
    //                 ->required()
    //                 ->defaultCountry('IN')
    //                 ->placeholder('+91 XXXXX XXXXX'),
    //         ])
    //         ->action(fn (array $data) => $this->sendTestMessage($data));
    // }

    // private function sendTestMessage(array $data): void
    // {
    //     try {
    //         $phoneNumber = $data['phone_number'] ?? null;

    //         if (! $phoneNumber) {
    //             throw new \Exception('Phone number is required');
    //         }

    //         $service = new \App\Services\TwilioWhatsAppService((string) $this->getGymId());

    //         $result = $service->sendTemplateMessage(
    //             $phoneNumber,
    //             'welcome_message',
    //             [
    //                 '1' => $this->getGym()?->name ?? 'Gym',
    //                 '2' => Auth::user()?->name ?? 'User',
    //                 '3' => 'Premium',
    //                 '4' => now()->addDays(30)->format('F j, Y'),
    //             ]
    //         );

    //         if ($result['success']) {
    //             Notification::make()
    //                 ->title('Test Message Sent!')
    //                 ->success()
    //                 ->send();
    //         } else {
    //             throw new \Exception($result['error'] ?? 'Failed to send');
    //         }
    //     } catch (\Exception $e) {
    //         Notification::make()
    //             ->title('Failed to Send')
    //             ->body($e->getMessage())
    //             ->danger()
    //             ->send();
    //     }
    // }

    public function refresh(): void
    {
        $this->loadData();
    }

    protected function getGymId(): int
    {
        return Filament::getTenant()->id;
    }

    protected function getGym()
    {
        return Filament::getTenant();
    }

    public function getTenantSlug(): string
    {
        return Filament::getTenant()->slug;
    }
}
