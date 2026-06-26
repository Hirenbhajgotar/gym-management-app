<?php

namespace App\Imports;

use App\Models\Trainer;
use App\Models\Gym;
use Illuminate\Support\Facades\Date;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Helpers\DateHelper;
use App\Helpers\Number;
use App\Models\City;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Services\AvatarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class GymMemberImport extends BaseImporter implements ShouldQueue, WithColumnFormatting
{
    protected static ?string $model = GymMember::class;

    protected array $dateColumns = [
        'dob',
        'membership_start',
    ];

    public function __construct(public string $tenantId)
    {
        parent::__construct();
    }

    public function columnFormats(): array
    {
        return [
            'dob' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'membership_start' => NumberFormat::FORMAT_DATE_YYYYMMDD,
        ];
    }

    public function processRow($row): void
    {
        $membershipPlan = null;
        if (isset($row['membership_plan'])) {
            $membershipPlan = MembershipPlan::query()->where('name', $row['membership_plan'])->first();

            if (! $membershipPlan) {
                throw ValidationException::withMessages(['membership_plan' => 'Membership plan not found.']);
            }
        }

        $trainer = null;
        if (isset($row['trainer_email'])) {
            $trainer = Trainer::query()->where('email', $row['trainer_email'])->first();
        }

        if (isset($row['dob'])) {
            $row['age'] = DateHelper::calculateAge($row['dob']);
        }

        if (isset($row['phone'])) {
            $row['phone'] = Number::clearPhoneNumberFormat($row['phone']);
        }

        $profileImagePath = null;
        if (isset($row['name'])) {
            $profileImagePath = resolve(AvatarService::class)->generate($row['name']);
        }

        $gymMember = GymMember::query()->create([
            'gym_id' => $this->tenantId,
            'name' => $row['name'],
            'profile_photo' => $profileImagePath,
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'weight' => $row['weight'] ?? null,
            'height' => $row['height'] ?? null,
            'age' => $row['age'] ?? null,
            'dob' => $row['dob'] ?? null,
            'membership_start' => $row['membership_start'] ?? now(),
            'payment_status' => $row['payment_status'] ?? PaymentStatus::PENDING,
            'gym_membership_plan_id' => $membershipPlan->id ?? null,
            'trainer_id' => $trainer?->id ?? null,
            'user_id' => Gym::query()->find($this->tenantId)?->owner()->id ?? null,
        ]);

        if ($membershipPlan) {
            $membershipStart = $row['membership_start'] ? Date::parse($row['membership_start']) : now();
            $membershipEnd = $membershipStart->copy()->addMonths($membershipPlan->duration);

            $gymMember->payments()->create([
                'gym_id' => $this->tenantId,
                'membership_plan_id' => $membershipPlan->id,
                'amount' => $membershipPlan->price,
                'currency' => $membershipPlan->currency ?? Currency::INR,
                'payment_method' => PaymentMethod::CASH,
                'status' => $row['payment_status'] ?? PaymentStatus::PENDING,
                'notes' => 'Imported via Excel',
                'payment_date' => now(),
                'membership_start' => $membershipStart,
                'membership_end' => $membershipEnd,
            ]);
        }

        if (isset($row['address'])) {
            $city = City::with('state')->where('slug', Str::slug($row['city']))->first();

            $gymMember->address()->create(['street' => $row['address'], 'city' => $city?->id ?? null, 'state' => $city?->state_id ?? null, 'country' => $city?->state?->country_id ?? null, 'postal_code' => $row['zip_code'] ?? null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:gym_members,email'],
            'phone' => ['nullable', 'max:20'],
            'address' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'age' => ['nullable', 'integer'],
            'dob' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'exists:cities,slug'],
            'zip_code' => ['nullable', 'max:20'],
            'membership_start' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', Rule::enum(PaymentStatus::class)],
            'membership_plan' => ['nullable', 'string'],
            'trainer_email' => ['nullable', 'string', 'exists:trainers,email'],
        ];
    }
}
