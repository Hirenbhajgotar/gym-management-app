<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('waba_setup_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');

            $table->string('session_id')->unique();
            $table->string('status')->default('pending');

            $table->text('meta_response')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waba_setup_sessions');
    }
};
