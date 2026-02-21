<?php

namespace App\Events;

use App\Models\WabaMigration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantWabaMigrationStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WabaMigration $migration
    ) {}
}
