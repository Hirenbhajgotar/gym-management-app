@extends('pdf.layout')

@section('content')
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

    @if(isset($data['breakdown']) && !empty($data['breakdown']))
    <div class="section">
        <div class="section-title">Detailed Breakdown</div>
        <table>
            @foreach($data['breakdown'] as $row)
                <tr>
                    @foreach($row as $cell)
                        @if(is_numeric($cell))
                            <td>{{ number_format($cell) }}</td>
                        @else
                            <td>{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
    @endif

    {{-- Chart Data Tables for PDF --}}
    @if(isset($data['chart_data']) && !empty($data['chart_data']))
    <div class="section">
        <div class="section-title">Analytics Data</div>
        
        @foreach($data['chart_data'] as $chartType => $chartData)
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">{{ ucfirst(str_replace('_', ' ', $chartType)) }} Data</div>
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
        @endforeach
    </div>
    @endif

    {{-- Monthly Trends Data --}}
    @if(isset($data['monthly_trends']) && !empty($data['monthly_trends']))
    <div class="section">
        <div class="section-title">Monthly Trends</div>
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
                        <td class="text-right">{{ is_numeric($trend['value'] ?? null) && $trend['value'] > 100 ? number_format($trend['value'], 2) : number_format($trend['value'] ?? 0) }}</td>
                        <td class="text-right {{ ($trend['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($trend['change_percent'] ?? 0) >= 0 ? '+' : '' }}{{ $trend['change_percent'] ?? 0 }}%
                        </td>
                        <td class="text-center">{{ $trend['trend'] ?? 'Stable' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endsection
