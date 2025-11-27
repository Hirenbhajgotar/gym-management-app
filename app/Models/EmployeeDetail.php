<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $date_of_birth
 * @property GenderEnum|null $gender
 * @property MaritalStatusEnum|null $marital_status
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_email
 * @property string|null $employee_id
 * @property string|null $hire_date
 * @property string|null $employment_type
 * @property string|null $monthly_salary
 * @property array<array-key, mixed>|null $skills
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmergencyContactRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereEmploymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereHireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereMaritalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereMonthlySalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereSkills($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeDetail whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperEmployeeDetail
 */
class EmployeeDetail extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'marital_status',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_email',
        'employee_id',
        'hire_date',
        'employment_type',
        'monthly_salary',
        'skills',
        'notes',
    ];

    protected $casts = [
        'skills' => 'array',
        'gender' => GenderEnum::class,
        'marital_status' => MaritalStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
