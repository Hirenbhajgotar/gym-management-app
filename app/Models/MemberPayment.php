<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MemberPayment extends Model
{
    use LogsActivity, HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'membership_plan_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'notes',
        'payment_date',
        'membership_start',
        'membership_end',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
        'status' => PaymentStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('member_payment')
            ->logOnly(['member.name', 'membershipPlan.name', 'amount', 'currency', 'payment_method', 'status', 'notes', 'payment_date', 'membership_start', 'membership_end'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $payment = Number::currency($this->amount, in: $this->currency);

        return match ($eventName) {
            'created' => "Payment of {$payment} received from {$this->member?->name} at {$this->member?->gym?->name}",
            'updated' => "Payment of {$payment} updated for {$this->member?->name} at {$this->member?->gym?->name}",
            'deleted' => "Payment of {$payment} deleted for {$this->member?->name} at {$this->member?->gym?->name}",
            default => "Payment {$eventName} for {$this->member?->name} at {$this->member?->gym?->name}",
        };
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
