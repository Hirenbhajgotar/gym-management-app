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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            @if(isset($data['monthly_trends'][0]['trend']))
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($data['monthly_trends'] as $trend)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $trend['month'] ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ number_format($trend['count'] ?? $trend['value'] ?? $trend['total'] ?? $trend['amount'] ?? 0) }}
                                </td>
                                @if(isset($trend['trend']))
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $trend['trend'] === 'Up' ? 'bg-green-100 text-green-800' : ($trend['trend'] === 'Down' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ $trend['trend'] }}
                                        </span>
                                        <span class="text-xs text-gray-500 ml-1">
                                            ({{ $trend['change_percent'] > 0 ? '+' : '' }}{{ $trend['change_percent'] }}%)
                                        </span>
                                    </td>
                                @endif
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        @foreach($data['chart_data'] as $chartType => $chartData)
            @if(!empty($chartData))
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ Str::title(str_replace('_', ' ', $chartType)) }}</h3>
                    </div>
                    <div class="p-6">
                        <canvas id="{{ $chartType }}Chart" style="max-height: 300px;"></canvas>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('{{ $chartType }}Chart').getContext('2d');
                        
                        // Determine chart type based on data structure
                        let chartData = @json($chartData);
                        let chartType = 'bar';
                        let isSparkline = false;

                        // Check if data is simple array of numbers (Sparkline/Trend)
                        if (chartData.length > 0 && typeof chartData[0] === 'number') {
                            chartType = 'line';
                            isSparkline = true;
                        } 
                        // Check if data has objects with labels
                        else if (chartData.length > 0 && typeof chartData[0] === 'object') {
                            const keys = Object.keys(chartData[0]);
                            if (keys.includes('label') || keys.includes('name') || keys.includes('risk_level') || keys.includes('category')) {
                                chartType = '{{ str_contains($chartType, 'distribution') || str_contains($chartType, 'source') ? 'doughnut' : 'bar' }}';
                            }
                        }
                        
                        // Smart key detection for labels and values
                        const getLabel = (item, index) => isSparkline ? `Day ${index + 1}` : (item.label || item.name || item.status || item.month || item.category || 'Unknown');
                        const getValue = (item) => isSparkline ? item : (item.value || item.count || item.amount || item.total || item.clients || item.revenue || 0);
                        
                        // Color palette
                        const colors = [
                            'rgba(99, 102, 241, 0.7)',   // Indigo
                            'rgba(34, 197, 94, 0.7)',    // Green
                            'rgba(249, 115, 22, 0.7)',   // Orange
                            'rgba(239, 68, 68, 0.7)',    // Red
                            'rgba(168, 85, 247, 0.7)',   // Purple
                            'rgba(59, 130, 246, 0.7)',   // Blue
                            'rgba(236, 72, 153, 0.7)',   // Pink
                            'rgba(20, 184, 166, 0.7)',   // Teal
                        ];
                        
                        const options = {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: chartType === 'doughnut' || chartType === 'pie',
                                    position: 'bottom'
                                }
                            }
                        };

                        if (isSparkline) {
                            options.scales = {
                                x: { display: true, grid: { display: false } }, // Show axis but hide grid for cleaner look
                                y: { beginAtZero: true }
                            };
                            options.elements = {
                                point: { radius: 0, hitRadius: 10 }, // Hide points for sparkline look
                                line: { tension: 0.4 } // Smooth curves
                            };
                        } else if (chartType === 'bar' || chartType === 'line') {
                            options.scales = {
                                y: { beginAtZero: true }
                            };
                        }

                        new Chart(ctx, {
                            type: chartType,
                            data: {
                                labels: chartData.map(getLabel),
                                datasets: [{
                                    label: '{{ Str::title(str_replace('_', ' ', $chartType)) }}',
                                    data: chartData.map(getValue),
                                    backgroundColor: isSparkline ? 'rgba(99, 102, 241, 0.1)' : colors.slice(0, chartData.length),
                                    borderColor: isSparkline ? 'rgba(99, 102, 241, 1)' : colors.map(c => c.replace('0.7', '1')).slice(0, chartData.length),
                                    borderWidth: isSparkline ? 2 : 1,
                                    fill: isSparkline
                                }]
                            },
                            options: options
                        });
                    });
                </script>
            @endif
        @endforeach
    </div>
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
                    @if(!in_array($key, [
                        'stats', 'chart_data', 'monthly_trends', 
                        'memberStats', 'revenueStats', 'leadStats', 'attendanceStats', 'insights',
                        'pnl', 'revenue_breakdown', 'expense_breakdown', 
                        'growth', 'planDistribution', 'churnStats',
                        'performance_metrics', 'usageStats', 'ltvStats',
                        'report', 'link', 'title', 'generatedAt' // generic view parameters if passed
                    ]))
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