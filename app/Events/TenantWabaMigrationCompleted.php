<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\WabaMigration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantWabaMigrationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WabaMigration $migration
    ) {}
}
