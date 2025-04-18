<?php

namespace App\Imports;

use App\Models\GymMember;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class GymMembersImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $gymId;

    public function __construct($gymId)
    {
        $this->gymId = $gymId;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $plan = MembershipPlan::where('gym_id', $this->gymId)
            ->where('name', $row['membership_plan_name'])
            ->first();

        if (!$plan) {
            return null; // Skip if plan not found
        }

        return new GymMember([
            'gym_id' => $this->gymId,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'dob' => $row['dob'] ? Carbon::parse($row['dob']) : null,
            'membership_start' => $row['membership_start'] ? Carbon::parse($row['membership_start']) : now(),
            'membership_plan_id' => $plan->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:gym_members,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:65535',
            'dob' => 'nullable|date',
            'membership_start' => 'nullable|date',
            'membership_plan_name' => 'required|string|exists:membership_plans,name',
        ];
    }
}
