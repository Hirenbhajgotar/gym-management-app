<?php

namespace App\Jobs;

use App\Models\MemberPayment;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice', [
                'error' => $e->getMessage(),
                'payment_id' => $this->payment->id,
            ]);
        }
    }
}
