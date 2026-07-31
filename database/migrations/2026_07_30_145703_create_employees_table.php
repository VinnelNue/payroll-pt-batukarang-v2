<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id('id_employee');
            $table->string('uuid')->unique();

            // Identitas Utama Karyawan
            $table->string('nik_ktp', 16)->unique();
            $table->string('full_name');
            $table->string('nickname')->nullable();
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('religion')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced'])->default('single');

            // Kontak & Alamat
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address_ktp');
            $table->text('address_domicile')->nullable();

            // Relasi Wilayah Indonesia (Laravolt)
            $table->char('province_code', 2)->nullable();
            $table->char('city_code', 4)->nullable();
            $table->char('district_code', 7)->nullable();
            $table->char('village_code', 10)->nullable();

            // Finansial & Rekening Payroll
            $table->string('npwp_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};