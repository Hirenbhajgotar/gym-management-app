<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use App\Services\RazorpayService;
use App\Services\WhatsAppBillingService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PurchaseCredits extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Purchase Credits';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.tenant.pages.whatsapp-purchase-credits';

    protected static ?int $navigationSort = 2;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public array $packages = [];

    public array $razorpayOrder = [];

    public bool $showPaymentModal = false;

    public ?int $selectedPackageId = null;

    public function mount(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $gymId = $this->getGymId();

        $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        $this->packages = WhatsAppPackage::query()->active()
            ->ordered()
            ->get()
            ->toArray();
    }

    public function selectPackage(int $packageId): void
    {
        $this->selectedPackageId = $packageId;
        $this->showPaymentModal = true;
    }

    public function initiatePayment(): void
    {
        try {
            $package = WhatsAppPackage::find($this->selectedPackageId);

            if (! $package) {
                throw new \Exception('Package not found');
            }

            $gym = $this->getGym();
            $user = Auth::user();

            $razorpayService = new RazorpayService;

            $order = $razorpayService->createOrder([
                'amount' => $package->selling_price,
                'currency' => 'INR',
                'receipt' => 'WA-' . $gym->id . '-' . uniqid(),
                'payment_type' => 'whatsapp_package',
                'gym_id' => $gym->id,
                'user_id' => $user?->id,
                'notes' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'message_count' => $package->message_count,
                    'gym_id' => $gym->id,
                    'gym_name' => $gym->name,
                ],
                'description' => "WhatsApp Package: {$package->name} ({$package->message_count} messages)",
            ]);

            $this->razorpayOrder = [
                'order_id' => $order['order_id'],
                'amount' => $order['amount'] / 100,
                'key' => $order['key'],
                'payment_id' => $order['payment_id'],
                'package_name' => $package->name,
                'message_count' => $package->message_count,
            ];

            $this->js('window.location.reload()');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Payment Init Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function handlePaymentSuccess(string $razorpayPaymentId, string $razorpaySignature): void
    {
        try {
            $razorpayService = new RazorpayService;

            $payment = $razorpayService->verifyPayment(
                $this->razorpayOrder['order_id'],
                $razorpayPaymentId,
                $razorpaySignature
            );

            if ($payment && $payment->isCompleted()) {
                Notification::make()
                    ->title('Payment Successful!')
                    ->body("You've received {$this->razorpayOrder['message_count']} message credits.")
                    ->success()
                    ->send();

                $this->loadData();
                $this->showPaymentModal = false;
                $this->selectedPackageId = null;
                $this->razorpayOrder = [];
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Payment Verification Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function claimWelcomeCredits(): void
    {
        try {
            $whatsAppBillingService = new WhatsAppBillingService(new RazorpayService);

            $whatsAppBillingService->addWelcomeCredits($this->getGymId());

            Notification::make()
                ->title('Welcome Credits Added!')
                ->body('You now have 50 free message credits to get started.')
                ->success()
                ->send();

            $this->loadData();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Claim Credits')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getWelcomeCreditsRemaining(): int
    {
        if (! $this->creditBalance) {
            return 50;
        }

        return $this->creditBalance->hasWelcomeCredits()
            ? $this->creditBalance->getWelcomeCreditsRemaining()
            : 50;
    }

    public function hasClaimedWelcomeCredits(): bool
    {
        return $this->creditBalance?->welcome_credits_claimed ?? false;
    }

    public function canClaimWelcomeCredits(): bool
    {
        return ! $this->hasClaimedWelcomeCredits();
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
        return $this->getGym()->slug;
    }
}
