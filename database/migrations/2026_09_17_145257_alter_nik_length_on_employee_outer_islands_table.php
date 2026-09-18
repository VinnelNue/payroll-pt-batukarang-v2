<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_outer_island', function (Blueprint $table) {
            // Perbesar ukuran kolom NIK, No KK, dan No HP menjadi 20 karakter
            $table->string('nik_ktp_outer', 20)->change();
            $table->string('no_kk_outer', 20)->nullable()->change();
            $table->string('phone_number_outer', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees_outer_island', function (Blueprint $table) {
            // Kembalikan ke ukuran semula jika di-rollback
            $table->string('nik_ktp_outer', 16)->change();
            $table->string('no_kk_outer', 16)->nullable()->change();
            $table->string('phone_number_outer', 16)->nullable()->change();
        });
    }
};