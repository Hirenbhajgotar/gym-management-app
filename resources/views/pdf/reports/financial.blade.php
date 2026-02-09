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
                <td class="text-success">INR {{ number_format($data['stats']['total_revenue'] ?? 0) }}</td>
                <td>{{ number_format($data['stats']['transaction_count'] ?? 0) }}</td>
                <td>INR {{ number_format($data['stats']['avg_transaction'] ?? 0) }}</td>
                <td>INR {{ number_format($data['stats']['arpm'] ?? 0) }}</td>
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
                <td class="text-success">INR {{ number_format($data['pnl']['totals']['revenue'] ?? 0) }}</td>
                <td class="text-danger">INR {{ number_format($data['pnl']['totals']['expenses'] ?? 0) }}</td>
                <td style="font-weight: bold;">INR {{ number_format($data['pnl']['totals']['net_profit'] ?? 0) }}</td>
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
                        <td class="text-right text-success">INR {{ number_format($row['revenue'] ?? 0) }}</td>
                        <td class="text-right text-danger">INR {{ number_format($row['expenses'] ?? 0) }}</td>
                        <td class="text-right" style="font-weight: bold;">INR {{ number_format($row['net_profit'] ?? 0) }}</td>
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
                <td class="text-danger">INR {{ number_format($data['stats']['pending_payments'] ?? 0) }}</td>
                <td class="text-success">INR {{ number_format($data['stats']['this_month'] ?? 0) }}</td>
                <td>INR {{ number_format($data['stats']['last_month'] ?? 0) }}</td>
                <td class="{{ ($data['stats']['monthly_change'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ ($data['stats']['monthly_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($data['stats']['monthly_change'] ?? 0, 1) }}%
                </td>
            </tr>
        </table>
    </div>

    {{-- Chart Data Tables for PDF --}}
    <div class="section">
        <div class="section-title">Financial Analytics Data</div>
        
        {{-- Revenue Trend Analysis --}}
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Revenue Trend Analysis</div>
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Expenses</th>
                        <th class="text-right">Net Profit</th>
                        <th class="text-right">Margin</th>
                        <th class="text-right">Growth %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['pnl']['breakdown'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['label'] ?? '' }}</td>
                            <td class="text-right text-success">INR {{ number_format($row['revenue'] ?? 0) }}</td>
                            <td class="text-right text-danger">INR {{ number_format($row['expenses'] ?? 0) }}</td>
                            <td class="text-right" style="font-weight: bold;">INR {{ number_format($row['net_profit'] ?? 0) }}</td>
                            <td class="text-right">
                                <span class="badge {{ ($row['margin'] ?? 0) >= 0 ? 'badge-success' : 'badge-danger' }}">
                                    {{ $row['margin'] ?? 0 }}%
                                </span>
                            </td>
                            <td class="text-right {{ ($row['growth_rate'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ ($row['growth_rate'] ?? 0) >= 0 ? '+' : '' }}{{ $row['growth_rate'] ?? 0 }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Revenue Breakdown by Category --}}
        @if(isset($data['revenue_breakdown']) && !empty($data['revenue_breakdown']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Revenue by Category</div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Category</th>
                        <th class="text-right">Amount</th>
                        <th style="width: 100px;">Distribution</th>
                        <th class="text-right">Transactions</th>
                        <th class="text-right">Avg Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $totalRevenue = collect($data['revenue_breakdown'])->sum('amount'); 
                        $maxRevenue = collect($data['revenue_breakdown'])->max('amount');
                    @endphp
                    @foreach($data['revenue_breakdown'] as $category)
                        @php 
                            $percentage = $totalRevenue > 0 ? ($category['amount'] / $totalRevenue * 100) : 0;
                            $barWidth = $maxRevenue > 0 ? ($category['amount'] / $maxRevenue * 100) : 0;
                        @endphp
                        <tr>
                            <td>{{ $category['name'] ?? 'Other' }}</td>
                            <td class="text-right text-success">INR {{ number_format($category['amount'] ?? 0) }}</td>
                            <td style="vertical-align: middle;">
                                <div style="font-size: 10px; margin-bottom: 2px;">{{ number_format($percentage, 1) }}%</div>
                                <div style="background-color: #e5e7eb; height: 6px; width: 100%; border-radius: 3px; overflow: hidden;">
                                    <div style="background-color: #10b981; height: 100%; width: {{ $barWidth }}%;"></div>
                                </div>
                            </td>
                            <td class="text-right">{{ number_format($category['transactions'] ?? 0) }}</td>
                            <td class="text-right">INR {{ number_format(($category['amount'] ?? 0) / max(1, $category['transactions'] ?? 1)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Expense Analysis --}}
        @if(isset($data['expense_breakdown']) && !empty($data['expense_breakdown']))
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Expense Analysis by Category</div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Category</th>
                        <th class="text-right">Amount</th>
                        <th style="width: 100px;">Distribution</th>
                        <th class="text-right">Change</th>
                        <th class="text-right">Budget Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $totalExpenses = collect($data['expense_breakdown'])->sum('amount'); 
                        $maxExpense = collect($data['expense_breakdown'])->max('amount');
                    @endphp
                    @foreach($data['expense_breakdown'] as $expense)
                        @php 
                            $percentage = $totalExpenses > 0 ? ($expense['amount'] / $totalExpenses * 100) : 0;
                            $barWidth = $maxExpense > 0 ? ($expense['amount'] / $maxExpense * 100) : 0;
                        @endphp
                        <tr>
                            <td>{{ $expense['name'] ?? 'Uncategorized' }}</td>
                            <td class="text-right text-danger">INR {{ number_format($expense['amount'] ?? 0) }}</td>
                            <td style="vertical-align: middle;">
                                <div style="font-size: 10px; margin-bottom: 2px;">{{ number_format($percentage, 1) }}%</div>
                                <div style="background-color: #e5e7eb; height: 6px; width: 100%; border-radius: 3px; overflow: hidden;">
                                    <div style="background-color: #ef4444; height: 100%; width: {{ $barWidth }}%;"></div>
                                </div>
                            </td>
                            <td class="text-right {{ ($expense['change_rate'] ?? 0) >= 0 ? 'text-danger' : 'text-success' }}">
                                {{ ($expense['change_rate'] ?? 0) >= 0 ? '+' : '' }}{{ $expense['change_rate'] ?? 0 }}%
                            </td>
                            <td class="text-right">
                                @if(isset($expense['budget_variance']) && $expense['budget_variance'] != 0)
                                    <span class="{{ $expense['budget_variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                        INR {{ number_format(abs($expense['budget_variance'])) }}
                                        {{ $expense['budget_variance'] > 0 ? ' over' : ' under' }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
@endsection
