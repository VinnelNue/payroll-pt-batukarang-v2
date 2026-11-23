<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_logs', function (Blueprint $table): void {
            $table->id();

            // Nullable agar scan mentah tetap tersimpan walaupun NIK/PIN belum
            // berhasil dipasangkan dengan karyawan / contract history.
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('contract_history_id')->nullable();

            $table->string('fingerprint_pin', 50)->nullable();
            $table->string('nik', 50)->nullable();
            $table->dateTime('scan_at');

            $table->string('device_sn', 100)->nullable();
            $table->string('device_name', 150)->nullable();
            $table->string('verification', 50)->nullable();
            $table->string('mode', 80)->nullable();
            $table->string('mode_update', 80)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('job_position', 100)->nullable();
            $table->string('source_file', 255)->nullable();

            // Hash menjadikan import ulang file yang sama idempotent.
            $table->char('log_hash', 64)->unique();

            $table->timestamps();

            $table->index(['employee_id', 'scan_at'], 'fingerprint_logs_employee_scan_idx');
            $table->index(['contract_history_id', 'scan_at'], 'fingerprint_logs_history_scan_idx');
            $table->index(['fingerprint_pin', 'scan_at'], 'fingerprint_logs_pin_scan_idx');
            $table->index(['nik', 'scan_at'], 'fingerprint_logs_nik_scan_idx');
            $table->index(['department', 'scan_at'], 'fingerprint_logs_department_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_logs');
    }
};
