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
        Schema::create('business_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->onDelete('cascade');
            $table->foreignId('business_type_id')->nullable()->constrained('business_types')->onDelete('cascade');
            $table->string('number_of_locations')->nullable();
            $table->string('years_in_business')->nullable();
            $table->string('website')->nullable();
            $table->string('gym_size')->nullable();
            $table->text('business_description')->nullable();
            $table->text('special_requirements')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_details');
    }
};
