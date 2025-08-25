<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class BaseEmployeeImporter implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public function __construct(protected string $modelClass) {}

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => bcrypt($row['password'] ?? 'password'),
            ]);

            $user->employeeDetail()->create([
                'address' => $row['address'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'date_of_birth' => $row['date_of_birth'],
            ]);

            $model = $this->modelClass::create([
                'user_id' => $user->id,
            ]);

            $user->assignRole(class_basename($this->modelClass));
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'address' => 'required|string',
            'phone' => 'required|string',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
        ];
    }
}
