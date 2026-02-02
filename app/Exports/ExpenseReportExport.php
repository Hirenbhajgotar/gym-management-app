<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseReportExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $expenses,
        protected Collection $categoryBreakdown,
        protected array $filters
    ) {}

    public function sheets(): array
    {
        return [
            new ExpenseDetailsSheet($this->expenses),
            new CategorySummarySheet($this->categoryBreakdown),
        ];
    }
}

class ExpenseDetailsSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected Collection $expenses
    ) {}

    public function collection()
    {
        return $this->expenses;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Title',
            'Description',
            'Category',
            'Amount',
            'Currency',
            'Payment Method',
            'Vendor',
            'Reference Number',
            'Submitted By',
            'Status',
            'Approved By',
            'Approved At',
        ];
    }

    public function map($expense): array
    {
        return [
            $expense->expense_date->format('Y-m-d'),
            $expense->title,
            $expense->description ?? '',
            $expense->category?->name ?? 'Uncategorized',
            $expense->amount,
            $expense->currency,
            $expense->payment_method?->getLabel() ?? '',
            $expense->vendor_name ?? '',
            $expense->reference_number ?? '',
            $expense->user?->name ?? '',
            $expense->status->getLabel(),
            $expense->approvedBy?->name ?? '',
            $expense->approved_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    public function title(): string
    {
        return 'Expense Details';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class CategorySummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected Collection $categoryBreakdown
    ) {}

    public function collection()
    {
        return $this->categoryBreakdown->map(function ($category) {
            return [
                'category' => $category['name'],
                'count' => $category['count'],
                'total' => $category['total'],
                'percentage' => $category['percentage'] . '%',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Category',
            'Number of Expenses',
            'Total Amount',
            'Percentage',
        ];
    }

    public function title(): string
    {
        return 'Category Summary';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
