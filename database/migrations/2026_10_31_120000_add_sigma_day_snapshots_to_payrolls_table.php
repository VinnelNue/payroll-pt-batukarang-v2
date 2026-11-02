<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (!Schema::hasColumn('payrolls', 'standard_work_days')) {
                $table->unsignedTinyInteger('standard_work_days')->nullable()->after('cutoff_day');
            }

            if (!Schema::hasColumn('payrolls', 'paid_days')) {
                $table->decimal('paid_days', 8, 1)->default(0)->after('work_days');
            }

            if (!Schema::hasColumn('payrolls', 'unpaid_days')) {
                $table->decimal('unpaid_days', 8, 1)->default(0)->after('paid_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            foreach (['standard_work_days', 'paid_days', 'unpaid_days'] as $column) {
                if (Schema::hasColumn('payrolls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
