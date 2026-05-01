<?php

namespace App\Filament\Admin\Pages;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasFormInitialization;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasSetupActions;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasSetupSchemas;
use App\Filament\Admin\WhatsAppSetup\Concerns\HasSetupState;
use App\Interfaces\WhatsAppProviderInterface;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * WhatsApp Setup page — thin orchestrator (~60 lines).
 * All state, schemas, and actions are delegated to traits.
 */
class WhatsAppSetup extends Page implements HasActions, HasForms
{
    use HasFormInitialization;
    use HasSetupActions;
    use HasSetupSchemas;
    use HasSetupState;
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Setup';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    protected static ?int $navigationSort = 2;

    protected ?WhatsAppProviderInterface $provider = null;

    public function mount(): void
    {
        $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);
        $this->loadWhatsAppData();
        $this->determineViewState();
        $this->initializeFormData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getWhatsAppFormSchema())
            ->statePath('data');
    }

    /**
     * Get the WhatsApp provider instance for the current gym.
     */
    public function getProvider(): MetaWhatsAppProvider|TwilioWhatsAppProvider|null
    {
        $gymId = $this->getGymId();

        return match ($this->selectedProvider) {
            WhatsAppProviderTypeEnum::TWILIO->value => new TwilioWhatsAppProvider((int) $gymId),
            WhatsAppProviderTypeEnum::WABA->value => new MetaWhatsAppProvider((int) $gymId),
            default => null,
        };
    }
}
