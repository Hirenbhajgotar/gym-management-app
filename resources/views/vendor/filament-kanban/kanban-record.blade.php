<div
    id="{{ $record->getKey() }}"
    wire:click="recordClicked('{{ $record->getKey() }}', {{ @json_encode($record) }})"
    class="record bg-white dark:bg-gray-700 rounded-lg p-4 font-medium text-gray-600 dark:text-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 ease-in-out cursor-grab"
    @if($record->timestamps && now()->diffInSeconds($record->{$record::UPDATED_AT}, true) < 3)
        x-data
        x-init="
            $el.classList.add('animate-pulse-twice');
        "
    @endif
>
    <div class="flex flex-col gap-y-3">
        {{-- Deal Title --}}
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                {{ $record->{static::$recordTitleAttribute} }}
            </h3>
        </div>

        {{-- Deal Description --}}
        @if($record->notes)
            <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                {{ $record->notes }}
            </p>
        @endif

        <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
            {{-- Expected Close Date --}}
            @if($record->expected_close_date)
                <div class="flex items-center gap-x-1 text-xs text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 " />
                    <span>{{ $record->expected_close_date->format('M d, Y') }}</span>
                </div>
            @endif
        </div>
    </div>
</div>
