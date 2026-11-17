<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payrolls', 'maternity_case_id')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->unsignedBigInteger('maternity_case_id')->nullable()->after('maternity_leave_pay');
                $table->index('maternity_case_id', 'payroll_maternity_case_idx');
            });
        }

        if (!Schema::hasColumn('payrolls', 'maternity_hold_days')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->decimal('maternity_hold_days', 5, 1)->default(0)->after('maternity_case_id');
            });
        }

        if (!Schema::hasColumn('payrolls', 'maternity_hold_amount')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->decimal('maternity_hold_amount', 15, 2)->default(0)->after('maternity_hold_days');
            });
        }

        if (!Schema::hasColumn('payrolls', 'maternity_release_amount')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->decimal('maternity_release_amount', 15, 2)->default(0)->after('maternity_hold_amount');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: some columns may predate this reconciled P7 migration.
    }
};
