<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_message_credit_id')->nullable()->constrained('whatsapp_message_credits')->onDelete('cascade');
            $table->foreignId('whatsapp_package_id')->nullable()->constrained('whatsapp_packages')->onDelete('set null');

            $table->string('type')->nullable();
            $table->integer('credits')->nullable();
            $table->integer('balance_before')->nullable();
            $table->integer('balance_after')->nullable();

            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('admin_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_credit_transactions');
    }
};
