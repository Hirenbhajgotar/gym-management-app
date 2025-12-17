<?php

namespace App\Services;

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
        if ($payment->invoice_path && Storage::exists($payment->invoice_path)) {
            return;
        }

        // Generate invoice number if not exists
        if (! $payment->invoice_number) {
            $date = $payment->created_at->format('Ymd');
            $payment->invoice_number = "INV-{$date}-{$payment->id}";
            $payment->saveQuietly();
        }

        // Prepare data for the view
        $data = [
            'payment' => $payment,
            'user' => $payment->user,
            'gym' => $payment->gym,
            'invoice_number' => $payment->invoice_number,
            'date' => $payment->created_at->format('M d, Y'),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('invoices.default', $data);

        // Save PDF to storage
        $path = "invoices/{$payment->invoice_number}.pdf";
        Storage::put($path, $pdf->output());

        // Update payment with invoice path
        $payment->invoice_path = $path;
        $payment->saveQuietly();
    }

    /**
     * Download the invoice for a payment
     */
    public function downloadInvoice(Payment $payment): StreamedResponse
    {
        if (! $payment->invoice_path || ! Storage::exists($payment->invoice_path)) {
            // Try to generate it if it doesn't exist
            $this->generateInvoice($payment);
        }

        return Storage::download($payment->invoice_path, "{$payment->invoice_number}.pdf");
    }
}
