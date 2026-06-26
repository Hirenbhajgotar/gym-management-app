<?php

namespace App\Exports;

use App\Enums\PaymentStatus;
use App\Models\MemberPayment;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueReportExport implements FromQuery, Responsable, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?int $gymId;

    protected array $filters;

    private string $fileName = 'revenue-report.xlsx';

    public function __construct(?int $gymId = null, array $filters = [])
    {
        $this->gymId = $gymId;
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        return MemberPayment::query()
            ->where('gym_id', $this->gymId)
            ->when(
                isset($this->filters['start_date']) && $this->filters['start_date'],
                fn ($q) => $q->whereDate('payment_date', '>=', $this->filters['start_date'])
            )
            ->when(
                isset($this->filters['end_date']) && $this->filters['end_date'],
                fn ($q) => $q->whereDate('payment_date', '<=', $this->filters['end_date'])
            )
            ->when(
                isset($this->filters['status']) && $this->filters['status'],
                fn ($q) => $q->where('status', $this->filters['status'])
            )
            ->when(
                isset($this->filters['membership_plan_id']) && $this->filters['membership_plan_id'],
                fn ($q) => $q->where('membership_plan_id', $this->filters['membership_plan_id'])
            )
            ->with(['member', 'membershipPlan'])
            ->latest('payment_date');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Invoice Number',
            'Member Name',
            'Member Email',
            'Membership Plan',
            'Amount',
            'Currency',
            'Payment Method',
            'Status',
            'Payment Date',
            'Membership Start',
            'Membership End',
            'Notes',
            'Created At',
        ];
    }

    /**
     * @param  MemberPayment  $payment
     */
    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->invoice_number ?? '-',
            $payment->member?->name ?? '-',
            $payment->member?->email ?? '-',
            $payment->membershipPlan?->name ?? '-',
            $payment->amount,
            $payment->currency?->value ?? 'INR',
            ucfirst($payment->payment_method ?? '-'),
            $payment->status instanceof PaymentStatus ? $payment->status->getLabel() : ($payment->status ?? '-'),
            $payment->payment_date?->format('Y-m-d'),
            $payment->membership_start?->format('Y-m-d') ?? '-',
            $payment->membership_end?->format('Y-m-d') ?? '-',
            $payment->notes ?? '-',
            $payment->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row as bold
            1 => ['font' => ['bold' => true]],
        ];
    }
}
