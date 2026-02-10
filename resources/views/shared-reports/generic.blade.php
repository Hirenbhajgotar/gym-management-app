<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    @foreach($data['stats'] as $key => $value)
        @if(is_numeric($value))
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">{{ Str::title(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="text-lg font-semibold text-gray-900">
                                @if(strpos($key, 'amount') !== false || strpos($key, 'revenue') !== false || strpos($key, 'cost') !== false)
                                    {{ number_format($value, 2) }}
                                @else
                                    {{ number_format($value) }}
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<!-- Additional Data Sections -->
@if(isset($data['monthly_trends']) && !empty($data['monthly_trends']))
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Monthly Trends</h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($data['monthly_trends'] as $trend)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $trend['month'] ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($trend['count'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<!-- Chart Data -->
@if(isset($data['chart_data']) && !empty($data['chart_data']))
    @foreach($data['chart_data'] as $chartType => $chartData)
        @if(!empty($chartData))
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ Str::title(str_replace('_', ' ', $chartType)) }}</h3>
                </div>
                <div class="p-6">
                    <canvas id="{{ $chartType }}Chart" width="400" height="200"></canvas>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('{{ $chartType }}Chart').getContext('2d');
                    
                    // Determine chart type based on data structure
                    let chartType = 'bar';
                    let chartData = @json($chartData);
                    
                    // If data has labels and values structure, use appropriate chart
                    if (chartData.length > 0 && typeof chartData[0] === 'object') {
                        const keys = Object.keys(chartData[0]);
                        if (keys.includes('label') || keys.includes('name') || keys.includes('risk_level')) {
                            chartType = 'doughnut';
                        }
                    }
                    
                    new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: chartData.map(item => item.label || item.name || item.risk_level || item.month || 'Unknown'),
                            datasets: [{
                                label: '{{ Str::title(str_replace('_', ' ', $chartType)) }}',
                                data: chartData.map(item => item.count || item.value || item.amount || 0),
                                backgroundColor: [
                                    'rgb(99, 102, 241)',
                                    'rgb(34, 197, 94)',
                                    'rgb(251, 146, 60)',
                                    'rgb(239, 68, 68)',
                                    'rgb(168, 85, 247)',
                                    'rgb(59, 130, 246)',
                                    'rgb(16, 185, 129)',
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: chartType === 'bar' ? {
                                y: {
                                    beginAtZero: true
                                }
                            } : {},
                            plugins: {
                                legend: {
                                    display: chartType === 'doughnut',
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                });
            </script>
        @endif
    @endforeach
@endif

<!-- Raw Data Display (for debugging or additional info) -->
@if(count($data) > 1)
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Additional Information</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @foreach($data as $key => $value)
                    @if($key !== 'stats' && $key !== 'chart_data' && $key !== 'monthly_trends')
                        @if(is_array($value))
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">{{ Str::title(str_replace('_', ' ', $key)) }}</h4>
                                <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @elseif(is_scalar($value))
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-gray-600">{{ Str::title(str_replace('_', ' ', $key)) }}</span>
                                <span class="font-medium">{{ $value }}</span>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif