<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    @if(isset($data['stats']['total_members']))
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Members</dt>
                        <dd class="text-lg font-semibold text-gray-900">{{ number_format($data['stats']['total_members']) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if(isset($data['stats']['active_members']))
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Members</dt>
                        <dd class="text-lg font-semibold text-gray-900">{{ number_format($data['stats']['active_members']) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if(isset($data['stats']['new_members']))
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">New Members</dt>
                        <dd class="text-lg font-semibold text-gray-900">{{ number_format($data['stats']['new_members']) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if(isset($data['stats']['churn_rate']))
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Churn Rate</dt>
                        <dd class="text-lg font-semibold text-gray-900">{{ number_format($data['stats']['churn_rate'], 1) }}%</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Growth Data -->
@if(!empty($data['growth']))
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Member Growth</h3>
        </div>
        <div class="p-6">
            <canvas id="growthChart" width="400" height="200"></canvas>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('growthChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($data['growth']['labels'] ?? []),
                    datasets: [{
                        label: 'Members',
                        data: @json($data['growth']['data'] ?? []),
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
@endif

<!-- Plan Distribution -->
@if(!empty($data['planDistribution']))
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Plan Distribution</h3>
        </div>
        <div class="p-6">
            <canvas id="planChart" width="400" height="200"></canvas>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('planChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: @json(array_keys($data['planDistribution'] ?? [])),
                    datasets: [{
                        data: @json(array_values($data['planDistribution'] ?? [])),
                        backgroundColor: [
                            'rgb(99, 102, 241)',
                            'rgb(34, 197, 94)',
                            'rgb(251, 146, 60)',
                            'rgb(239, 68, 68)',
                            'rgb(168, 85, 247)',
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });
    </script>
@endif

<!-- Churn Statistics -->
@if(!empty($data['churnStats']))
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Churn Analysis</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @if(isset($data['churnStats']['high_risk']))
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">{{ $data['churnStats']['high_risk'] }}</div>
                        <div class="text-sm text-gray-500">High Risk</div>
                    </div>
                @endif
                @if(isset($data['churnStats']['medium_risk']))
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ $data['churnStats']['medium_risk'] }}</div>
                        <div class="text-sm text-gray-500">Medium Risk</div>
                    </div>
                @endif
                @if(isset($data['churnStats']['low_risk']))
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $data['churnStats']['low_risk'] }}</div>
                        <div class="text-sm text-gray-500">Low Risk</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif