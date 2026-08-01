<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id('id_payroll');
            $table->foreignId('employee_id')->constrained('employees', 'id_employee')->onDelete('cascade');
            $table->string('period_month', 7); // Format: 2026-07
            
            // Data Kehadiran
            $table->decimal('work_days', 4, 1)->default(27);
            $table->decimal('unpaid_leave', 4, 1)->default(0);
            $table->decimal('overtime_hours', 5, 1)->default(0);

            // Rincian Pendapatan & Potongan
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('overtime_pay', 15, 2)->default(0);
            $table->decimal('incentive', 15, 2)->default(0);
            $table->decimal('cash_advance', 15, 2)->default(0);
            
            // Potongan Pajak & BPJS
            $table->decimal('bpjs_tk_deduction', 15, 2)->default(0);
            $table->decimal('bpjs_ks_deduction', 15, 2)->default(0);
            $table->decimal('pph21_deduction', 15, 2)->default(0);

            // Gaji Kotor & Netto
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            
            $table->enum('status', ['Draft', 'Approved', 'Paid'])->default('Approved');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};