<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\MembershipPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Validation\Rule;

class MembershipPlansImport extends BaseImporter implements ShouldQueue
{
    protected static ?string $model = MembershipPlan::class;

    public function __construct(public int $tenantId)
    {
        parent::__construct();
    }

    public function processRow($row): void
    {
        MembershipPlan::withoutEvents(function () use ($row) {
            MembershipPlan::create([
                'gym_id' => $this->tenantId,
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'price' => $row['price'],
                'duration' => $row['duration_in_months'],
                'currency' => $row['currency'] ?? Currency::INR->value,
                'is_active' => $row['is_active'] ?? true,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_in_months' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', Rule::enum(Currency::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    // public function registerEvents(): array
    // {
    //     return [
    //         AfterImport::class => function (AfterImport $event) {
    //             $recipient = Auth::user();

    //             if (! $recipient) {
    //                 return;
    //             }

    //             if ($this->failedRows > 0) {
    //                 $failedRows = number_format($this->failedRows) . ' ' . str($this->failedRows)->plural('row');
    //                 $message = "{$failedRows} failed to import due to validation errors.";

    //                 Notification::make()
    //                     ->danger()
    //                     ->title('Import Completed')
    //                     ->body($message)
    //                     ->sendToDatabase($recipient)
    //                     ->send();
    //             } else {
    //                 $successfulRows = number_format($this->successfulRows) . ' ' . str($this->successfulRows)->plural('row');
    //                 $message = "Your import has completed and {$successfulRows} imported.";

    //                 Notification::make()
    //                     ->success()
    //                     ->title('Import Completed')
    //                     ->body($message)
    //                     ->sendToDatabase($recipient)
    //                     ->send();
    //             }

    //             event(new DatabaseNotificationsSent($recipient));
    //         },
    //     ];
    // }
}
