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
                <td class="text-success">INR {{ number_format($data['revenueStats']['mtd'] ?? 0) }}</td>
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
                <td class="text-success">INR {{ number_format($data['revenueStats']['mtd'] ?? 0) }}</td>
                <td class="text-success">INR {{ number_format($data['revenueStats']['ytd'] ?? 0) }}</td>
                <td>INR {{ number_format($data['revenueStats']['last_month'] ?? 0) }}</td>
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

    {{-- Chart Data Tables for PDF --}}
    <div class="section">
        <div class="section-title">Additional Analytics Data</div>
        
        {{-- Membership Trends Data --}}
        @if(isset($data['memberStats']['growth_data']) && !empty($data['memberStats']['growth_data']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Monthly Membership Trends</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">New Members</th>
                        <th class="text-right">Active Members</th>
                        <th class="text-right">Growth %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['memberStats']['growth_data'] as $monthData)
                        <tr>
                            <td>{{ $monthData['month'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($monthData['new_members'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($monthData['active_members'] ?? 0) }}</td>
                            <td class="text-right">{{ $monthData['growth_rate'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Revenue Trend Data --}}
        @if(isset($data['revenueStats']['monthly_data']) && !empty($data['revenueStats']['monthly_data']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Monthly Revenue Trends</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Transactions</th>
                        <th class="text-right">Avg Transaction</th>
                        <th class="text-right">Change %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['revenueStats']['monthly_data'] as $monthData)
                        <tr>
                            <td>{{ $monthData['month'] ?? '' }}</td>
                            <td class="text-right text-success">INR {{ number_format($monthData['revenue'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($monthData['transactions'] ?? 0) }}</td>
                            <td class="text-right">INR {{ number_format($monthData['avg_transaction'] ?? 0) }}</td>
                            <td class="text-right {{ ($monthData['change_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ ($monthData['change_rate'] ?? 0) >= 0 ? '+' : '' }}{{ $monthData['change_rate'] ?? 0 }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Lead Conversion Funnel Data --}}
        @if(isset($data['leadStats']['funnel_data']) && !empty($data['leadStats']['funnel_data']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Lead Conversion Funnel</div>
            <table>
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Conversion Rate</th>
                        <th class="text-right">Drop-off Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['leadStats']['funnel_data'] as $stage)
                        <tr>
                            <td>{{ $stage['stage'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($stage['count'] ?? 0) }}</td>
                            <td class="text-right text-success">{{ $stage['conversion_rate'] ?? 0 }}%</td>
                            <td class="text-right text-danger">{{ $stage['dropoff_rate'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Attendance Pattern Data --}}
        @if(isset($data['attendanceStats']['daily_data']) && !empty($data['attendanceStats']['daily_data']))
        <div>
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Weekly Attendance Patterns</div>
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th class="text-right">Check-ins</th>
                        <th class="text-right">Peak Hour</th>
                        <th class="text-right">Avg Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['attendanceStats']['daily_data'] as $dayData)
                        <tr>
                            <td>{{ $dayData['day'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($dayData['checkins'] ?? 0) }}</td>
                            <td class="text-right">{{ $dayData['peak_hour'] ?? '' }}</td>
                            <td class="text-right">{{ $dayData['avg_duration'] ?? 0 }} mins</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
@endsection
