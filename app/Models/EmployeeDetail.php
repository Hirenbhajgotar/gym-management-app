<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\MaritalStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
