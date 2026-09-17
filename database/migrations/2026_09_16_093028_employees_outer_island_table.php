<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees_outer_island', function (Blueprint $table) {
            $table->id('id_employee_outer_island');
            $table->string('uuid')->unique();

            // Identitas Utama Karyawan
            $table->string('no_kk_outer', 16)->nullable();
            $table->string('nik_ktp_outer', 16)->unique();
            $table->string('full_name_outer');
            $table->enum('gender_outer', ['L', 'P']);
            $table->string('birth_place_outer');
            $table->date('birth_date_outer');
            $table->string('religion_outer')->nullable();
            $table->enum('marital_status_outer', ['single', 'married', 'divorced'])->default('single');

            // Kontak & Alamat
            $table->string('phone_number_outer')->nullable();
            $table->string('email_outer')->nullable();
            $table->text('address_ktp_outer');
            $table->text('address_domicile_outer')->nullable();

            // Finansial & Rekening Payroll
            $table->string('npwp_number_outer')->nullable();
            $table->string('bank_name_outer')->nullable();
            $table->string('bank_account_number_outer')->nullable();
            $table->string('bank_account_holder_outer')->nullable();

            // File Upload Foto KTP
            $table->string('ktp_path_outer')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees_outer_island');
    }
};