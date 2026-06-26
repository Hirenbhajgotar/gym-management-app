<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadReportExport implements FromQuery, Responsable, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?int $gymId;

    protected array $filters;

    private string $fileName = 'lead-report.xlsx';

    public function __construct(?int $gymId = null, array $filters = [])
    {
        $this->gymId = $gymId;
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        return Lead::query()
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
                isset($this->filters['source']) && $this->filters['source'],
                fn ($q) => $q->where('source', $this->filters['source'])
            )
            ->when(
                isset($this->filters['status']) && $this->filters['status'],
                fn ($q) => $q->where('status', $this->filters['status'])
            )
            ->with(['membershipPlan', 'creator'])->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Source',
            'Status',
            'Interested Plan',
            'Last Follow-up',
            'Created By',
            'Created At',
            'Updated At',
            'Notes',
        ];
    }

    /**
     * @param  Lead  $lead
     */
    public function map($lead): array
    {
        return [
            $lead->id,
            $lead->name,
            $lead->email,
            $lead->phone,
            $lead->source?->getLabel() ?? '-',
            $lead->status?->getLabel() ?? '-',
            $lead->membershipPlan?->name ?? '-',
            $lead->last_follow_up_date?->format('Y-m-d') ?? 'Never',
            $lead->creator?->name ?? '-',
            $lead->created_at?->format('Y-m-d H:i'),
            $lead->updated_at?->format('Y-m-d H:i'),
            $lead->notes ?? '-',
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
