<x-filament-panels::page>
    <div class="grid gap-6">
        {{-- AI Insights --}}
        @php $insights = $this->getInsights(); @endphp
        @if($insights->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach($insights as $insight)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-4">
                            <div @class([
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                'bg-danger-50 text-danger-600 dark:bg-danger-900/20 dark:text-danger-400' => $insight['color'] === 'danger',
                                'bg-warning-50 text-warning-600 dark:bg-warning-900/20 dark:text-warning-400' => $insight['color'] === 'warning',
                                'bg-success-50 text-success-600 dark:bg-success-900/20 dark:text-success-400' => $insight['color'] === 'success',
                                'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400' => $insight['color'] === 'primary',
                            ])>
                                @svg($insight['icon'], 'h-6 w-6')
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $insight['title'] }}</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $insight['message'] }}</p>
                                @if(isset($insight['action_url']))
                                    <div class="mt-3">
                                        <a href="{{ $insight['action_url'] }}" 
                                           class="text-xs font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400">
                                            {{ $insight['action_label'] }} &rarr;
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Quick Stats Cards --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            {{-- Members Card --}}
            @php $memberStats = $this->getMemberStats(); @endphp
            <x-filament::section class="relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Members</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($memberStats['active']) }}</p>
                        <div class="mt-1 flex items-center gap-1 text-sm">
                            @if($memberStats['growth_rate'] > 0)
                                <x-heroicon-m-arrow-trending-up class="h-4 w-4 text-success-500" />
                            @elseif($memberStats['growth_rate'] < 0)
                                <x-heroicon-m-arrow-trending-down class="h-4 w-4 text-danger-500" />
                            @endif
                            <span class="{{ $memberStats['growth_color'] === 'success' ? 'text-success-600' : ($memberStats['growth_color'] === 'danger' ? 'text-danger-600' : 'text-gray-500') }}">
                                {{ abs($memberStats['growth_rate']) }}%
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">vs last month</span>
                        </div>
                    </div>
                    <div class="rounded-full bg-primary-100 p-3 dark:bg-primary-900/20">
                        <x-phosphor-users class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $memberStats['new_this_month'] }} new this month · {{ $memberStats['expired'] }} expired
                </div>
                
                {{-- Threshold Alert --}}
                @if(isset($memberStats['alert']))
                    <div class="mt-2">
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium',
                            'bg-danger-50 text-danger-700 dark:bg-danger-900/20 dark:text-danger-400' => $memberStats['alert']['type'] === 'danger',
                            'bg-warning-50 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400' => $memberStats['alert']['type'] === 'warning',
                        ])>
                            <x-heroicon-m-exclamation-triangle class="h-3 w-3" />
                            {{ $memberStats['alert']['message'] }}
                        </span>
                    </div>
                @endif
                
                {{-- Sparkline --}}
                @if(isset($memberStats['sparkline']))
                    <div class="mt-3">
                        <x-sparkline :data="$memberStats['sparkline']" color="primary" />
                    </div>
                @endif
            </x-filament::section>

            {{-- Revenue Card --}}
            @php $revenueStats = $this->getRevenueStats(); @endphp
            <x-filament::section class="relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">MTD Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">₹{{ number_format($revenueStats['mtd'], 0) }}</p>
                        <div class="mt-1 flex items-center gap-1 text-sm">
                            @if($revenueStats['change_rate'] > 0)
                                <x-heroicon-m-arrow-trending-up class="h-4 w-4 text-success-500" />
                            @elseif($revenueStats['change_rate'] < 0)
                                <x-heroicon-m-arrow-trending-down class="h-4 w-4 text-danger-500" />
                            @endif
                            <span class="{{ $revenueStats['change_color'] === 'success' ? 'text-success-600' : ($revenueStats['change_color'] === 'danger' ? 'text-danger-600' : 'text-gray-500') }}">
                                {{ abs($revenueStats['change_rate']) }}%
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">vs last month</span>
                        </div>
                    </div>
                    <div class="rounded-full bg-success-100 p-3 dark:bg-success-900/20">
                        <x-phosphor-currency-inr class="h-6 w-6 text-success-600 dark:text-success-400" />
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Today: ₹{{ number_format($revenueStats['today'], 0) }} · YTD: ₹{{ number_format($revenueStats['ytd'], 0) }}
                </div>
                
                {{-- Sparkline --}}
                @if(isset($revenueStats['sparkline']))
                    <div class="mt-3">
                        <x-sparkline :data="$revenueStats['sparkline']" color="success" />
                    </div>
                @endif
            </x-filament::section>

            {{-- Leads Card --}}
            @php $leadStats = $this->getLeadStats(); @endphp
            <x-filament::section class="relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Leads</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($leadStats['active']) }}</p>
                        <div class="mt-1 flex items-center gap-1 text-sm">
                            <x-phosphor-target class="h-4 w-4 text-warning-500" />
                            <span class="text-warning-600">{{ $leadStats['conversion_rate'] }}%</span>
                            <span class="text-gray-500 dark:text-gray-400">conversion</span>
                        </div>
                    </div>
                    <div class="rounded-full bg-warning-100 p-3 dark:bg-warning-900/20">
                        <x-phosphor-funnel class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $leadStats['new'] }} new · {{ $leadStats['converted_this_month'] }} converted this month
                </div>
                
                {{-- Sparkline --}}
                @if(isset($leadStats['sparkline']))
                    <div class="mt-3">
                        <x-sparkline :data="$leadStats['sparkline']" color="warning" />
                    </div>
                @endif
            </x-filament::section>

            {{-- Trainers Card --}}
            @php $trainerStats = $this->getTrainerStats(); @endphp
            <x-filament::section class="relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Trainers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $trainerStats['total'] }}</p>
                        <div class="mt-1 flex items-center gap-1 text-sm">
                            <x-phosphor-chart-bar class="h-4 w-4 text-info-500" />
                            <span class="text-info-600">{{ $trainerStats['utilization_rate'] }}%</span>
                            <span class="text-gray-500 dark:text-gray-400">utilized</span>
                        </div>
                    </div>
                    <div class="rounded-full bg-info-100 p-3 dark:bg-info-900/20">
                        <x-phosphor-barbell class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Avg {{ $trainerStats['average_clients'] }} clients per trainer
                </div>
            </x-filament::section>

            {{-- Attendance Card --}}
            @php $attendanceStats = $this->getAttendanceStats(); @endphp
            <x-filament::section class="relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Check-ins</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $attendanceStats['today'] }}</p>
                        <div class="mt-1 flex items-center gap-1 text-sm">
                            <x-phosphor-users class="h-4 w-4 text-primary-500" />
                            <span class="text-primary-600">{{ $attendanceStats['currently_in'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">currently in</span>
                        </div>
                    </div>
                    <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900/20">
                        <x-phosphor-door-open class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Avg {{ $attendanceStats['weekly_average'] }}/day · Peak: {{ $attendanceStats['peak_hour'] }}
                </div>
                
                {{-- Sparkline --}}
                @if(isset($attendanceStats['sparkline']))
                    <div class="mt-3">
                        <x-sparkline :data="$attendanceStats['sparkline']" color="purple" />
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Quick Links to Reports --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-phosphor-chart-bar class="h-5 w-5 text-gray-500" />
                    Detailed Reports
                </div>
            </x-slot>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <a href="{{ \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::getUrl() }}"
                   class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500">
                    <x-phosphor-users-three class="mb-2 h-8 w-8 text-primary-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Member Reports</span>
                </a>

                <a href="{{ \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::getUrl() }}"
                   class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white p-4 transition hover:border-success-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-success-500">
                    <x-phosphor-currency-inr class="mb-2 h-8 w-8 text-success-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Revenue Reports</span>
                </a>

                <a href="{{ \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::getUrl() }}"
                   class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white p-4 transition hover:border-warning-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-warning-500">
                    <x-phosphor-funnel class="mb-2 h-8 w-8 text-warning-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Lead Reports</span>
                </a>

                <a href="{{ \App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::getUrl() }}"
                   class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white p-4 transition hover:border-info-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-info-500">
                    <x-phosphor-barbell class="mb-2 h-8 w-8 text-info-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Trainer Reports</span>
                </a>

                <a href="{{ \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::getUrl() }}"
                   class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white p-4 transition hover:border-purple-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-purple-500">
                    <x-phosphor-door-open class="mb-2 h-8 w-8 text-purple-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Attendance Reports</span>
                </a>
            </div>
        </x-filament::section>

        {{-- Last Updated Info --}}
        <div class="text-center text-xs text-gray-400 dark:text-gray-500">
            Data is cached for optimal performance. Stats refresh automatically every 15 minutes.
            <button wire:click="$refresh" wire:loading.attr="disabled" class="ml-2 text-primary-500 hover:underline disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Refresh Now</span>
                <span wire:loading.flex class="inline-flex items-center gap-1">
                    <x-filament::loading-indicator class="h-3 w-3" />
                    Refreshing...
                </span>
            </button>
        </div>
    </div>
</x-filament-panels::page>

@script
<script>
// Initialize Laravel Echo for real-time updates
if (window.Echo) {
    const gymId = '{{ \Filament\Facades\Filament::getTenant()->id }}';
    
    Echo.private(`reports.${gymId}`)
        .listen('.MemberStatsUpdated', (e) => {
            console.log('✅ Member stats updated:', e);
            $wire.$refresh();
        })
        .listen('.RevenueStatsUpdated', (e) => {
            console.log('✅ Revenue stats updated:', e);
            $wire.$refresh();
        })
        .listen('.LeadStatsUpdated', (e) => {
            console.log('✅ Lead stats updated:', e);
            $wire.$refresh();
        })
        .listen('.AttendanceStatsUpdated', (e) => {
            console.log('✅ Attendance stats updated:', e);
            $wire.$refresh();
        });
        
    console.log(`🔌 Echo subscribed to: reports.${gymId}`);
} else {
    console.warn('⚠️ Laravel Echo not initialized');
}
</script>
@endscript
