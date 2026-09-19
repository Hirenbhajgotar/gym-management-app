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

        $payment->loadMissing(['member']);

        $isMemberOwner = $payment->member?->user_id === $user->id;
        $isStaff = $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]) && $user->gyms()->where('gyms.id', $payment->gym_id)->exists();

        abort_unless($isMemberOwner || $isStaff, 403, 'You do not have access to this invoice.');

        return $invoiceService->viewInvoice($payment);
    }
}
