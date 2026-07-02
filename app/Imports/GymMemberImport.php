<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Helpers\DateHelper;
use App\Helpers\ParserHelper;
use App\Models\City;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Services\AvatarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Date;
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
        'membership_end',
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
            'membership_end' => NumberFormat::FORMAT_DATE_YYYYMMDD,
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

        $phone = isset($row['phone']) ? (string) $row['phone'] : '';
        $row['phone'] = ParserHelper::formatPhoneNumberWithCountryCode($phone);

        $profileImagePath = null;
        if (isset($row['name'])) {
            $profileImagePath = resolve(AvatarService::class)->generate($row['name']);
        }

        $gymMember = GymMember::query()->create([
            'gym_id' => $this->tenantId,
            'name' => $row['name'],
            'profile_photo' => $profileImagePath,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'],
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
            $membershipStart = null;
            $membershipEnd = null;

            if (isset($row['membership_start']) && ! isset($row['membership_end'])) {
                $membershipStart = $row['membership_start']
                    ? Date::parse(
                        is_string($row['membership_start'])
                            ? $row['membership_start']
                            : throw ValidationException::withMessages(['membership_start' => 'Invalid membership start date format']))
                    : now();

                $membershipEnd = $membershipStart->copy()->addMonths($membershipPlan->duration ?? 0);
            } elseif (isset($row['membership_end']) && ! isset($row['membership_start'])) {
                $membershipEnd = Date::parse(
                    is_string($row['membership_end'])
                        ? $row['membership_end']
                        : throw ValidationException::withMessages(['membership_end' => 'Invalid membership end date format']));

                $membershipStart = $membershipEnd->copy()->subMonths($membershipPlan->duration ?? 0);
            } elseif (isset($row['membership_end']) && isset($row['membership_start'])) {
                $membershipStart = $row['membership_start']
                    ? Date::parse(
                        is_string($row['membership_start'])
                            ? $row['membership_start']
                            : throw ValidationException::withMessages(['membership_start' => 'Invalid membership start date format']))
                    : now();

                $membershipEnd = $row['membership_end']
                    ? Date::parse(
                        is_string($row['membership_end'])
                            ? $row['membership_end']
                            : throw ValidationException::withMessages(['membership_end' => 'Invalid membership end date format']))

                    : $membershipStart->copy()->addMonths($membershipPlan->duration ?? 0);
            }

            $payment = $gymMember->payments()->create([
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

            $amountPaid = $row['payment_amount_paid'] ?? $membershipPlan->price;
            $payment->transactions()->create([
                'member_payment_id' => $payment->id,
                'amount' => $amountPaid,
                'currency' => $membershipPlan->currency ?? Currency::INR,
                'payment_method' => PaymentMethod::CASH,
                'transaction_date' => now(),
                'status' => 'completed',
                'notes' => 'Imported via Excel',
            ]);

            $payment->syncStatusFromTransactions();

            $gymMember->update([
                'payment_status' => $payment->status?->value,
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
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:gym_members,email'],
            'phone' => ['required', 'max:20', Rule::unique('gym_members', 'phone')->where('gym_id', $this->tenantId)],
            'address' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'age' => ['nullable', 'integer'],
            'dob' => ['nullable', 'date'],
            'city' => ['nullable', 'string', 'exists:cities,slug'],
            'zip_code' => ['nullable', 'max:20'],
            'membership_start' => ['nullable', 'date'],
            'membership_end' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', Rule::enum(PaymentStatus::class)],
            'payment_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'membership_plan' => ['nullable', 'string'],
            'trainer_email' => ['nullable', 'string', 'exists:trainers,email'],
        ];
    }
}
