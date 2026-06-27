<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GymMember;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MembersExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?int $gymId;

    public function __construct(?int $gymId = null)
    {
        $this->gymId = $gymId;
    }

    public function collection()
    {
        $query = GymMember::with(['gym', 'membershipPlan']);

        if ($this->gymId) {
            $query->where('gym_id', $this->gymId);
        } elseif (auth()->user()->hasRole('gym_owner')) {
            $query->whereIn('gym_id', auth()->user()->gyms()->pluck('id'));
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Address',
            'DOB',
            'Gym',
            'Membership Plan',
            'Membership Start',
            'Membership End',
            'Payment Status',
        ];
    }

    public function map($member): array
    {
        return [
            $member->name,
            $member->email,
            $member->phone,
            $member->address,
            $member->dob?->format('Y-m-d'),
            $member->gym?->name,
            $member->membershipPlan?->name,
            $member->membership_start?->format('Y-m-d'),
            $member->membership_end?->format('Y-m-d'),
            $member->payment_status,
        ];
    }
}
