@extends('pdf.layout')

@section('content')
    <div class="section">
        <div class="section-title">Revenue Overview</div>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Total Revenue</th>
                <th>Transactions</th>
                <th>Avg Transaction</th>
                <th>ARPM</th>
            </tr>
            <tr>
                <td class="text-success">₹{{ number_format($data['stats']['total_revenue'] ?? 0) }}</td>
                <td>{{ number_format($data['stats']['transaction_count'] ?? 0) }}</td>
                <td>₹{{ number_format($data['stats']['avg_transaction'] ?? 0) }}</td>
                <td>₹{{ number_format($data['stats']['arpm'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Profit & Loss Summary</div>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Total Revenue</th>
                <th>Total Expenses</th>
                <th>Net Profit</th>
                <th>Margin</th>
            </tr>
            <tr>
                <td class="text-success">₹{{ number_format($data['pnl']['totals']['revenue'] ?? 0) }}</td>
                <td class="text-danger">₹{{ number_format($data['pnl']['totals']['expenses'] ?? 0) }}</td>
                <td style="font-weight: bold;">₹{{ number_format($data['pnl']['totals']['net_profit'] ?? 0) }}</td>
                <td>{{ $data['pnl']['totals']['margin'] ?? 0 }}%</td>
            </tr>
        </table>

        <div style="margin-top: 20px; font-weight: bold; margin-bottom: 10px;">Monthly Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net Profit</th>
                    <th class="text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['pnl']['breakdown'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['label'] ?? '' }}</td>
                        <td class="text-right text-success">₹{{ number_format($row['revenue'] ?? 0) }}</td>
                        <td class="text-right text-danger">₹{{ number_format($row['expenses'] ?? 0) }}</td>
                        <td class="text-right" style="font-weight: bold;">₹{{ number_format($row['net_profit'] ?? 0) }}</td>
                        <td class="text-right">
                            <span class="badge {{ ($row['margin'] ?? 0) >= 0 ? 'badge-success' : 'badge-danger' }}">
                                {{ $row['margin'] ?? 0 }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Pending & This Month</div>
        <table>
            <tr>
                <th>Pending Payments</th>
                <th>This Month Revenue</th>
                <th>Last Month Revenue</th>
                <th>Change</th>
            </tr>
            <tr>
                <td class="text-danger">₹{{ number_format($data['stats']['pending_payments'] ?? 0) }}</td>
                <td class="text-success">₹{{ number_format($data['stats']['this_month'] ?? 0) }}</td>
                <td>₹{{ number_format($data['stats']['last_month'] ?? 0) }}</td>
                <td class="{{ ($data['stats']['monthly_change'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ ($data['stats']['monthly_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($data['stats']['monthly_change'] ?? 0, 1) }}%
                </td>
            </tr>
        </table>
    </div>
@endsection
