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

        $caseColumns = [
            'leave_start_date' => fn (Blueprint $table) => $table->date('leave_start_date')->nullable(),
            'expected_delivery_date' => fn (Blueprint $table) => $table->date('expected_delivery_date')->nullable(),
            'delivery_date' => fn (Blueprint $table) => $table->date('delivery_date')->nullable(),
            'certificate_path' => fn (Blueprint $table) => $table->string('certificate_path', 500)->nullable(),
            'certificate_original_name' => fn (Blueprint $table) => $table->string('certificate_original_name', 255)->nullable(),
            'document_uploaded_at' => fn (Blueprint $table) => $table->timestamp('document_uploaded_at')->nullable(),
            'status' => fn (Blueprint $table) => $table->string('status', 20)->default('pending'),
            'verified_at' => fn (Blueprint $table) => $table->timestamp('verified_at')->nullable(),
            'verified_by' => fn (Blueprint $table) => $table->unsignedBigInteger('verified_by')->nullable(),
            'released_at' => fn (Blueprint $table) => $table->timestamp('released_at')->nullable(),
            'rejection_reason' => fn (Blueprint $table) => $table->text('rejection_reason')->nullable(),
            'created_by' => fn (Blueprint $table) => $table->unsignedBigInteger('created_by')->nullable(),
            'attendance_snapshots' => fn (Blueprint $table) => $table->json('attendance_snapshots')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($caseColumns as $column => $definition) {
            if (!Schema::hasColumn('maternity_leave_cases', $column)) {
                Schema::table('maternity_leave_cases', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }

        // Upgrade the older P7 naming without deleting its existing records.
        if (
            Schema::hasColumn('maternity_leave_cases', 'cm_start_date')
            && Schema::hasColumn('maternity_leave_cases', 'leave_start_date')
        ) {
            DB::table('maternity_leave_cases')
                ->whereNull('leave_start_date')
                ->update(['leave_start_date' => DB::raw('cm_start_date')]);
        }

        if (
            Schema::hasColumn('maternity_leave_cases', 'certificate_submitted_at')
            && Schema::hasColumn('maternity_leave_cases', 'document_uploaded_at')
        ) {
            DB::table('maternity_leave_cases')
                ->whereNull('document_uploaded_at')
                ->whereNotNull('certificate_submitted_at')
                ->update(['document_uploaded_at' => DB::raw('certificate_submitted_at')]);
        }

        if (Schema::hasTable('payrolls')) {
            $payrollColumns = [
                'maternity_case_id' => fn (Blueprint $table) => $table->unsignedBigInteger('maternity_case_id')->nullable(),
                'maternity_hold_days' => fn (Blueprint $table) => $table->decimal('maternity_hold_days', 5, 1)->default(0),
                'maternity_hold_amount' => fn (Blueprint $table) => $table->decimal('maternity_hold_amount', 15, 2)->default(0),
                'maternity_release_amount' => fn (Blueprint $table) => $table->decimal('maternity_release_amount', 15, 2)->default(0),
            ];

            foreach ($payrollColumns as $column => $definition) {
                if (!Schema::hasColumn('payrolls', $column)) {
                    Schema::table('payrolls', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            }

            if (
                Schema::hasColumn('payrolls', 'maternity_leave_case_id')
                && Schema::hasColumn('payrolls', 'maternity_case_id')
            ) {
                DB::table('payrolls')
                    ->whereNull('maternity_case_id')
                    ->whereNotNull('maternity_leave_case_id')
                    ->update(['maternity_case_id' => DB::raw('maternity_leave_case_id')]);
            }

            if (
                Schema::hasColumn('payrolls', 'maternity_leave_hold_days')
                && Schema::hasColumn('payrolls', 'maternity_hold_days')
            ) {
                DB::table('payrolls')
                    ->where('maternity_hold_days', 0)
                    ->update(['maternity_hold_days' => DB::raw('maternity_leave_hold_days')]);
            }

            if (
                Schema::hasColumn('payrolls', 'maternity_leave_hold')
                && Schema::hasColumn('payrolls', 'maternity_hold_amount')
            ) {
                DB::table('payrolls')
                    ->where('maternity_hold_amount', 0)
                    ->update(['maternity_hold_amount' => DB::raw('maternity_leave_hold')]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: this migration only reconciles legacy P7 schemas.
    }
};
