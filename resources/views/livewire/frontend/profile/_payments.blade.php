<!-- Payment & Invoicing History Card -->
<div id="invoices-history" class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-8 shadow-xl backdrop-blur-xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-cyan-400 text-2xl">receipt_long</span>
            <div>
                <h3 class="font-headline font-bold text-xl text-white uppercase tracking-tight">Payment Invoices & History</h3>
                <p class="text-xs text-gray-400">Download official tax invoices and review transaction receipts</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-gray-400 font-mono text-[11px] uppercase tracking-wider border-b border-white/10">
                    <th class="pb-3 px-3">Date</th>
                    <th class="pb-3 px-3">Plan / Description</th>
                    <th class="pb-3 px-3">Type</th>
                    <th class="pb-3 px-3">Amount</th>
                    <th class="pb-3 px-3">Status</th>
                    <th class="pb-3 px-3 text-right">Invoice</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-sm">
                @forelse ($this->latestPayments as $payment)
                    <tr wire:key="payment-{{ $payment->id }}" class="hover:bg-white/[0.02] transition-colors group">
                        <td class="py-4 px-3 text-gray-300 font-mono text-xs">
                            {{ $payment->created_at->format('M d, Y') }}
                        </td>
                        <td class="py-4 px-3 text-white font-headline font-bold text-sm">
                            {{ $payment->metadata['plan_name'] ?? 'Gym Subscription' }}
                        </td>
                        <td class="py-4 px-3 text-gray-400 text-xs">
                            @if($payment->payment_type === 'whatsapp_package')
                                <span class="inline-flex items-center gap-1 text-emerald-400">
                                    <span class="material-symbols-outlined text-xs">chat</span>
                                    WhatsApp Package
                                </span>
                            @else
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'Subscription')) }}
                            @endif
                        </td>
                        <td class="py-4 px-3 text-white font-headline font-bold text-sm">
                            {{ $payment->formatted_amount }}
                        </td>
                        <td class="py-4 px-3">
                            @if ($payment->status->value === 'completed')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Paid
                                </span>
                            @elseif($payment->status->value === 'pending')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30 uppercase tracking-wider">
                                    Pending
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30 uppercase tracking-wider">
                                    Failed
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-3 text-right">
                            @if($payment->isCompleted())
                                <button wire:click="downloadInvoice({{ $payment->id }})" 
                                    wire:loading.attr="disabled"
                                    title="Download GST Invoice"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-cyan-400 hover:text-cyan-300 bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 px-3 py-1.5 rounded-lg transition-all cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="downloadInvoice({{ $payment->id }})" class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">download</span>
                                        <span>PDF</span>
                                    </span>
                                    <span wire:loading.flex wire:target="downloadInvoice({{ $payment->id }})" class="items-center gap-1">
                                        <svg class="w-3.5 h-3.5 animate-spin text-cyan-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </span>
                                </button>
                            @else
                                <span class="text-gray-600 font-mono">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-500 space-y-2">
                            <span class="material-symbols-outlined text-3xl text-gray-600">receipt</span>
                            <p class="text-xs font-medium">No payment history or invoices recorded yet.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
