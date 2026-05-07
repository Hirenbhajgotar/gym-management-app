@props(['numbers' => []])

@if(!empty($numbers))
    <div class="space-y-2 mt-4">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Select a number to purchase:</p>
        
        <div class="grid grid-cols-1 gap-2 max-h-64 overflow-y-auto p-1 space-y-2">
            @foreach($numbers as $number)
                @php
                    // Safely handle both object and array formats returned by the Twilio API
                    $phoneNumber = is_object($number) ? ($number->phoneNumber ?? '') : ($number['phoneNumber'] ?? $number);
                    $friendlyName = is_object($number) ? ($number->friendlyName ?? '') : ($number['friendlyName'] ?? '');
                    $region = is_object($number) ? ($number->region ?? '') : ($number['region'] ?? '');
                @endphp
                
                <button
                    type="button"
                    wire:click="selectNumber('{{ addslashes($phoneNumber) }}')"
                    wire:loading.attr="disabled"
                    class="w-full text-left p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-gray-800 transition-all duration-200 flex items-center justify-between group"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="font-mono font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $phoneNumber }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                @if($region)
                                    <span class="text-xs bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded uppercase">{{ $region }}</span>
                                @endif
                                @if($friendlyName && $friendlyName !== $phoneNumber)
                                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">{{ $friendlyName }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400 opacity-0-- group-hover:opacity-100 transition-opacity flex items-center gap-1 flex-shrink-0">
                        <span wire:loading.remove wire:target="selectNumber('{{ addslashes($phoneNumber) }}')">Select</span>
                        <span wire:loading wire:target="selectNumber('{{ addslashes($phoneNumber) }}')" class="inline-flex items-center gap-1">
                            Processing...
                        </span>
                        <svg class="w-4 h-4" wire:loading.remove wire:target="selectNumber('{{ addslashes($phoneNumber) }}')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                </button>
            @endforeach
        </div>
    </div>
@else
    <div class="space-y-2 mt-4 flex items-center gap-2">
        <x-filament::icon
            icon="heroicon-o-exclamation-triangle"
            class="h-5 w-5 "
        />
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 !mt-0">No numbers found</p>
    </div>
@endif