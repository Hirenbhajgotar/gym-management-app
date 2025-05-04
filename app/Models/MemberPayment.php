<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MemberPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('member_payment')
            ->logOnly(['member.name', 'membershipPlan.name', 'amount', 'currency', 'payment_method', 'status', 'notes', 'payment_date'])
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

    public function member()
    {
        return $this->belongsTo(GymMember::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
