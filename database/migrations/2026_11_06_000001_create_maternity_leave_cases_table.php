<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maternity_leave_cases')) {
            return;
        }

        Schema::create('maternity_leave_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('leave_start_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('certificate_path', 500)->nullable();
            $table->string('certificate_original_name', 255)->nullable();
            $table->timestamp('document_uploaded_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('attendance_snapshots')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status'], 'maternity_employee_status_idx');
            $table->index(['leave_start_date', 'delivery_date'], 'maternity_dates_idx');
            $table->foreign('employee_id', 'maternity_employee_fk')
                ->references('id_employee')
                ->on('employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Non-destructive: this table may have existed from an earlier P7 rollout.
    }
};
