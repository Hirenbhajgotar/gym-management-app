@extends('pdf.layout')

@section('content')
    <div class="section">
        <div class="section-title">Member Overview</div>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Total Members</th>
                <th>Active Members</th>
                <th>Expired Members</th>
                <th>Retention Rate</th>
            </tr>
            <tr>
                <td>{{ number_format($data['stats']['total'] ?? 0) }}</td>
                <td class="text-success">{{ number_format($data['stats']['active'] ?? 0) }}</td>
                <td class="text-danger">{{ number_format($data['stats']['expired'] ?? 0) }}</td>
                <td>{{ number_format($data['stats']['retention_rate'] ?? 0) }}%</td>
            </tr>
        </table>
    </div>

    {{-- Chart Data Tables for PDF --}}
    <div class="section">
        <div class="section-title">Membership Analytics Data</div>
        
        {{-- Membership Growth Data --}}
        @if(!empty($data['growth']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Monthly Membership Growth</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">New Members</th>
                        <th class="text-right">Growth Rate</th>
                        <th class="text-right">Retention Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['growth'] as $row)
                        <tr>
                            <td>{{ $row['month'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($row['count'] ?? 0) }}</td>
                            <td class="text-right text-success">{{ $row['growth_rate'] ?? 0 }}%</td>
                            <td class="text-right text-info">{{ $row['retention_rate'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Detailed Plan Distribution --}}
        @if(!empty($data['planDistribution']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Membership Plan Analysis</div>
            <table>
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th class="text-right">Members</th>
                        <th class="text-right">Percentage</th>
                        <th class="text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalMembers = collect($data['planDistribution'])->sum('count'); @endphp
                    @foreach($data['planDistribution'] as $plan)
                        <tr>
                            <td>{{ $plan['name'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($plan['count'] ?? 0) }}</td>
                            <td class="text-right">{{ $totalMembers > 0 ? number_format(($plan['count'] ?? 0) / $totalMembers * 100, 1) : 0 }}%</td>
                            <td class="text-right text-success">INR {{ number_format($plan['revenue'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Churn Risk Breakdown --}}
        @if(!empty($data['churnStats']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Churn Risk Analysis</div>
            <table>
                <thead>
                    <tr>
                        <th>Risk Level</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Percentage</th>
                        <th class="text-right">Avg Days Since Visit</th>
                        <th class="text-right">Membership Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-danger">High Risk</td>
                        <td class="text-right">{{ number_format($data['churnStats']['high_risk'] ?? 0) }}</td>
                        <td class="text-right">{{ ($data['churnStats']['at_risk_count'] ?? 0) > 0 ? number_format(($data['churnStats']['high_risk'] ?? 0) / ($data['churnStats']['at_risk_count'] ?? 0) * 100, 1) : 0 }}%</td>
                        <td class="text-right">{{ $data['churnStats']['high_risk_avg_days'] ?? 0 }}</td>
                        <td class="text-right text-danger">INR {{ number_format($data['churnStats']['high_risk_value'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="text-warning" style="color: #f59e0b;">Medium Risk</td>
                        <td class="text-right">{{ number_format($data['churnStats']['medium_risk'] ?? 0) }}</td>
                        <td class="text-right">{{ ($data['churnStats']['at_risk_count'] ?? 0) > 0 ? number_format(($data['churnStats']['medium_risk'] ?? 0) / ($data['churnStats']['at_risk_count'] ?? 0) * 100, 1) : 0 }}%</td>
                        <td class="text-right">{{ $data['churnStats']['medium_risk_avg_days'] ?? 0 }}</td>
                        <td class="text-right text-warning">INR {{ number_format($data['churnStats']['medium_risk_value'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="text-success">Low Risk</td>
                        <td class="text-right">{{ number_format($data['churnStats']['low_risk'] ?? 0) }}</td>
                        <td class="text-right">{{ ($data['churnStats']['at_risk_count'] ?? 0) > 0 ? number_format(($data['churnStats']['low_risk'] ?? 0) / ($data['churnStats']['at_risk_count'] ?? 0) * 100, 1) : 0 }}%</td>
                        <td class="text-right">{{ $data['churnStats']['low_risk_avg_days'] ?? 0 }}</td>
                        <td class="text-right text-success">INR {{ number_format($data['churnStats']['low_risk_value'] ?? 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Top At-Risk Members --}}
        @if(isset($data['churnStats']['top_at_risk']) && !empty($data['churnStats']['top_at_risk']))
        <div>
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Top 10 High-Risk Members</div>
            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Risk Score</th>
                        <th class="text-right">Days Since Visit</th>
                        <th class="text-right">Membership Value</th>
                        <th class="text-right">Plan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['churnStats']['top_at_risk'] as $member)
                        <tr>
                            <td>{{ $member['name'] ?? '' }}</td>
                            <td class="text-danger">{{ $member['risk_score'] ?? 0 }}/100</td>
                            <td class="text-right">{{ $member['days_since_visit'] ?? 0 }}</td>
                            <td class="text-right">INR {{ number_format($member['membership_value'] ?? 0) }}</td>
                            <td class="text-right">{{ $member['plan_name'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
        @endif
    </div>
@endsection
