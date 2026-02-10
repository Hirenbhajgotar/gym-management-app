@extends('pdf.layout')

@section('content')
    <!-- Report Summary with Enhanced KPI Cards -->
    @if(isset($data['stats']) && !empty($data['stats']))
    <div class="section">
        <div class="section-title">Report Summary</div>
        <table>
            @foreach($data['stats'] ?? [] as $key => $value)
            @if(is_numeric($value))
                <tr>
                    <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                    <td>{{ is_numeric($value) && $value > 100 ? number_format($value, 2) : number_format($value) }}</td>
                </tr>
            @endif
            @endforeach
        </table>
    </div>
        {{-- <div class="section-title">Report Summary</div>
        <div class="section-content">
            <div class="stats-grid">
                @foreach($data['stats'] ?? [] as $key => $value)
                    @if(is_numeric($value))
                        @php
                            // Determine color based on metric type
                            $colorKey = 'primary';
                            $lowerKey = strtolower($key);
                            if (str_contains($lowerKey, 'revenue') || str_contains($lowerKey, 'income') || str_contains($lowerKey, 'profit')) {
                                $colorKey = 'success';
                            } elseif (str_contains($lowerKey, 'expense') || str_contains($lowerKey, 'cost')) {
                                $colorKey = 'danger';
                            } elseif (str_contains($lowerKey, 'pending') || str_contains($lowerKey, 'at_risk')) {
                                $colorKey = 'warning';
                            } elseif (str_contains($lowerKey, 'active') || str_contains($lowerKey, 'new')) {
                                $colorKey = 'info';
                            }
                        @endphp
                        <div class="stat-card" style="border-left-color: {{ $brandColors[$colorKey] ?? '#2563eb' }};">
                            <div class="stat-value">{{ is_numeric($value) && $value > 100 ? number_format($value, 2) : number_format($value) }}</div>
                            <div class="stat-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div> --}}
    </div>
    @endif

    <!-- Detailed Breakdown Table -->
    @if(isset($data['breakdown']) && !empty($data['breakdown']))
    <div class="section page-break">
        <div class="section-title">Detailed Breakdown</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        @foreach($data['breakdown'][0] as $key => $cell)
                            <th class="{{ is_numeric($cell) ? 'text-right' : '' }}">
                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['breakdown'] as $row)
                        <tr>
                            @foreach($row as $key => $value)
                                @if(is_numeric($value))
                                    <td class="text-right">{{ number_format($value, 2) }}</td>
                                @else
                                    <td>{{ $value }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Analytics Data Tables -->
    @if(isset($data['chart_data']) && !empty($data['chart_data']))
    <div class="section">
        <div class="section-title">Analytics Data Tables</div>
        <div class="section-content">
            @foreach($data['chart_data'] as $chartType => $chartData)
                @if(!empty($chartData))
                <div class="no-break" style="margin-bottom: 25px;">
                    <div style="font-weight: 600; margin-bottom: 12px; font-size: 14px; color: #374151; text-transform: uppercase; letter-spacing: 0.5px;">
                        {{ ucfirst(str_replace('_', ' ', $chartType)) }}
                    </div>
                    <table>
                        <thead>
                            <tr>
                                @foreach(array_keys($chartData[0] ?? []) as $column)
                                    <th class="{{ is_numeric($chartData[0][$column] ?? null) ? 'text-right' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $column)) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chartData as $row)
                                <tr>
                                    @foreach($row as $key => $value)
                                        @if(is_numeric($value))
                                            <td class="text-right">{{ number_format($value, 2) }}</td>
                                        @else
                                            <td>{{ $value }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    <!-- Monthly Trends -->
    @if(isset($data['monthly_trends']) && !empty($data['monthly_trends']))
    <div class="section">
        <div class="section-title">Monthly Trends</div>
        <div class="section-content">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Value</th>
                        <th class="text-right">Change %</th>
                        <th class="text-right">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['monthly_trends'] as $trend)
                        <tr>
                            <td>{{ $trend['month'] ?? '' }}</td>
                            <td class="text-right">
                                {{ is_numeric($trend['value'] ?? null) && $trend['value'] > 100 ? number_format($trend['value'], 2) : number_format($trend['value'] ?? 0) }}
                            </td>
                            <td class="text-right {{ ($trend['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ ($trend['change_percent'] ?? 0) >= 0 ? '+' : '' }}{{ $trend['change_percent'] ?? 0 }}%
                            </td>
                            <td class="text-center">
                                <span class="badge {{ ($trend['trend'] ?? 'stable') === 'up' ? 'badge-success' : (($trend['trend'] ?? 'stable') === 'down' ? 'badge-danger' : 'badge-warning') }}">
                                    {{ ucfirst($trend['trend'] ?? 'stable') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Chart Visualizations -->
    @if(isset($chartImages) && !empty($chartImages))
        <div class="section">
            <div class="section-title">Charts & Visualizations</div>
            <div class="section-content">
                @foreach($chartImages as $chartType => $chartImage)
                    @if($chartImage)
                        <div class="chart-container no-break" style="margin-bottom: 30px;">
                            <div class="chart-title">{{ Str::title(str_replace('_', ' ', $chartType)) }}</div>
                            <img src="{{ $chartImage }}" alt="{{ Str::title(str_replace('_', ' ', $chartType)) }}" class="chart-image">
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endsection