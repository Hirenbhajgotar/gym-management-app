<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->decimal('pt_salary_per_member', 10, 2)
                ->default(10.00)
                ->nullable()
                ->comment('Percentage commission rate (e.g., 10.00 = 10%)')
                ->change();
        });

        DB::statement('UPDATE trainers SET pt_salary_per_member = 10.00 WHERE pt_salary_per_member IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->decimal('pt_salary_per_member', 10, 2)
                ->default(0.00)
                ->nullable()
                ->change();
        });
    }
};
