<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\GymMember;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GymMemberExporter extends Exporter
{
    protected static ?string $model = GymMember::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('payments.transactions');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('weight'),
            ExportColumn::make('height'),
            ExportColumn::make('age'),
            ExportColumn::make('dob'),
            ExportColumn::make('membership_start'),
            ExportColumn::make('membership_end'),
            ExportColumn::make('payment_status')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('total_paid')
                ->label('Total Paid')
                ->formatStateUsing(function (GymMember $record): float {
                    // /** @var float $totalPaid */
                    // $totalPaid = $record->payments()
                    //     ->with('transactions')
                    //     ->get()
                    //     ->sum(fn ($payment) => $payment->total_paid);

                    // return $totalPaid;

                    return (float) $record->payments
                        ->loadMissing('transactions')
                        ->sum(fn ($payment) => $payment->transactions
                            ->where('status', 'completed')
                            ->sum('amount')
                        );
                }),
            ExportColumn::make('outstanding_balance')
                ->label('Outstanding Balance')
                ->formatStateUsing(function (GymMember $record): float {
                    $totalPlans = (float) $record->payments->sum('amount');
                    /** @var float $totalPaid */
                    $totalPaid = $record->payments
                        ->loadMissing('transactions')
                        ->sum(fn ($payment) => $payment->transactions
                            ->where('status', 'completed')
                            ->sum('amount')
                        );

                    return max(0, $totalPlans - $totalPaid);
                }),
            ExportColumn::make('payment_installments')
                ->label('Installments')
                ->formatStateUsing(fn (GymMember $record) => $record->payments->sum(fn ($p) => $p->transactions->count())),
            ExportColumn::make('latest_payment_date')
                ->label('Latest Payment Date')
                ->formatStateUsing(function (mixed $state): ?string {
                    if ($state instanceof Carbon) {
                        return $state->toDateString();
                    }

                    return is_string($state) ? $state : null;
                }),
            ExportColumn::make('trainer.name'),
            ExportColumn::make('membershipPlan.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your member export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
