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
        Schema::table('checkout_sessions', function (Blueprint $table) {
            // Persisted Razorpay order id so the checkout page can be loaded
            // (and refreshed) without creating a new order / hitting the API
            // on every request.
            $table->string('razorpay_order_id')->nullable()->index()->after('status');
            $table->decimal('razorpay_order_amount', 10, 2)->nullable()->after('razorpay_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn(['razorpay_order_id', 'razorpay_order_amount']);
        });
    }
};
