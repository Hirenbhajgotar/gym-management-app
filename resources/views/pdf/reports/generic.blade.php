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

    <div class="section">
        <div class="section-title">Generated Information</div>
        <table>
            <tr>
                <th>Report Type</th>
                <td>{{ $report->report_type }}</td>
            </tr>
            <tr>
                <th>Generated On</th>
                <td>{{ $date }}</td>
            </tr>
            @if(isset($data['stats']['start_date']))
            <tr>
                <th>Report Period</th>
                <td>{{ \Carbon\Carbon::parse($data['stats']['start_date'] ?? 'N/A')->format('M j, Y') }} - {{ \Carbon\Carbon::parse($data['stats']['end_date'] ?? 'N/A')->format('M j, Y') }}</td>
            </tr>
            @endif
        </table>
    </div>
@endsection
