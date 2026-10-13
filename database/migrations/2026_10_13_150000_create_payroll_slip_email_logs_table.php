<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_slip_email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->index();
            $table->unsignedBigInteger('employee_outer_island_id')->index();
            $table->char('period_month', 7)->index();
            $table->string('employee_name', 255);
            $table->string('recipient_email')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['period_month', 'employee_outer_island_id', 'status'],
                'payroll_slip_email_logs_period_employee_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slip_email_logs');
    }
};
