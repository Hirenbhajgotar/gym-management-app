<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MemberReportExport implements FromQuery, Responsable, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?int $gymId;

    protected array $filters;

    private string $fileName = 'member-report.xlsx';

    public function __construct(?int $gymId = null, array $filters = [])
    {
        $this->gymId = $gymId;
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        return GymMember::query()
            ->where('gym_id', $this->gymId)
            ->when(
                isset($this->filters['start_date']) && $this->filters['start_date'],
                fn ($q) => $q->whereDate('created_at', '>=', $this->filters['start_date'])
            )
            ->when(
                isset($this->filters['end_date']) && $this->filters['end_date'],
                fn ($q) => $q->whereDate('created_at', '<=', $this->filters['end_date'])
            )
            ->when(
                isset($this->filters['status']) && $this->filters['status'] === 'active',
                fn ($q) => $q->membershipActive()
            )
            ->when(
                isset($this->filters['status']) && $this->filters['status'] === 'expired',
                fn ($q) => $q->membershipExpired()
            )
            ->when(
                isset($this->filters['membership_plan_id']) && $this->filters['membership_plan_id'],
                fn ($q) => $q->where('gym_membership_plan_id', $this->filters['membership_plan_id'])
            )
            ->with(['membershipPlan', 'trainer'])
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Age',
            'Date of Birth',
            'Membership Plan',
            'Trainer',
            'Membership Start',
            'Membership End',
            'Membership Status',
            'Payment Status',
            'Joined At',
        ];
    }

    /**
     * @param  GymMember  $member
     */
    public function map($member): array
    {
        $membershipStatus = 'No Membership';
        if ($member->membership_end) {
            $membershipStatus = $member->membership_end->isPast() ? 'Expired' : 'Active';
        }

        return [
            $member->id,
            $member->name,
            $member->email,
            $member->phone ?? '-',
            $member->age ?? '-',
            $member->dob?->format('Y-m-d') ?? '-',
            $member->membershipPlan?->name ?? '-',
            $member->trainer?->name ?? 'No Trainer',
            $member->membership_start?->format('Y-m-d') ?? '-',
            $member->membership_end?->format('Y-m-d') ?? '-',
            $membershipStatus,
            $member->payment_status instanceof PaymentStatus ? $member->payment_status->getLabel() : ($member->payment_status ?? '-'),
            $member->created_at?->format('Y-m-d H:i'),
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
