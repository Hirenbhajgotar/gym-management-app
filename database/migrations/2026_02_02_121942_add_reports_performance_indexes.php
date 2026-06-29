<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Performance indexes for Reports & Analytics module.
     * These indexes optimize queries used in report generation.
     */
    public function up(): void
    {
        // Helper to check if index exists
        $indexExists = fn (string $table, string $index) => collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $index);

        // GymMembers table indexes for member reports
        Schema::table('gym_members', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('gym_members', 'idx_gym_members_membership_status')) {
                $table->index(['gym_id', 'membership_end'], 'idx_gym_members_membership_status');
            }
            if (! $indexExists('gym_members', 'idx_gym_members_created')) {
                $table->index(['gym_id', 'created_at'], 'idx_gym_members_created');
            }
            if (! $indexExists('gym_members', 'idx_gym_members_trainer')) {
                $table->index(['gym_id', 'trainer_id'], 'idx_gym_members_trainer');
            }
            if (! $indexExists('gym_members', 'idx_gym_members_plan')) {
                $table->index(['gym_id', 'gym_membership_plan_id'], 'idx_gym_members_plan');
            }
        });

        // MemberPayments table indexes for revenue reports
        Schema::table('member_payments', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('member_payments', 'idx_payments_revenue')) {
                $table->index(['gym_id', 'status', 'payment_date'], 'idx_payments_revenue');
            }
            if (! $indexExists('member_payments', 'idx_payments_plan_revenue')) {
                $table->index(['gym_id', 'membership_plan_id', 'status'], 'idx_payments_plan_revenue');
            }
            if (! $indexExists('member_payments', 'idx_payments_method')) {
                $table->index(['gym_id', 'payment_method', 'status'], 'idx_payments_method');
            }
        });

        // Leads table indexes for lead reports
        Schema::table('leads', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('leads', 'idx_leads_funnel')) {
                $table->index(['gym_id', 'status', 'created_at'], 'idx_leads_funnel');
            }
            if (! $indexExists('leads', 'idx_leads_source')) {
                $table->index(['gym_id', 'source', 'created_at'], 'idx_leads_source');
            }
            if (! $indexExists('leads', 'idx_leads_conversion')) {
                $table->index(['gym_id', 'status', 'updated_at'], 'idx_leads_conversion');
            }
        });

        // Attendances table indexes for attendance reports
        Schema::table('attendances', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('attendances', 'idx_attendances_trend')) {
                $table->index(['gym_id', 'check_in_time'], 'idx_attendances_trend');
            }
            if (! $indexExists('attendances', 'idx_attendances_status')) {
                $table->index(['gym_id', 'status', 'check_in_time'], 'idx_attendances_status');
            }
        });

        // Expenses table indexes for expense reports
        Schema::table('expenses', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('expenses', 'idx_expenses_report')) {
                $table->index(['gym_id', 'status', 'expense_date'], 'idx_expenses_report');
            }
            if (! $indexExists('expenses', 'idx_expenses_category')) {
                $table->index(['gym_id', 'expense_category_id', 'status'], 'idx_expenses_category');
            }
            if (! $indexExists('expenses', 'idx_expenses_payment_method')) {
                $table->index(['gym_id', 'payment_method', 'status'], 'idx_expenses_payment_method');
            }
        });

        // Trainers table index
        Schema::table('trainers', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists('trainers', 'idx_trainers_available')) {
                $table->index(['gym_id', 'is_available'], 'idx_trainers_available');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropIndex('idx_gym_members_membership_status');
            $table->dropIndex('idx_gym_members_created');
            $table->dropIndex('idx_gym_members_trainer');
            $table->dropIndex('idx_gym_members_plan');
        });

        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_revenue');
            $table->dropIndex('idx_payments_plan_revenue');
            $table->dropIndex('idx_payments_method');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_funnel');
            $table->dropIndex('idx_leads_source');
            $table->dropIndex('idx_leads_conversion');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_trend');
            $table->dropIndex('idx_attendances_status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_report');
            $table->dropIndex('idx_expenses_category');
            $table->dropIndex('idx_expenses_payment_method');
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->dropIndex('idx_trainers_available');
        });
    }
};
