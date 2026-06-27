<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->after('is_active'); // Default to 0 (Inactive) temporarily
        });

        // Migrate existing data
        DB::table('users')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                // is_active: 1 -> status: 1 (Active)
                // is_active: 0 -> status: 0 (Inactive)
                $status = $user->is_active ? 1 : 0;
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['status' => $status]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse data migration
        // \Illuminate\Support\Facades\DB::table('users')->chunkById(100, function ($users) {
        //     foreach ($users as $user) {
        //         // status: 1 -> is_active: 1
        //         // status: 0 -> is_active: 0
        //         // status: 2 (Under Review) -> is_active: 0 (Inactive)
        //         $isActive = $user->status === 1;
        //         \Illuminate\Support\Facades\DB::table('users')
        //             ->where('id', $user->id)
        //             ->update(['is_active' => $isActive]);
        //     }
        // });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
