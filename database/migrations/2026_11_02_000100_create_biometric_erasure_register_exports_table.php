<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable, non-tenant-scoped copy of a gym's outstanding erasure register.
 *
 * The owner-purge command cannot both refuse a gym delete on an outstanding obligation
 * and ever complete: retiring the terminals is what creates the obligation. It now
 * exports the register here (deliberately no foreign key, so the tenant cascade cannot
 * destroy it), writes one audit entry, and then deletes the tenant under that audited
 * override. An operator or auditor can reconstruct what erasures were outstanding at the
 * moment of deletion from this table, even though the tenant rows are gone.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biometric_erasure_register_exports')) {
            return;
        }

        Schema::create('biometric_erasure_register_exports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->string('gym_name')->nullable();
            $table->unsignedInteger('obligation_count')->default(0);
            $table->json('obligations');
            $table->timestamp('exported_at');
            $table->timestamps();

            $table->index('gym_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_erasure_register_exports');
    }
};
