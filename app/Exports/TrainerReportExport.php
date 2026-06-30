<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Trainer;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainerReportExport implements FromQuery, Responsable, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?int $gymId;

    private string $fileName = 'trainer-report.xlsx';

    public function __construct(?int $gymId = null)
    {
        $this->gymId = $gymId;
    }

    public function query(): Builder
    {
        return Trainer::query()
            ->where('gym_id', $this->gymId)
            ->with(['members.payments'])
            ->withCount(['members' => fn ($q) => $q->membershipActive()])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Specialization',
            'Experience (Years)',
            'Status',
            'Active Clients',
            'Basic Salary',
            'PT Commission Rate (%)',
            'Total Earnings (Est)',
            'Joined Date',
        ];
    }

    /**
     * @param  Trainer  $trainer
     */
    public function map($trainer): array
    {
        return [
            $trainer->id,
            $trainer->name,
            $trainer->email,
            $trainer->phone,
            $trainer->specialization ?? '-',
            $trainer->experience_years ?? 0,
            $trainer->is_available ? 'Active' : 'Unavailable',
            $trainer->members_count,
            $trainer->basic_salary,
            $trainer->pt_salary_per_member,
            $trainer->total_earnings, // Uses the accessor I saw in Trainer.php
            $trainer->created_at?->format('Y-m-d'),
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
