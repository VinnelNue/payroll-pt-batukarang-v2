<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls_outer_island', function (Blueprint $table) {

            $table->id('id_payroll_outer_island');

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('employee_outer_island_id');

            $table->foreign('employee_outer_island_id')
                ->references('id_employee_outer_island')
                ->on('employees_outer_island')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | PERIOD
            |--------------------------------------------------------------------------
            */
            $table->string('period_month', 7);

            /*
             * Cutoff fleksibel 20–28.
             * Default mengikuti sistem Local: 26.
             */
            $table->unsignedTinyInteger('cutoff_day')->default(26);


            /*
            |--------------------------------------------------------------------------
            | ATTENDANCE SNAPSHOT
            |--------------------------------------------------------------------------
            |
            | Attendance sebenarnya tetap disimpan di
            | attendance_records_outer_island.
            |
            | daily_attendance di sini adalah snapshot
            | ketika payroll dihitung/disimpan.
            |
            */
            $table->json('daily_attendance')->nullable();

            $table->decimal('work_days', 4, 1)->default(26.0);

            $table->decimal('unpaid_leave', 4, 1)->default(0.0);

            /*
             * Hari setelah cutoff yang akan menjadi
             * gantungan periode berikutnya.
             */
            $table->decimal('gantungan_days', 4, 1)->default(0.0);


            /*
            |--------------------------------------------------------------------------
            | OVERTIME
            |--------------------------------------------------------------------------
            */
            $table->decimal('overtime_hours', 5, 1)->default(0.0);

            $table->decimal('basic_salary', 15, 2)->default(0);

            $table->decimal('allowance', 15, 2)->default(0);

            $table->decimal('overtime_pay', 15, 2)->default(0);

            $table->decimal('maternity_leave_pay', 15, 2)->default(0);

            $table->decimal('incentive', 15, 2)->default(0);


            /*
            |--------------------------------------------------------------------------
            | DEDUCTIONS
            |--------------------------------------------------------------------------
            */
            $table->decimal('cash_advance', 15, 2)->default(0);

            $table->decimal('other_deductions', 15, 2)->default(0);

            /*
             * Gantungan periode sekarang.
             */
            $table->decimal('gantungan_deduction', 15, 2)->default(0);

            /*
             * Gantungan dari periode sebelumnya.
             */
            $table->decimal('previous_gantungan_deduction', 15, 2)->default(0);


            /*
            |--------------------------------------------------------------------------
            | BPJS
            |--------------------------------------------------------------------------
            */
            $table->decimal('bpjs_tk_deduction', 15, 2)->default(0);

            $table->decimal('bpjs_ks_deduction', 15, 2)->default(0);

            /*
             * true = memakai nominal manual dari contract.
             * false = memakai perhitungan otomatis.
             */
            $table->boolean('is_bpjs_override')->default(false);


            /*
            |--------------------------------------------------------------------------
            | PPH 21
            |--------------------------------------------------------------------------
            */
            $table->decimal('pph21_deduction', 15, 2)->default(0);


            /*
            |--------------------------------------------------------------------------
            | TOTAL PAYROLL
            |--------------------------------------------------------------------------
            */
            $table->decimal('gross_salary', 15, 2)->default(0);

            $table->decimal('net_salary', 15, 2)->default(0);


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'Draft',
                'Approved',
                'Paid',
            ])->default('Approved');


            /*
            |--------------------------------------------------------------------------
            | LOCK PAYROLL
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_locked')->default(false);

            $table->timestamp('locked_at')->nullable();

            $table->unsignedBigInteger('locked_by')->nullable();


            /*
            |--------------------------------------------------------------------------
            | UNLOCK REQUEST
            |--------------------------------------------------------------------------
            */
            $table->boolean('unlock_requested')->default(false);

            $table->text('unlock_reason')->nullable();

            $table->unsignedBigInteger('requested_by')->nullable();


            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */
            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | INDEX
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['employee_outer_island_id', 'period_month'],
                'payroll_outer_emp_period_idx'
            );

            $table->index(
                'period_month',
                'payroll_outer_period_idx'
            );

            $table->index(
                'locked_by',
                'payroll_outer_locked_by_idx'
            );

            $table->index(
                'requested_by',
                'payroll_outer_requested_by_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls_outer_island');
    }
};