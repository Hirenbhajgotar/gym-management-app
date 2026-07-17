<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MemberPayment;
use App\Models\Payment;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * Create a new job instance.
     *
     * @param  string  $paymentType  Value: payment|member_payment
     */
    public function __construct(
        public Payment|MemberPayment $payment,
        public string $paymentType,
    ) {}

    public function uniqueId(): string
    {
        return $this->paymentType . ':' . $this->payment->id;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        try {
            if ($this->payment instanceof MemberPayment) {
                $invoiceService->generateMemberInvoice($this->payment);
            } else {
                $invoiceService->generateInvoice($this->payment);
            }
        } catch (Exception $e) {
            Log::error('Failed to generate invoice', [
                'error' => $e->getMessage(),
                'payment_id' => $this->payment->id,
            ]);

            throw $e;
        }
    }
}
