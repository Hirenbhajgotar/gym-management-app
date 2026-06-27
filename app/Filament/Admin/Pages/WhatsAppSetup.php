<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasFormInitialization;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasSetupState;
use App\Filament\Admin\WhatsAppSetup\Concerns\SetupActions;
use App\Filament\Admin\WhatsAppSetup\Schemas\SetupSchema;
use App\Filament\BasePages\BasePage;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;

/**
 * WhatsApp Setup page — thin orchestrator (~60 lines).
 * All state, schemas, and actions are delegated to traits.
 */
class WhatsAppSetup extends BasePage implements HasActions, HasForms
{
    use HasFormInitialization, HasSetupState, HasTenantAccess, InteractsWithActions, InteractsWithForms, SetupActions;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Setup';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    protected static ?int $navigationSort = 2;

    /**
     * Cached provider instance for lazy loading.
     *
     * @var MetaWhatsAppProvider|TwilioWhatsAppProvider|null
     */
    protected $cachedProvider;

    public function defaultRolePermissions(): array
    {
        return [];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ]);
    }

    public function mount(): void
    {
        // $this->useIndividualWaba = (bool) config('services.twilio.use_individual_waba', false);
        $this->loadWhatsAppData();
        $this->determineViewState();
        $this->initializeFormData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(SetupSchema::make($this))
            ->statePath('data');
    }

    public function refresh(): void
    {
        $this->initializeFormData();
        $this->cachedProvider = null;
        $this->data = array_merge($this->data, ['provider_type' => $this->selectedProvider]);
    }

    public function refreshWizard(): void
    {
        $this->selectedProvider = is_string($this->data['provider_type'] ?? null) ? $this->data['provider_type'] : $this->selectedProvider;
        $this->cachedProvider = null;
        $this->form->fill();
    }

    public function updatedProviderType(?string $value): void
    {
        if ($value) {
            $this->selectedProvider = $value;
            $this->cachedProvider = null;
        }
    }

    /**
     * Get the WhatsApp provider instance for the current gym.
     * Uses cached instance for performance.
     */
    public function getProvider(): MetaWhatsAppProvider|TwilioWhatsAppProvider|null
    {
        if ($this->cachedProvider === null) {
            $gymId = $this->getGymId();

            $this->cachedProvider = match ($this->selectedProvider) {
                WhatsAppProviderTypeEnum::TWILIO->value => new TwilioWhatsAppProvider((int) $gymId),
                WhatsAppProviderTypeEnum::WABA->value => new MetaWhatsAppProvider((int) $gymId),
                default => null,
            };
        }

        return $this->cachedProvider;
    }
}
