<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("request_contacts", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("email");
            $table->string("phone");
            $table->string("company_name")->nullable();
            $table
                ->integer("gym_size")
                ->nullable()
                ->comment("Number of members or size");
            $table->text("message")->nullable();
            $table->string("ip_address")->nullable();
            $table->string("user_agent")->nullable();
            $table->boolean("is_contacted")->default(false);
            $table->timestamp("contacted_at")->nullable();
            $table->text("admin_notes")->nullable();
            $table->timestamps();

            $table->index("email");
            $table->index("phone");
            $table->index("is_contacted");
            $table->index("created_at");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("request_contacts");
    }
};
