<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PayrollController uses updateOrCreate(employee_id, period_month).
        // Enforce that invariant so concurrent saves cannot create duplicate monthly payrolls.
        $duplicatePayroll = DB::table('payrolls')
            ->select('employee_id', 'period_month')
            ->groupBy('employee_id', 'period_month')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicatePayroll) {
            throw new \RuntimeException(
                'Migration CM dihentikan: payroll duplikat ditemukan untuk employee_id '
                . $duplicatePayroll->employee_id
                . ' periode '
                . $duplicatePayroll->period_month
                . '. Gabungkan/hapus duplikat dummy terlebih dahulu, lalu jalankan migrate lagi.'
            );
        }

        Schema::create('maternity_leave_settlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maternity_case_id')->unique();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('status', 30)->default('pending')->index(); // pending, paid, no_balance
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('pph21_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->char('tax_period', 7)->nullable()->index();
            $table->decimal('taxable_gross_basis', 15, 2)->default(0);
            $table->decimal('ter_rate', 8, 4)->nullable();
            $table->timestamp('tax_calculated_at')->nullable();
            $table->date('planned_payment_date')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('transfer_reference', 120)->nullable();
            $table->string('proof_path', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('maternity_case_id')
                ->references('id')->on('maternity_leave_cases')
                ->cascadeOnDelete();
            $table->foreign('employee_id')
                ->references('id_employee')->on('employees')
                ->cascadeOnDelete();
            $table->foreign('paid_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        Schema::create('maternity_leave_accruals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maternity_case_id');
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('attendance_date');
            $table->unsignedBigInteger('contract_history_id')->nullable()->index();
            // payroll_id = original payroll that held this CM day.
            // offset_payroll_id = later payroll which excludes a pre-payroll
            // settlement accrual from normal pay, avoiding duplicate payout.
            $table->unsignedBigInteger('payroll_id')->nullable()->index();
            $table->unsignedBigInteger('offset_payroll_id')->nullable()->index();
            $table->unsignedTinyInteger('standard_work_days');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('settlement_id')->nullable()->index();
            $table->string('status', 30)->default('accrued')->index(); // accrued, settlement_pending, paid
            $table->timestamps();

            $table->unique(['maternity_case_id', 'attendance_date'], 'maternity_accrual_case_date_unique');
            $table->index(['employee_id', 'attendance_date'], 'maternity_accrual_employee_date_idx');
            $table->foreign('maternity_case_id')
                ->references('id')->on('maternity_leave_cases')
                ->cascadeOnDelete();
            $table->foreign('employee_id')
                ->references('id_employee')->on('employees')
                ->cascadeOnDelete();
            $table->foreign('contract_history_id')
                ->references('id_contract_history')->on('employee_contract_histories')
                ->nullOnDelete();
            $table->foreign('payroll_id')
                ->references('id_payroll')->on('payrolls')
                ->nullOnDelete();
            $table->foreign('offset_payroll_id')
                ->references('id_payroll')->on('payrolls')
                ->nullOnDelete();
            $table->foreign('settlement_id')
                ->references('id')->on('maternity_leave_settlements')
                ->nullOnDelete();
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->decimal('maternity_settled_days', 5, 1)->default(0)->after('maternity_hold_days');
            $table->decimal('maternity_settled_amount', 15, 2)->default(0)->after('maternity_hold_amount');
            $table->unique(['employee_id', 'period_month'], 'payroll_employee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropUnique('payroll_employee_period_unique');

            if (Schema::hasColumn('payrolls', 'maternity_settled_amount')) {
                $table->dropColumn('maternity_settled_amount');
            }
            if (Schema::hasColumn('payrolls', 'maternity_settled_days')) {
                $table->dropColumn('maternity_settled_days');
            }
        });

        Schema::dropIfExists('maternity_leave_accruals');
        Schema::dropIfExists('maternity_leave_settlements');
    }
};
