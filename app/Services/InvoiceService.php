<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\InvoiceablePayment;
use App\Enums\DefaultRole;
use App\Models\MemberPayment;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceService
{
    /**
     * Generate an invoice for a system payment.
     */
    public function generateInvoice(Payment $payment, bool $force = false): void
    {
        $this->generate($payment, 'invoices.default', 'system_payment', $force);
    }

    /**
     * Generate an invoice for a member payment.
     */
    public function generateMemberInvoice(MemberPayment $payment, bool $force = false): void
    {
        $this->generate($payment, 'invoices.member', 'member_payment', $force);
    }

    private function generate(InvoiceablePayment $payment, string $view, string $type, bool $force = false): void
    {
        if (! $force && $this->invoiceExists($payment)) {
            return;
        }

        $this->ensureInvoiceNumber($payment);

        $data = [
            'payment' => $payment,
            'user' => $payment->getInvoiceOwner(),
            'gym' => $payment->getGym(),
            'invoice_number' => $payment->getInvoiceNumber(),
            'date' => $payment->getCreatedAt()?->format('M d, Y') ?? now()->format('M d, Y'),
            'type' => $type,
        ];

        $this->createPdf($view, $data, $payment);
    }

    private function invoiceExists(InvoiceablePayment $payment): bool
    {
        $path = $payment->getInvoicePath();

        return $path !== null && $this->disk()->exists($path);
    }

    private function ensureInvoiceNumber(InvoiceablePayment $payment): void
    {
        if ($payment->getInvoiceNumber() !== null) {
            return;
        }

        $invoiceNumber = $this->buildInvoiceNumber($payment);
        $payment->setInvoiceNumber($invoiceNumber);
        $payment->saveInvoiceMetaQuietly();
    }

    private function createPdf(string $view, array $data, InvoiceablePayment $payment): void
    {
        try {
            $pdf = Pdf::loadView($view, $data);

            $path = "invoices/{$payment->getInvoiceNumber()}.pdf";
            $this->disk()->put($path, $pdf->output());

            $payment->setInvoicePath($path);
            $payment->saveInvoiceMetaQuietly();
        } catch (Exception $e) {
            Log::error('Failed to generate invoice', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->getInvoiceKey(),
                'invoice_number' => $payment->getInvoiceNumber(),
                'invoice_path' => $payment->getInvoicePath(),
            ]);

            throw $e;
        }
    }

    /**
     * Download the invoice for a payment.
     */
    public function downloadInvoice(InvoiceablePayment $payment): StreamedResponse
    {
        $this->assertAccessible($payment);

        $this->ensureInvoiceExists($payment);

        return $this->disk()->download($payment->getInvoicePath(), "{$payment->getInvoiceNumber()}.pdf");
    }

    /**
     * View the invoice for a payment inline.
     */
    public function viewInvoice(InvoiceablePayment $payment): StreamedResponse
    {
        $this->assertAccessible($payment);

        $this->ensureInvoiceExists($payment);

        return $this->disk()->response($payment->getInvoicePath(), "{$payment->getInvoiceNumber()}.pdf",
            [
                'Content-Disposition' => 'inline; filename="' . $payment->getInvoiceNumber() . '.pdf"',
            ]
        );
    }

    private function ensureInvoiceExists(InvoiceablePayment $payment): void
    {
        if ($payment->getInvoicePath() !== null && $this->disk()->exists($payment->getInvoicePath())) {
            return;
        }

        if ($payment instanceof MemberPayment) {
            $this->generate($payment, 'invoices.member', 'member_payment');
        } else {
            $this->generate($payment, 'invoices.default', 'system_payment');
        }
    }

    private function disk(): Filesystem
    {
        $name = config()->string('filesystems.invoice_disk', 'local');

        if (! array_key_exists($name, config()->array('filesystems.disks', []))) {
            Log::warning('Configured invoice_disk is invalid, falling back to local', ['disk' => $name]);
            $name = 'local';
        }

        return Storage::disk($name);
    }

    private function buildInvoiceNumber(InvoiceablePayment $payment): string
    {
        $gymId = $payment->getGym()->id ?? '0';
        $date = ($payment->getCreatedAt() ?? now())->format('Ymd');
        $key = str_pad((string) $payment->getInvoiceKey(), 10, '0', STR_PAD_LEFT);

        $typePrefix = $payment instanceof MemberPayment ? 'M' : 'S';

        return "INV-{$date}-{$typePrefix}{$gymId}{$key}";
    }

    /**
     * Assert that the authenticated user has access to the invoice.
     */
    private function assertAccessible(InvoiceablePayment $payment): void
    {
        $message = 'You do not have access to this invoice.';

        $authUser = Auth::user();

        abort_unless($authUser !== null, 403, $message);

        if ($payment instanceof Payment) {
            $isOwner = $authUser->id === $payment->user_id;
            $isGymStaff = $authUser->gyms()->where('gyms.id', $payment->gym_id)->exists()
                && $authUser->hasAnyRole([
                    DefaultRole::SUPER_ADMIN->value,
                    DefaultRole::GYM_OWNER->value,
                    DefaultRole::MANAGER->value,
                    DefaultRole::RECEPTIONIST->value,
                ]);

            abort_unless($isOwner || $isGymStaff, 403, $message);

            return;
        }

        $memberPayment = MemberPayment::query()->findOrFail($payment->getInvoiceKey());

        $memberPayment->loadMissing(['member']);

        abort_unless($authUser->gyms()->where('gyms.id', $memberPayment->gym_id)->exists(), 403, $message);

        abort_unless($authUser->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]), 403, $message);
    }
}
