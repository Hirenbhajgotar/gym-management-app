<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

interface InvoiceablePayment
{
    public function getInvoiceKey(): int;

    public function getGym(): ?Gym;

    /** The invoiced owner (member or user) rendered on the invoice. */
    public function getInvoiceOwner(): ?Model;

    /** Stable foreign-key id used when building the invoice number. */
    public function getOwnerId(): ?int;

    public function getInvoiceNumber(): ?string;

    public function getInvoicePath(): ?string;

    public function getCreatedAt(): ?Carbon;

    public function setInvoiceNumber(string $number): void;

    public function setInvoicePath(string $path): void;

    /** Persist invoice metadata without firing model observers/activity log. */
    public function saveInvoiceMetaQuietly(): void;
}
