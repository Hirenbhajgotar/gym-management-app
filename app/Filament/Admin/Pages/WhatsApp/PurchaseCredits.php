<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Models\Gym;
use App\Models\Payment;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use App\Models\WhatsAppPhoneNumber;
use App\Services\RazorpayService;
use App\Services\WhatsAppBillingService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class PurchaseCredits extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Purchase Credits';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.tenant.pages.whatsapp-purchase-credits';

    protected static ?int $navigationSort = 3;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public array $packages = [];

    public array $razorpayOrder = [];

    public bool $showPaymentModal = false;

    public ?int $selectedPackageId = null;

    public bool $isProcessingPayment = false;

    public float $phoneNumberSurcharge = 0.0;

    public bool $showSurcharge = false;

    public ?int $loadingPackageId = null;

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

        $this->loadSurchargeData();
    }

    private function loadSurchargeData(): void
    {
        $billingService = new WhatsAppBillingService(new RazorpayService);
        $surcharge = $billingService->getTwilioNumberSurcharge($this->getGymId());

        $this->showSurcharge = (bool) $surcharge['eligible'];
        $this->phoneNumberSurcharge = (float) $surcharge['amount'];
    }

    public function selectPackage(int $packageId): void
    {
        $gymId = $this->getGymId();
        $lockKey = "billing:whatsapp-package:select:{$gymId}";

        // Use Cache::lock() to prevent concurrent package selections / rapid double-clicks
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            Log::info('Lock not acquired');
            // Lock not acquired - another selection is already in progress
            Notification::make()
                ->title('Please wait')
                ->body('Another payment is being processed. Please try again in a moment.')
                ->info()
                ->send();

            return;
        }

        try {
            $this->selectedPackageId = $packageId;
            $this->showPaymentModal = true;
            $this->initiatePayment();
        } finally {
            $lock->release();
        }
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

            $totalAmount = $package->selling_price;

            $notes = [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'message_count' => $package->message_count,
                'gym_id' => $gym->id,
                'gym_name' => $gym->name,
            ];

            if ($this->showSurcharge && $this->phoneNumberSurcharge > 0) {
                $totalAmount += $this->phoneNumberSurcharge;
                $notes['phone_number_surcharge'] = $this->phoneNumberSurcharge;
            }

            $order = $razorpayService->createOrder([
                'amount' => $totalAmount,
                'currency' => 'INR',
                'receipt' => 'WA-' . $gym->id . '-' . uniqid(),
                'payment_type' => 'whatsapp_package',
                'gym_id' => $gym->id,
                'user_id' => $user?->id,
                'notes' => $notes,
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

            $this->dispatch('open-razorpay', [
                'key' => $order['key'],
                'amount' => $order['amount'] / 100,
                'order_id' => $order['order_id'],
                'package_name' => $package->name,
                'message_count' => $package->message_count,
            ]);
        } catch (\Exception $e) {
            Log::info('Payment Init Failed: ' . $e->getMessage());
            Notification::make()
                ->title('Payment Init Failed')
                ->danger()
                ->send();
        }
    }

    #[On('handle-payment-verify')]
    public function handlePaymentVerify(string $razorpayPaymentId, string $razorpaySignature, string $razorpayOrderId = ''): void
    {
        $this->isProcessingPayment = true;

        try {
            // Reconstruct component state from Payment DB record if lost
            // (e.g., user navigated away mid-payment and came back)
            if (empty($this->razorpayOrder['order_id']) && $razorpayOrderId !== '') {
                $paymentRecord = Payment::where('razorpay_order_id', $razorpayOrderId)->first();

                if (! $paymentRecord) {
                    throw new \Exception('Payment session expired. Please try again.');
                }

                $this->razorpayOrder = [
                    'order_id' => $paymentRecord->razorpay_order_id,
                ];
                $this->selectedPackageId = (int) ($paymentRecord->metadata['package_id'] ?? 0);
                $this->phoneNumberSurcharge = (float) ($paymentRecord->metadata['phone_number_surcharge'] ?? 0.0);
            }

            // Guard: verify we have the required state before proceeding
            if (empty($this->razorpayOrder['order_id']) || ! $this->selectedPackageId) {
                throw new \Exception('Payment session data is missing. Please try again.');
            }

            $razorpayService = new RazorpayService;

            $payment = $razorpayService->verifyPayment(
                $this->razorpayOrder['order_id'],
                $razorpayPaymentId,
                $razorpaySignature
            );

            if ($payment && $payment->isCompleted()) {
                $whatsAppBillingService = new WhatsAppBillingService($razorpayService);

                $result = $whatsAppBillingService->purchasePackage(
                    $this->getGymId(),
                    $this->selectedPackageId,
                    $razorpayPaymentId,
                    null,
                    $this->phoneNumberSurcharge
                );

                if ($result['success']) {
                    if ($this->phoneNumberSurcharge > 0) {
                        $phone = WhatsAppPhoneNumber::whereHas('account', function ($query) {
                            $query->where('gym_id', $this->getGymId());
                        })->first();

                        if ($phone) {
                            $phone->update(['last_number_cost_charged_at' => now()]);
                        }
                    }

                    Notification::make()
                        ->title('Payment Successful!')
                        ->body("You've received {$result['message_count']} message credits.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Payment Verified')
                        ->body('Failed to add credits. Please contact support.')
                        ->warning()
                        ->send();
                }

                $this->loadData();
                $this->showPaymentModal = false;
                $this->selectedPackageId = null;
                $this->razorpayOrder = [];
            }
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'razorpay_order_id' => $this->razorpayOrder['order_id'] ?? 'N/A',
                'razorpay_package_id' => $this->selectedPackageId,
            ]);
            Notification::make()
                ->title('Payment Verification Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isProcessingPayment = false;
        }
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->selectedPackageId = null;
        $this->razorpayOrder = [];
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

            $this->redirect(
                route('filament.admin.pages.whatsapp-dashboard', ['tenant' => $this->getTenantSlug()]),
                navigate: true
            );

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
        return $this->getGym()->id;
    }

    protected function getGym(): Gym
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Gym) {
            throw new \Exception('No gym tenant context available.');
        }

        return $tenant;
    }

    public function getTenantSlug(): string
    {
        return $this->getGym()->slug;
    }

    public function getFormattedSurcharge(): string
    {
        return number_format($this->phoneNumberSurcharge, 2);
    }
}
