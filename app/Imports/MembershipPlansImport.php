<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\MembershipPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Validation\Rule;

class MembershipPlansImport extends BaseImporter implements ShouldQueue
{
    protected static ?string $model = MembershipPlan::class;

    public function __construct(public string $tenantId)
    {
        parent::__construct();
    }

    public function processRow($row): void
    {
        MembershipPlan::query()->create([
            'gym_id' => $this->tenantId,
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
