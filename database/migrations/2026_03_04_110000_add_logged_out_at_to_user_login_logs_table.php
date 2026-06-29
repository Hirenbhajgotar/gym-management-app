<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->timestamp('logged_out_at')->nullable()->after('login_at');
            $table->boolean('is_current_session')->default(false)->after('logged_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->dropColumn(['logged_out_at', 'is_current_session']);
        });
    }
};
