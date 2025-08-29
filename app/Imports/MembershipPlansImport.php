<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\MembershipPlan;
use Filament\Facades\Filament;
use Illuminate\Validation\Rule;

class MembershipPlansImport extends BaseImporter
{
    public function processRow($row): void
    {
        MembershipPlan::create([
            'gym_id' => Filament::getTenant()->id,
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'duration' => $row['duration'],
            'currency' => $row['currency'],
            'is_active' => $row['is_active'] ?? true,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
