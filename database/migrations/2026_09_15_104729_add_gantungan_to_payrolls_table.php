<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('gantungan_days', 4, 1)->default(0.0)->after('unpaid_leave');
            $table->decimal('gantungan_deduction', 15, 2)->default(0.00)->after('other_deductions');
            $table->decimal('previous_gantungan_deduction', 15, 2)->default(0.00)->after('gantungan_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'gantungan_days',
                'gantungan_deduction',
                'previous_gantungan_deduction'
            ]);
        });
    }
};