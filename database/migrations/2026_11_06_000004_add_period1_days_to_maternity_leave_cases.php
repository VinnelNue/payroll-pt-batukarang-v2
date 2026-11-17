<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maternity_leave_cases')) {
            return;
        }

        if (!Schema::hasColumn('maternity_leave_cases', 'period1_days')) {
            Schema::table('maternity_leave_cases', function (Blueprint $table) {
                $table->unsignedTinyInteger('period1_days')->nullable()->after('leave_start_date');
            });
        }

        // Preserve the schedule already assigned to legacy P7 cases.
        // Existing records retain their prior duration (delivery/expected date,
        // capped at 45 days); new records will receive the HRD-entered duration.
        DB::table('maternity_leave_cases')
            ->whereNull('period1_days')
            ->whereNotNull('leave_start_date')
            ->update([
                'period1_days' => DB::raw(
                    'GREATEST(1, LEAST(45, DATEDIFF(LEAST(COALESCE(delivery_date, expected_delivery_date, DATE_ADD(leave_start_date, INTERVAL 44 DAY)), DATE_ADD(leave_start_date, INTERVAL 44 DAY)), leave_start_date) + 1))'
                ),
            ]);
    }

    public function down(): void
    {
        // Non-destructive migration: keep the period duration data.
    }
};
