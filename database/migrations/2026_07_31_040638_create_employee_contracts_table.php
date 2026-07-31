<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id('id_contract');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees', 'id_employee')->onDelete('cascade');
            
            // Jabatan, Kategori & Level (Sesuai Excel)
            $table->string('job_title'); // Contoh: Manager Regional Area
            $table->string('category', 10)->nullable(); // Contoh: A
            $table->integer('level')->nullable(); // Contoh: 21
            
            // Acuan Gaji & Tunjangan Tetap
            $table->decimal('basic_salary', 15, 2)->default(0); // GAPOK
            $table->decimal('allowance', 15, 2)->default(0); // TJ (Tunjangan)
            
            // Status Keaktifan BPJS
            $table->boolean('is_bpjstk_active')->default(true);
            $table->boolean('is_bpjs_health_active')->default(true);
            
            // Status Kontrak & Keaktifan
            $table->enum('employment_type', ['PKWT', 'PKWTT', 'Probation', 'Internship'])->default('PKWT');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};