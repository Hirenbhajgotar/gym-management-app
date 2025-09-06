@php
    $recordKey = $record->getKey();
@endphp
<div id="{{ $recordKey }}" data-record-id="{{ $recordKey }}"
    class="record bg-white dark:bg-gray-700 rounded-lg p-4 font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 ease-in-out cursor-grab"
    @if ($record->timestamps && now()->diffInSeconds($record->{$record::UPDATED_AT}, true) < 3) x-data
        x-init="
            $el.classList.add('animate-pulse-twice');
        " @endif>
    <div class="flex flex-col gap-y-3">
        {{-- Lead Name --}}
        <div class="flex items-center justify-between ">
            <div class="gap-x-2 flex items-center">
                <x-filament::avatar
                    src="https://ui-avatars.com/api/?name={{ $record->{static::$recordTitleAttribute} }}&length={{ str()->wordCount($record->{static::$recordTitleAttribute}) }}&background=random&rounded=true"
                    alt="{{ $record->{static::$recordTitleAttribute} }}" />
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ $record->{static::$recordTitleAttribute} }}
                </h3>
            </div>

            @php
                $days = now()->diffInDays($record->created_at);
            @endphp

            @if ($days >= 5)
                <x-filament::icon icon="heroicon-o-exclamation-triangle"
                    class="text-danger-500 dark:text-danger-400 h-5 w-5"
                    x-tooltip.raw="Lead is rotten for {{ $days }} days" />
            @endif
        </div>

        {{-- Phone and Email --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            @if ($record->phone)
                <div class="flex items-center gap-x-2">
                    <x-filament::icon icon="phosphor-phone" class="h-4 w-4" />
                    <span>{{ $record->phone }}</span>
                </div>
            @endif
            @if ($record->email)
                <div class="flex items-center gap-x-2">
                    <x-filament::icon icon="phosphor-envelope" class="h-4 w-4" />
                    <span>{{ $record->email }}</span>
                </div>
            @endif
        </div>

        <div class="my-3.5">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ \Illuminate\Support\Str::limit($record->notes, 100) }}
            </p>
        </div>

        <div class="flex flex-wrap gap-1">
            <x-filament::badge icon="heroicon-m-user" color="gray" size="sm">
                {{ $record?->creator?->name ?? 'N/A' }}
            </x-filament::badge>
            <x-filament::badge icon="heroicon-m-star" color="success" size="sm">
                Gold Membership
            </x-filament::badge>
        </div>

        {{-- Tags Source --}}
        @if ($record->tags)
            <div class="flex flex-wrap gap-1">
                {{-- Source Badge --}}
                <x-filament::badge color="gray" size="sm">
                    {{ $record->source->getLabel() }}
                </x-filament::badge>

                {{-- Tags Badges --}}
                @foreach ($record->tags as $tag)
                    @php
                        $leadTag = \App\Enums\LeadTag::tryFrom($tag);
                    @endphp
                    @if ($leadTag)
                        <x-filament::badge color="{{ $leadTag->getColor() }}" size="sm">
                            {{ $leadTag->getLabel() }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning" size="sm">
                            {{ $tag }}
                        </x-filament::badge>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($record->interest)
            {{-- Interest Badges --}}
            <div class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                Interest:
            </div>
            <div class="flex flex-wrap gap-1">
                @foreach (array_slice($record->interest, 0, 3) as $interest)
                    <x-filament::badge color="warning" size="sm">
                        {{ $interest }}
                    </x-filament::badge>
                @endforeach
                @if (count($record->interest) > 3)
                    <x-filament::badge color="gray" size="sm">
                        ...
                    </x-filament::badge>
                @endif
            </div>
        @endif

        {{-- Dates and Next Action --}}
        @if ($record->last_follow_up_date)
            <div
                class="space-y-2 text-xs text-gray-600 dark:text-gray-300 pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-x-2">
                    <x-filament::icon icon="phosphor-calendar-check" class="h-4 w-4 text-green-500" />
                    <span>Last Follow-up: {{ $record->last_follow_up_date->format('M d, Y') }}</span>
                </div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-x-2 mt-3 pt-2 border-t border-gray-200 dark:border-gray-600">
            <x-filament::button icon="heroicon-m-plus-circle" color="success" size="xs" tooltip="Add Follow-Up"
                wire:click="mountAction('addFollowUp', { id: {{ $recordKey }} })" outlined>
                Follow-Up
            </x-filament::button>

            <x-filament::button icon="heroicon-m-user-plus" color="gray" size="xs" tooltip="Convert to Member"
                wire:click="mountAction('convertToMember', { id: {{ $recordKey }} })" outlined>
                Convert
            </x-filament::button>

            <x-filament::button icon="heroicon-m-archive-box" color="gray" size="xs"
                wire:click="mountAction('archive', { id: {{ $recordKey }} })" outlined tooltip="Archive Lead">
                Archive
            </x-filament::button>
        </div>
    </div>
</div>
