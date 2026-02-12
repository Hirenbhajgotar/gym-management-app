<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReportFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'name',
        'report_type',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    public function forUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function forReport($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}
