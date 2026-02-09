<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-phosphor-users-three class="h-5 w-5 text-primary-500" />
                Member Retention Cohort Analysis
            </div>
        </x-slot>

        <x-slot name="description">
            Track how members from each signup month are retained over time
        </x-slot>

        @php 
            $cohorts = $this->getCohortData(); 
            $maxMonths = $this->getMaxRetentionMonths();
        @endphp

        @if(count($cohorts) === 0)
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-phosphor-chart-bar class="mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Not enough data for cohort analysis</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Cohort data will appear after members are tracked over time</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Cohort</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Members</th>
                            @for($m = 0; $m < $maxMonths; $m++)
                                <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">
                                    Month {{ $m }}
                                </th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($cohorts as $cohort)
                            <tr class="">
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900 dark:text-white">
                                    {{ $cohort['cohort'] }}
                                </td>
                                <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">
                                    {{ $cohort['initial'] }}
                                </td>
                                @for($m = 0; $m < $maxMonths; $m++)
                                    <td class="px-3 py-2 text-center">
                                        @if(isset($cohort['retained'][$m]))
                                            @php
                                                $retention = $cohort['retained'][$m];
                                                $bgOpacity = min(100, max(10, $retention));
                                                $textColor = $retention >= 70 ? 'text-success-700 dark:text-success-400' : 
                                                            ($retention >= 40 ? 'text-warning-700 dark:text-warning-400' : 'text-danger-700 dark:text-danger-400');
                                            @endphp
                                            <span class="inline-flex items-center justify-center rounded px-2 py-1 text-xs font-medium {{ $textColor }}"
                                                  style="background-color: rgba({{ $retention >= 70 ? '34, 197, 94' : ($retention >= 40 ? '234, 179, 8' : '239, 68, 68') }}, {{ $bgOpacity / 100 * 0.2 }})">
                                                {{ $retention }}%
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Legend --}}
            <div class="mt-4 flex items-center justify-center gap-6 text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded bg-success-500/30"></span>
                    <span>≥70% (Excellent)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded bg-warning-500/30"></span>
                    <span>40–69% (Average)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded bg-danger-500/30"></span>
                    <span><40% (Needs Attention)</span>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
