<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DefaultRole;
use App\Models\MemberPayment;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;

class MemberInvoiceController extends Controller
{
    public function show(MemberPayment $payment, InvoiceService $invoiceService)
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        abort_unless($user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]), 403);

        return $invoiceService->viewInvoice($payment);
    }
}
