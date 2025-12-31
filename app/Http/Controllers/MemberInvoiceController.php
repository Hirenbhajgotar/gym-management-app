<?php

namespace App\Http\Controllers;

use App\Models\MemberPayment;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class MemberInvoiceController extends Controller
{
    public function show(MemberPayment $payment, InvoiceService $invoiceService)
    {
        // Add authorization check if needed, e.g., $this->authorize('view', $payment);
        return $invoiceService->viewInvoice($payment);
    }
}
