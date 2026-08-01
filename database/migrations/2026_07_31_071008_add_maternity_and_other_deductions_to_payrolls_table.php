<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('maternity_leave_pay', 15, 2)->default(0)->after('overtime_pay');
            $table->decimal('other_deductions', 15, 2)->default(0)->after('cash_advance');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['maternity_leave_pay', 'other_deductions']);
        });
    }
};