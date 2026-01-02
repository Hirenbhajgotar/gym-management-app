<?php

namespace App\Services;

use App\Models\MemberPayment;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceService
{
    /**
     * Generate an invoice for a payment
     */
    public function generateInvoice(Payment $payment): void
    {
        if ($this->invoiceExists($payment)) {
            return;
        }

        $this->ensureInvoiceNumber($payment);

        $data = [
            'payment' => $payment,
            'user' => $payment->user,
            'gym' => $payment->gym,
            'invoice_number' => $payment->invoice_number,
            'date' => $payment->created_at->format('M d, Y'),
            'type' => 'system_payment',
        ];

        $this->createPdf('invoices.default', $data, $payment);
    }

    public function generateMemberInvoice(MemberPayment $payment): void
    {
        if ($this->invoiceExists($payment)) {
            return;
        }

        $this->ensureInvoiceNumber($payment);

        $data = [
            'payment' => $payment,
            'user' => $payment->member,
            'gym' => $payment->gym,
            'invoice_number' => $payment->invoice_number,
            'date' => $payment->created_at->format('M d, Y'),
            'type' => 'member_payment',
        ];

        $this->createPdf('invoices.member', $data, $payment);
    }

    private function invoiceExists(Payment|MemberPayment $payment): bool
    {
        return $payment->invoice_path && Storage::exists($payment->invoice_path);
    }

    private function ensureInvoiceNumber(Payment|MemberPayment $payment): void
    {
        if (! $payment->invoice_number) {
            $date = $payment->created_at->format('Ymd');
            $payment->invoice_number = "INV-{$date}-{$payment->gym_id}-{$payment->id}";
            $payment->saveQuietly();
        }
    }

    private function createPdf(string $view, array $data, Payment|MemberPayment $payment): void
    {
        // Generate PDF
        $pdf = Pdf::loadView($view, $data);

        // Save PDF to storage
        $path = "invoices/{$payment->invoice_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        // Update payment with invoice path
        $payment->invoice_path = $path;
        $payment->saveQuietly();
    }

    /**
     * Download the invoice for a payment
     */
    public function downloadInvoice(Payment|MemberPayment $payment): StreamedResponse
    {
        $this->ensureInvoiceExists($payment);

        return Storage::disk('local')->download($payment->invoice_path, "{$payment->invoice_number}.pdf");
    }

    /**
     * View the invoice for a payment inline
     */
    public function viewInvoice(Payment|MemberPayment $payment): StreamedResponse
    {
        $this->ensureInvoiceExists($payment);

        return Storage::disk('local')->response($payment->invoice_path, "{$payment->invoice_number}.pdf", [
            'Content-Disposition' => 'inline; filename="' . $payment->invoice_number . '.pdf"',
        ]);
    }

    private function ensureInvoiceExists(Payment|MemberPayment $payment): void
    {
        if (! $payment->invoice_path || ! Storage::exists($payment->invoice_path)) {
            if ($payment instanceof MemberPayment) {
                $this->generateMemberInvoice($payment);
            } else {
                $this->generateInvoice($payment); // @phpstan-ignore-line
            }
        }
    }
}
