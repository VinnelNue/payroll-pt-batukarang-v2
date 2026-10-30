<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table): void {
            // Mempercepat pencarian history berdasarkan identitas mesin fingerprint.
            $table->index(
                ['nik_fingerprint', 'start_date'],
                'emp_hist_nik_start_idx'
            );

            $table->index(
                ['fingerprint_pin', 'start_date'],
                'emp_hist_pin_start_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table): void {
            $table->dropIndex('emp_hist_nik_start_idx');
            $table->dropIndex('emp_hist_pin_start_idx');
        });
    }
};
