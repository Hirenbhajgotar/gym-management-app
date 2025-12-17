@php
    $payments = \App\Models\Payment::where('gym_id', $this->getGym()->id)
        ->where('payment_type', 'whatsapp_package')
        ->latest()
        ->take(10)
        ->get();
@endphp

<div class="overflow-x-auto border border-gray-200 dark:border-white/10 rounded-lg">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr class="divide-x divide-gray-200 dark:divide-white/10">
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Date</th>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Package</th>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Amount</th>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Status</th>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white text-right">Invoice</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @forelse ($payments as $payment)
                <tr class="divide-x divide-gray-200 dark:divide-white/10">
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $payment->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                        {{ $payment->metadata['package_name'] ?? 'WhatsApp Package' }}
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                        {{ $payment->formatted_amount }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                            {{ $payment->status->value === 'completed' ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/30' : 
                               ($payment->status->value === 'pending' ? 'bg-yellow-50 text-yellow-800 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-500 dark:ring-yellow-400/30' : 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30') }}">
                            {{ ucfirst($payment->status->value) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($payment->status->value === 'completed')
                            <button wire:click.prevent="downloadInvoice({{ $payment->id }})" 
                                    class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 font-medium hover:underline">
                                Download
                            </button>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                        No purchase history found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
