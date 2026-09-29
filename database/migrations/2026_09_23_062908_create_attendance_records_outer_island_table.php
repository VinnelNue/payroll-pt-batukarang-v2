<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records_outer_island', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_outer_island_id');

            $table->date('attendance_date');

            $table->string('status', 10)->nullable();

            /*
             * Source:
             * - system    = dibuat otomatis oleh sistem
             * - manual    = diubah/dimasukkan HR/Admin
             */
            $table->string('source', 30)->default('system');

            $table->timestamps();

            $table->foreign('employee_outer_island_id')
                ->references('id_employee_outer_island')
                ->on('employees_outer_island')
                ->cascadeOnDelete();

            /*
             * Satu karyawan hanya boleh mempunyai
             * satu attendance record pada tanggal yang sama.
             */
            $table->unique(
                ['employee_outer_island_id', 'attendance_date'],
                'att_outer_emp_date_unique'
            );

            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records_outer_island');
    }
};