@extends('pdf.layout')

@section('content')
    <div class="section">
        <div class="section-title">Executive Summary</div>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Total Members</th>
                <th>Active Members</th>
                <th>New Joins (This Month)</th>
                <th>MTD Revenue</th>
            </tr>
            <tr>
                <td>{{ number_format($data['memberStats']['total'] ?? 0) }}</td>
                <td class="text-success">{{ number_format($data['memberStats']['active'] ?? 0) }}</td>
                <td class="text-success">+{{ number_format($data['memberStats']['new_this_month'] ?? 0) }}</td>
                <td class="text-success">₹{{ number_format($data['revenueStats']['mtd'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    @if(!empty($data['insights']))
    <div class="section">
        <div class="section-title">Key Insights</div>
        @foreach($data['insights'] as $insight)
            <div style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-left: 3px solid {{ match($insight['type'] ?? 'info') { 'danger' => '#dc2626', 'warning' => '#f59e0b', 'success' => '#16a34a', default => '#3b82f6' } }};">
                <div style="font-weight: bold;">{{ $insight['title'] ?? '' }}</div>
                <div style="font-size: 11px; margin-top: 2px;">{{ $insight['message'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
    @endif

    <div class="section">
        <div class="section-title">Revenue Overview</div>
        <table>
            <tr>
                <th>MTD Revenue</th>
                <th>YTD Revenue</th>
                <th>Last Month</th>
                <th>Avg Attendance/Day</th>
            </tr>
            <tr>
                <td class="text-success">₹{{ number_format($data['revenueStats']['mtd'] ?? 0) }}</td>
                <td class="text-success">₹{{ number_format($data['revenueStats']['ytd'] ?? 0) }}</td>
                <td>₹{{ number_format($data['revenueStats']['last_month'] ?? 0) }}</td>
                <td>{{ number_format($data['attendanceStats']['weekly_average'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Lead Pipeline</div>
        <table>
            <tr>
                <th>Total Leads</th>
                <th>Conversion Rate</th>
                <th>Active Pipeline</th>
            </tr>
            <tr>
                <td>{{ number_format($data['leadStats']['total'] ?? 0) }}</td>
                <td>{{ $data['leadStats']['conversion_rate'] ?? 0 }}%</td>
                <td>{{ number_format($data['leadStats']['active'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Attendance Today</div>
        <table>
            <tr>
                <th>Total Check-ins</th>
                <th>Currently In</th>
                <th>Peak Hour</th>
            </tr>
            <tr>
                <td>{{ number_format($data['attendanceStats']['today'] ?? 0) }}</td>
                <td class="text-success">{{ number_format($data['attendanceStats']['currently_in'] ?? 0) }}</td>
                <td>{{ $data['attendanceStats']['peak_hour'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>
@endsection
