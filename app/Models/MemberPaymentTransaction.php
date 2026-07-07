<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\MemberPaymentTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $member_payment_id
 * @property numeric|null $amount
 * @property Currency|null $currency
 * @property string|null $payment_method
 * @property Carbon|null $transaction_date
 * @property string|null $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static MemberPaymentTransactionFactory factory($count = null, $state = [])
 */
class MemberPaymentTransaction extends Model
{
    /** @use HasFactory<MemberPaymentTransactionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_payment_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_date',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'currency' => Currency::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('member_payment_transaction')
            ->logOnly(['amount', 'currency', 'payment_method', 'transaction_date', 'status', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<MemberPayment, $this>
     */
    public function memberPayment(): BelongsTo
    {
        return $this->belongsTo(MemberPayment::class);
    }
}
