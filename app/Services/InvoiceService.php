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
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceService
{
    /**
     * Generate an invoice for a system payment.
     */
    public function generateInvoice(Payment $payment, bool $force = false): void
    {
        $this->generate($payment, 'invoices.default', $force);
    }

    /**
     * Generate an invoice for a member payment.
     */
    public function generateMemberInvoice(MemberPayment $payment, bool $force = false): void
    {
        $this->generate($payment, 'invoices.member', $force);
    }

    private function generate(InvoiceablePayment $payment, string $view, bool $force = false): void
    {
        if (! $force && $this->invoiceExists($payment)) {
            return;
        }

        $this->ensureInvoiceNumber($payment);
        $this->eagerLoadRelations($payment);

        $gym = $payment->getGym();
        $owner = $payment->getInvoiceOwner();

        $data = [
            'payment' => $payment,
            'owner' => $owner,
            'user' => $owner,
            'gym' => $gym,
            'gym_owner_email' => $gym?->owner_email,
            'invoice_number' => $payment->getInvoiceNumber(),
            'date' => $payment->getCreatedAt()?->format('M d, Y') ?? now()->format('M d, Y'),
        ];

        $this->createPdf($view, $data, $payment);
    }

    private function eagerLoadRelations(InvoiceablePayment $payment): void
    {
        if ($payment instanceof Payment) {
            $payment->loadMissing(['user', 'gym.address.city', 'gym.address.state']);
        } elseif ($payment instanceof MemberPayment) {
            $payment->loadMissing(['member.address.city', 'member.address.state', 'gym.address.city', 'gym.address.state', 'membershipPlan']);
        }
    }

    private function invoiceExists(InvoiceablePayment $payment): bool
    {
        $path = $payment->getInvoicePath();

        return $path !== null && $this->disk()->exists($path);
    }

    public function ensureInvoiceNumber(InvoiceablePayment $payment): string
    {
        $existing = $payment->getInvoiceNumber();
        if ($existing !== null) {
            return $existing;
        }

        $invoiceNumber = $this->buildInvoiceNumber($payment);
        $payment->setInvoiceNumber($invoiceNumber);
        $payment->saveInvoiceMetaQuietly();

        return $invoiceNumber;
    }

    private function createPdf(string $view, array $data, InvoiceablePayment $payment): void
    {
        try {
            $pdf = Pdf::loadView($view, $data);

            $path = "invoices/{$payment->getInvoiceNumber()}.pdf";
            $written = $this->disk()->put($path, $pdf->output());

            throw_unless($written, RuntimeException::class, "Failed to write invoice PDF to disk: {$path}");

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
            $this->generate($payment, 'invoices.member');
        } else {
            $this->generate($payment, 'invoices.default');
        }
    }

    private function disk(): Filesystem
    {
        $name = config()->string('filesystems.invoice_disk', 'invoices');

        throw_unless(array_key_exists($name, config()->array('filesystems.disks', [])), InvalidArgumentException::class, "Configured invoice disk [{$name}] is not defined in filesystems.disks.");

        return Storage::disk($name);
    }

    private function buildInvoiceNumber(InvoiceablePayment $payment): string
    {
        $gymId = $payment->getGym()->id ?? '0';
        $date = ($payment->getCreatedAt() ?? now())->format('Ymd');
        $key = str_pad((string) $payment->getInvoiceKey(), 4, '0', STR_PAD_LEFT);

        $typePrefix = $payment instanceof MemberPayment ? 'M' : 'S';

        $number = "INV-{$date}-{$typePrefix}{$gymId}-{$key}";

        throw_unless(preg_match('/^INV-\d{8}-[SM]\d+-\d+$/', $number), InvalidArgumentException::class, "Generated invalid invoice number format: {$number}");

        return $number;
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

        $isMemberOwner = $memberPayment->member?->user_id === $authUser->id;
        $isGymStaff = $authUser->gyms()->where('gyms.id', $memberPayment->gym_id)->exists()
            && $authUser->hasAnyRole([
                DefaultRole::SUPER_ADMIN->value,
                DefaultRole::GYM_OWNER->value,
                DefaultRole::MANAGER->value,
                DefaultRole::RECEPTIONIST->value,
            ]);

        abort_unless($isMemberOwner || $isGymStaff, 403, $message);
    }
}
