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

    <div class="section">
        <div class="section-title">Member Details</div>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>With Trainer</th>
                <th>Avg Duration (Days)</th>
                <th>Active Rate</th>
                <th>With Trainer Rate</th>
            </tr>
            <tr>
                <td>{{ number_format($data['stats']['with_trainer'] ?? 0) }}</td>
                <td>{{ number_format($data['stats']['avg_duration_days'] ?? 0) }}</td>
                <td>{{ number_format($data['stats']['active_rate'] ?? 0) }}%</td>
                <td>{{ $data['stats']['total'] > 0 ? number_format(($data['stats']['with_trainer'] ?? 0) / $data['stats']['total'] * 100, 1) : 0 }}%</td>
            </tr>
        </table>
    </div>

    @if(!empty($data['growth']))
    <div class="section">
        <div class="section-title">Member Growth</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">New Members</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['growth'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['month'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($row['count'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($data['planDistribution']))
    <div class="section">
        <div class="section-title">Plan Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>Plan</th>
                    <th class="text-right">Members</th>
                    <th class="text-right">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php $totalMembers = collect($data['planDistribution'])->sum('count'); @endphp
                @foreach($data['planDistribution'] ?? [] as $plan)
                    <tr>
                        <td>{{ $plan['name'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($plan['count'] ?? 0) }}</td>
                        <td class="text-right">{{ $totalMembers > 0 ? number_format(($plan['count'] ?? 0) / $totalMembers * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($data['churnStats']))
    <div class="section">
        <div class="section-title">Churn Risk</div>
        <table>
            <tr>
                <th>High Risk Members</th>
                <th>Medium Risk Members</th>
                <th>Low Risk Members</th>
            </tr>
            <tr>
                <td class="text-danger">{{ number_format($data['churnStats']['high_risk'] ?? 0) }}</td>
                <td class="text-warning" style="color: #f59e0b;">{{ number_format($data['churnStats']['medium_risk'] ?? 0) }}</td>
                <td class="text-success">{{ number_format($data['churnStats']['low_risk'] ?? 0) }}</td>
            </tr>
        </table>
    </div>
    @endif
@endsection
