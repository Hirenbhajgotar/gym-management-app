<?php

namespace App\Http\Controllers;

use App\Enums\DefaultRole;
use App\Models\MemberPayment;
use App\Services\InvoiceService;

class MemberInvoiceController extends Controller
{
    public function show(MemberPayment $payment, InvoiceService $invoiceService)
    {
        abort_unless(auth()->user()->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]), 403);

        return $invoiceService->viewInvoice($payment);
    }
}
