<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrainerResource\Pages\ManageTrainers;
use App\Imports\TrainerImport;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    public function test_trainer_import_is_working_correctly()
    {
        $this->actingAs(User::factory()->create());

        Storage::fake('local');
        Storage::fake('public');

        $headings = [
            'name',
            'email',
            'password',
            'address',
            'phone',
            'gender',
            'date_of_birth',
        ];

        $data = [
            $headings,
            ['Test Trainer', 'trainer@example.com', 'password', '123 Test St', '1234567890', 'male', '1995-05-10'],
        ];

        $file = $this->createExcelFile('trainers.xlsx', $data);

        Livewire::test(ManageTrainers::class)
            ->callAction('import', data: [
                'upload' => $file->path(),
                'importer' => TrainerImport::class,
                'model' => Trainer::class,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Import Successful');

        $this->assertDatabaseHas('users', [
            'email' => 'trainer@example.com',
        ]);

        $this->assertDatabaseHas('trainers', [
            'user_id' => User::where('email', 'trainer@example.com')->first()->id,
        ]);
    }

    private function createExcelFile(string $filename, array $data): UploadedFile
    {
        $export = new class($data) implements FromArray
        {
            public function __construct(private readonly array $data) {}

            public function array(): array
            {
                return $this->data;
            }
        };

        Excel::store($export, $filename, 'local');

        return new UploadedFile(Storage::disk('local')->path($filename), $filename, null, null, true);
    }

    public function test_trainer_import_validation_and_failed_rows()
    {
        $this->actingAs(User::factory()->create());

        Storage::fake('local');
        Storage::fake('public');

        $headings = [
            'name',
            'email',
            'password',
            'address',
            'phone',
            'gender',
            'date_of_birth',
        ];

        // First row is valid, second is invalid (missing name)
        $data = [
            $headings,
            ['Test Trainer', 'trainer@example.com', 'password', '123 Test St', '1234567890', 'male', '1995-05-10'],
            ['', 'invalid@example.com', 'password', '456 Invalid St', '0987654321', 'female', '1992-02-20'],
        ];

        $file = $this->createExcelFile('trainers_with_errors.xlsx', $data);

        Livewire::test(ManageTrainers::class)
            ->callAction('import', data: [
                'upload' => $file->path(),
                'importer' => TrainerImport::class,
                'model' => Trainer::class,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Import Partially Successful');

        $this->assertDatabaseHas('users', [
            'email' => 'trainer@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid@example.com',
        ]);

        Storage::disk('public')->assertExists(function (string $path) {
            return str_starts_with($path, 'failed-imports/failed_imports_');
        });
    }
}
