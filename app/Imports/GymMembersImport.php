<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Rules\ValidPhone;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class GymMembersImport implements ToModel, WithEvents, WithHeadingRow, WithValidation
{
    protected $gymId;

    protected $failedRows = [];

    public function __construct($gymId)
    {
        $this->gymId = $gymId;
    }

    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $plan = MembershipPlan::query()->where('gym_id', $this->gymId)
            ->where('name', $row['membership_plan_name'])
            ->first();

        if (! $plan) {
            $this->failedRows[] = $row;

            return null; // Skip if plan not found
        }

        $member = new GymMember([
            'gym_id' => $this->gymId,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'weight' => $row['weight'] ?? null,
            'height' => $row['height'] ?? null,
            'age' => $row['age'] ?? null,
            'dob' => $row['dob'] ? Date::parse($row['dob']) : null,
            'membership_start' => $row['membership_start'] ? Date::parse($row['membership_start']) : now(),
            'gym_membership_plan_id' => $plan->id,
        ]);

        // Save the member first to get the ID
        $member->save();

        // Create payment record if payment data exists
        if (isset($row['payment_amount']) && $row['payment_amount'] > 0) {
            MemberPayment::query()->create([
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'amount' => $row['payment_amount'],
                'currency' => $row['payment_currency'] ?? 'INR',
                'payment_method' => $row['payment_method'] ?? 'cash',
                'status' => $row['payment_status'] ?? 'completed',
                'notes' => $row['payment_notes'] ?? null,
                'payment_date' => $row['payment_date'] ? Date::parse($row['payment_date']) : now(),
            ]);
        }

        return $member;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:gym_members,email',
            'phone' => ['nullable', 'max:255', new ValidPhone],
            'address' => 'nullable|string|max:65535',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'age' => 'nullable|integer|min:0|max:150',
            'dob' => 'nullable|date',
            'membership_start' => 'nullable|date',
            'membership_plan_name' => 'required|string',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_currency' => 'nullable|string|in:INR,USD',
            'payment_method' => 'nullable|string|in:cash,upi,credit_debit_card',
            'payment_status' => 'nullable|string|in:completed,pending',
            'payment_date' => 'nullable|date',
            'payment_notes' => 'nullable|string',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                if (filled($this->failedRows)) {
                    try {
                        // Ensure imports directory exists
                        $importsDir = storage_path('app/imports');
                        if (! File::exists($importsDir)) {
                            File::makeDirectory($importsDir, 0755, true);
                        } elseif (! is_writable($importsDir)) {
                            throw new \Exception('Imports directory is not writable: ' . $importsDir);
                        }

                        // Generate failed rows CSV
                        $timestamp = time();
                        $filePath = 'app/imports/';
                        $fileName = "failed_rows_{$timestamp}.csv";
                        $path = storage_path($filePath . $fileName);
                        $file = fopen($path, 'w');
                        throw_if($file === false, \Exception::class, 'Failed to open file for writing: ' . $path);
                        $headers = array_merge(array_keys($this->failedRows[0]), ['error']);
                        fputcsv($file, $headers);
                        foreach ($this->failedRows as $row) {
                            fputcsv($file, $row);
                        }
                        fclose($file);

                        // Store path for download
                        $downloadPath = "imports/failed_rows_{$timestamp}.csv";
                        Storage::disk('local')->put($downloadPath, file_get_contents($path));

                        $recipient = auth()->user();
                        // Notify user
                        Notification::make()
                            ->title('Some rows failed to import')
                            ->body('The membership plan was not found for the selected gym in some rows.')
                            ->actions([
                                Action::make('download')
                                    ->label('Download Failed Rows')
                                    ->url(route('download.failed.rows', ['file' => $fileName]))
                                    ->openUrlInNewTab(),
                            ])
                            ->warning()
                            ->sendToDatabase($recipient)
                            ->send();

                        event(new DatabaseNotificationsSent($recipient));
                    } catch (\Exception $e) {
                        $recipient = auth()->user();
                        Notification::make()
                            ->title('Import partially failed')
                            ->body('Some rows could not be imported due to missing membership plans. Failed to generate error report.')
                            ->warning()
                            ->sendToDatabase($recipient)
                            ->send();

                        event(new DatabaseNotificationsSent($recipient));
                    }
                } else {
                    Notification::make()
                        ->title('Members imported successfully')
                        ->success()
                        ->send();
                }
            },
        ];
    }
}
