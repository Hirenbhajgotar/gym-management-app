<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waba_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_account_id')->constrained()->onDelete('cascade');

            $table->string('status')->default('pending');

            $table->string('old_waba_id')->nullable();
            $table->string('old_waba_mode')->nullable();
            $table->string('new_waba_id')->nullable();
            $table->string('new_waba_mode')->nullable();

            $table->text('migration_notes')->nullable();
            $table->json('migration_data')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waba_migrations');
    }
};
