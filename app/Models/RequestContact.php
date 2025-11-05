<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestContact extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "email",
        "phone",
        "company_name",
        "job_title",
        "gym_size",
        "message",
        "preferred_contact_method",
        "best_time_to_contact",
        "ip_address",
        "user_agent",
        "is_contacted",
        "contacted_at",
        "admin_notes",
    ];

    protected function casts(): array
    {
        return [
            "gym_size" => "integer",
            "is_contacted" => "boolean",
            "contacted_at" => "datetime",
            "created_at" => "datetime",
            "updated_at" => "datetime",
        ];
    }

    /**
     * Scope for uncontacted requests
     */
    #[Scope]
    public function uncontacted($query)
    {
        return $query->where("is_contacted", false);
    }

    /**
     * Scope for contacted requests
     */
    #[Scope]
    public function contacted($query)
    {
        return $query->where("is_contacted", true);
    }

    /**
     * Scope for recent requests (last 30 days)
     */
    #[Scope]
    public function recent($query)
    {
        return $query->where("created_at", ">=", now()->subDays(30));
    }

    /**
     * Mark as contacted
     */
    public function markAsContacted(?string $notes = null): void
    {
        $this->update([
            "is_contacted" => true,
            "contacted_at" => now(),
            "admin_notes" => $notes ?? $this->admin_notes,
        ]);
    }
}
