<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts_outer_island', function (Blueprint $table) {
            $table->id('id_contract_outer_island');
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_outer_island_id')
                  ->constrained('employees_outer_island', 'id_employee_outer_island')
                  ->onDelete('cascade');
            $table->string('nik_fingerprint')->nullable();
            $table->string('fingerprint_pin')->nullable();
            $table->string('job_title');
            $table->string('department')->nullable();
            $table->string('placement_area')->nullable();
            $table->string('category', 10)->nullable();
            $table->integer('level')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0.00);
            $table->decimal('allowance', 15, 2)->default(0.00);
            $table->boolean('is_bpjstk_active')->default(true);
            $table->boolean('is_bpjs_health_active')->default(true);
            $table->string('ptkp_status', 10)->default('TK/0');
            $table->boolean('use_manual_bpjs')->default(false);
            $table->decimal('manual_bpjs_tk_employee', 15, 2)->default(0.00);
            $table->decimal('manual_bpjs_ks_employee', 15, 2)->default(0.00);
            $table->decimal('manual_bpjs_company', 15, 2)->default(0.00);
            $table->string('employment_type')->default('PKWT');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->text('exit_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts_outer_island');
    }
};

