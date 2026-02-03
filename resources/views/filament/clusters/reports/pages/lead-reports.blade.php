<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Form --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Summary Stats --}}
        @php $stats = $this->getSummaryStats(); @endphp
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total Leads</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ number_format($stats['new']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">New Leads</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ number_format($stats['active_pipeline']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Active Pipeline</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ number_format($stats['converted']) }}</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Converted</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ $stats['conversion_rate'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Win Rate</div>
                <div class="mt-1 text-xs text-gray-500">(Converted / Closed)</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $stats['overall_conversion'] }}%</div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overall Conversion</div>
                <div class="mt-1 text-xs text-gray-500">(Converted / Total)</div>
            </x-filament::section>
        </div>

        {{-- Lead Scoring Analysis --}}
        @php $scoringStats = $this->getLeadScoringStats(); @endphp
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
             <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-fire class="h-5 w-5 text-primary-500" />
                        Pipeline Quality
                    </div>
                </x-slot>
                
                <div class="flex flex-col items-center justify-center py-4">
                     <div class="flex w-full items-end justify-between px-4">
                        <div class="text-center">
                            <div class="text-xs text-gray-500">Hot Leads</div>
                            <div class="text-2xl font-bold text-primary-600">{{ $scoringStats['hot_leads'] }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-gray-500">Warm Leads</div>
                            <div class="text-2xl font-bold text-warning-600">{{ $scoringStats['warm_leads'] }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-gray-500">Total Active</div>
                            <div class="text-2xl font-bold text-gray-600">{{ $scoringStats['total_active'] }}</div>
                        </div>
                    </div>
                    
                    <div class="mt-6 w-full px-4">
                         <div class="mb-1 flex justify-between text-xs">
                            <span class="text-gray-500">High Quality Ratio</span>
                            <span class="font-medium text-gray-900 dark:text-gray-200">
                                {{ $scoringStats['total_active'] > 0 ? round(($scoringStats['hot_leads'] / $scoringStats['total_active']) * 100) : 0 }}%
                            </span>
                        </div>
                        <x-progress-bar  
                            color="primary" 
                        >
                            {{ $scoringStats['total_active'] > 0 ? ($scoringStats['hot_leads'] / $scoringStats['total_active']) * 100 : 0 }}%
                        </x-progress-bar>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-phosphor-star class="h-5 w-5 text-warning-500" />
                        Top Priority Leads
                    </div>
                </x-slot>

                 @if($scoringStats['top_leads']->count() > 0)
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($scoringStats['top_leads'] as $lead)
                            <div class="flex items-center justify-between py-3">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $lead->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $lead->source?->label() ?? 'Unknown' }} • {{ $lead->status?->label() ?? 'New' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex flex-col items-end">
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs font-medium text-primary-600">Score: {{ $lead->score }}</span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ $lead->follow_ups_count ?? $lead->followUps->count() }} interactions
                                        </div>
                                    </div>
                                    
                                    <x-filament::button size="xs" color="gray" href="{{ \App\Filament\Admin\Resources\LeadResource::getUrl('view', ['record' => $lead->id]) }}">
                                        View
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <x-phosphor-magnifying-glass class="mb-3 h-12 w-12 text-gray-300" />
                        <p class="text-sm text-gray-500">No high-scoring leads found yet.</p>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Funnel Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">Lead Funnel</x-slot>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 rounded-lg bg-blue-100 px-4 py-2 dark:bg-blue-900/30">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-400">New</span>
                    <span class="text-lg font-bold text-blue-800 dark:text-blue-300">{{ $stats['new'] }}</span>
                </div>
                <x-heroicon-o-arrow-right class="h-5 w-5 text-gray-400" />

                <div class="flex items-center gap-2 rounded-lg bg-indigo-100 px-4 py-2 dark:bg-indigo-900/30">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-400">Contacted</span>
                    <span class="text-lg font-bold text-indigo-800 dark:text-indigo-300">{{ $stats['contacted'] }}</span>
                </div>
                <x-heroicon-o-arrow-right class="h-5 w-5 text-gray-400" />

                <div class="flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 dark:bg-green-900/30">
                    <span class="text-sm font-medium text-green-700 dark:text-green-400">Converted</span>
                    <span class="text-lg font-bold text-green-800 dark:text-green-300">{{ $stats['converted'] }}</span>
                </div>
                <x-heroicon-o-slash class="h-5 w-5 text-gray-400" />

                <div class="ml-4 flex items-center gap-2 rounded-lg bg-red-100 px-4 py-2 dark:bg-red-900/30">
                    <span class="text-sm font-medium text-red-700 dark:text-red-400">Lost</span>
                    <span class="text-lg font-bold text-red-800 dark:text-red-300">{{ $stats['lost'] }}</span>
                </div>
            </div>
        </x-filament::section>

        {{-- Charts are rendered via footer widgets --}}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
