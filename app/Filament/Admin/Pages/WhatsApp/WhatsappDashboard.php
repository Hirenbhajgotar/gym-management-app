<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Actions\WhatsAppAccount\UpdateBillingModeAction;
use App\Enums\DefaultRole;
use App\Enums\WhatsAppBillingModeEnum;
use App\Filament\BasePages\BasePage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppUsageLog;
use Exception;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * @property-read ?WhatsAppAccount $whatsappAccount
 */
class WhatsappDashboard extends BasePage implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.tenant.pages.whatsapp-dashboard';

    public ?array $data = [];

    public bool $hasWhatsAppSetup = false;

    public function defaultRolePermissions(): array
    {
        return [];
    }

    // Only access for super_admin, admin and gym_owner
    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasAnyRole([
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
        $this->hasWhatsAppSetup = $this->whatsappAccount?->isSetupComplete() ?? false;
    }

    #[Computed(persist: true, cache: true)]
    public function creditBalance(): ?WhatsAppMessageCredit
    {
        $gymId = $this->getGymId();

        return WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();
    }

    #[Computed(persist: true, cache: true)]
    public function whatsappAccount(): ?WhatsAppAccount
    {
        $gymId = $this->getGymId();

        return WhatsAppAccount::query()->where('gym_id', $gymId)->first();
    }

    /**
     * @return array{messages_this_month: int, delivered: int, failed: int, delivery_rate: int|float}
     */
    #[Computed(persist: true, cache: true)]
    public function usageStats(): array
    {
        $stats = WhatsAppUsageLog::query()->where('gym_id', $this->getGymId())
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

    protected function getHeaderActions(): array
    {
        return [
            ActionsAction::make('changeBillingMode')
                ->label('Change Billing Mode')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading('Change Billing Mode')
                ->modalDescription('Update how Meta WhatsApp charges are handled for this account.')
                ->modalSubmitActionLabel('Update Billing Mode')
                ->fillForm(fn (WhatsAppAccount $account): array => [
                    'newBillingMode' => $account->billing_mode?->value,
                ])
                ->form([
                    Radio::make('newBillingMode')
                        ->label('Billing Mode')
                        ->options(WhatsAppBillingModeEnum::class)
                        ->descriptions(WhatsAppBillingModeEnum::casesToDescriptionsArray())
                        ->default(fn () => $this->whatsappAccount?->billing_mode?->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $billingMode = $data['newBillingMode'] ?? null;

                    if (! is_string($billingMode) || $billingMode === '') {
                        Notification::make()->title('No billing mode selected')->danger()->send();

                        return;
                    }

                    try {
                        $account = WhatsAppAccount::query()->where('gym_id', $this->getGymId())->first();

                        if (! $account) {
                            Notification::make()->title('No WhatsApp account found')->danger()->send();

                            return;
                        }

                        resolve(UpdateBillingModeAction::class)($account, WhatsAppBillingModeEnum::from($billingMode));

                        unset($this->whatsappAccount);

                        Notification::make()
                            ->title('Billing mode updated successfully')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Failed to update billing mode')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

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
