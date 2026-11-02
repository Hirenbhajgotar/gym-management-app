<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable release intent for a tenant's Twilio resources.
 *
 * Releasing external resources inside the `deleting` listener is wrong in both
 * directions: a later rollback cannot restore a released subaccount, and a failed release
 * is invisible because the local rows are deleted unconditionally. The identifier and the
 * encrypted subaccount token are captured here (no foreign key, so the tenant cascade
 * cannot destroy the intent), the release runs in a queued job after commit, and only a
 * successful release marks the row done. A failed job leaves the row for the recovery
 * sweep and the queue's retry/failed-job handling.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_account_releases')) {
            return;
        }

        Schema::create('whatsapp_account_releases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->string('twilio_account_sid')->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('messaging_service_sid')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('channel_sender_sid')->nullable();
            $table->string('incoming_phone_sid')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('gym_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_account_releases');
    }
};
