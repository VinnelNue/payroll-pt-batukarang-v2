<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');

            $table->date('attendance_date');

            $table->string('status', 10)->nullable();

            $table->string('source', 30)->default('fingerprint');

            $table->timestamps();

            /*
             * Satu employee hanya boleh memiliki
             * satu record absensi pada satu tanggal.
             */
            $table->unique(
                ['employee_id', 'attendance_date'],
                'attendance_employee_date_unique'
            );

            $table->index(
                'attendance_date',
                'attendance_date_index'
            );

            /*
             * employees menggunakan:
             * primary key = id_employee
             */
            $table->foreign('employee_id')
                ->references('id_employee')
                ->on('employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};