<div class="mt-4 space-y-2" wire:key="available-numbers-{{ count($numbers) }}">
    @forelse($numbers as $index => $number)
        @if($loop->first)
            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Available Numbers</h4>
        @endif
        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg" wire:key="number-{{ $index }}">
            <div>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $number['phoneNumber'] ?? $number['phone_number'] ?? 'N/A' }}</span>
                @if(isset($number['locality']) && $number['locality'])
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $number['locality'] }}</span>
                @endif
                @if(isset($number['isoCountry']) && $number['isoCountry'])
                    <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">({{ $number['isoCountry'] }})</span>
                @endif
            </div>
            <button 
                type="button"
                wire:click="selectNumber('{{ e($number['phoneNumber'] ?? $number['phone_number']) }}')"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
            >
                <x-loading-icon target="selectNumber('{{ e($number['phoneNumber'] ?? $number['phone_number']) }}')" variant="icon" name="Loading" />
                <span wire:loading.remove wire:target="selectNumber('{{ e($number['phoneNumber'] ?? $number['phone_number']) }}')">
                    Select
                </span>
            </button>
        </div>
    @empty
        <div class="flex items-start gap-3 p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
            <div class="flex-shrink-0 p-1.5 bg-danger-100 dark:bg-danger-900/40 rounded-full">
                <x-heroicon-o-exclamation-circle class="w-5 h-5 text-danger-600 dark:text-danger-400" />
            </div>
            <div>
                <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">No available numbers</h3>
                <p class="text-xs text-danger-700 dark:text-danger-300 mt-0.5">
                    We couldn't find any available numbers matching your search criteria. Please try a different prefix or region.
                </p>
            </div>
        </div>
    @endforelse
</div>
