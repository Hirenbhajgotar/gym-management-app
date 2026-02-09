<div class="flex flex-wrap gap-2">
    @foreach($getPresets() as $key => $preset)
        <button
            type="button"
            wire:click="$set('filters.{{ $getStartDateField() }}', '{{ ($preset['start'])() }}'); $set('filters.{{ $getEndDateField() }}', '{{ ($preset['end'])() }}')"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-all hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-400"
        >
            @if(isset($preset['icon']))
                <x-dynamic-component :component="$preset['icon']" class="h-3.5 w-3.5" />
            @endif
            {{ $preset['label'] }}
        </button>
    @endforeach
</div>
