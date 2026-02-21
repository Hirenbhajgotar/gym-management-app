<div class="mt-4 space-y-2" wire:key="available-numbers-{{ count($numbers) }}">
    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Available Numbers</h4>
    @foreach($numbers as $index => $number)
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
                wire:click="selectNumber('{{ $number['phoneNumber'] ?? $number['phone_number'] }}')"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
            >
                Select
            </button>
        </div>
    @endforeach
</div>
