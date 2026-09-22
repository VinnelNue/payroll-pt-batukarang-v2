<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'cutoff_day')) {
                $table->unsignedTinyInteger('cutoff_day')
                    ->default(26)
                    ->after('period_month');
            }
        });

        // Database lama memakai default work_days = 27.0,
        // sedangkan aturan payroll yang dipakai sekarang menggunakan 26 hari sebagai divisor.
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('work_days', 4, 1)->default(26.0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'cutoff_day')) {
                $table->dropColumn('cutoff_day');
            }

            $table->decimal('work_days', 4, 1)->default(27.0)->change();
        });
    }
};
