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
            'duration' => $row['duration_in_months'],
            'currency' => $row['currency'] ?? Currency::INR->value,
            'is_active' => $row['is_active'] ?? true,
        ]);
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
}
