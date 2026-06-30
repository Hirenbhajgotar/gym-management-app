<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('specialization')->nullable();
            $table->integer('experience_years')->nullable();
            $table->text('certifications')->nullable();
            $table->boolean('is_available')->default(true)->nullable();
            $table->text('available_hours')->nullable();
            // $table->boolean('status')->default(true)->comment('1=Active, 0=Inactive');
            $table->decimal('basic_salary', 10, 2)->default(0.00)->nullable();
            $table->decimal('pt_salary_per_member', 10, 2)->default(0.00)->nullable();
            $table->string('currency')->default('INR')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('is_available');
            $table->index('basic_salary');
            $table->index('pt_salary_per_member');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
