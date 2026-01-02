<?php

namespace App\Http\Controllers;

use App\Models\MemberPayment;
use App\Services\InvoiceService;
use App\Enums\DefaultRole;

class MemberInvoiceController extends Controller
{
    public function show(MemberPayment $payment, InvoiceService $invoiceService)
    {
        abort_unless(auth()->user()->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value
        ]), 403);

        return $invoiceService->viewInvoice($payment);
    }
}
