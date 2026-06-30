<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MemberPayment;
use App\Models\Payment;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment|MemberPayment $payment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        try {
            if ($this->payment instanceof MemberPayment) {
                $invoiceService->generateMemberInvoice($this->payment);
            } else {
                /** @var Payment $payment */
                $payment = $this->payment;
                $invoiceService->generateInvoice($payment);
            }
        } catch (Exception $e) {
            Log::error('Failed to generate invoice', [
                'error' => $e->getMessage(),
                'payment_id' => $this->payment->id,
            ]);
        }
    }
}
