<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            // Urutan kontrak PKWT:
            // 1 = kontrak pertama
            // 2 = kontrak kedua
            // 3 = kontrak ketiga, dst.
            //
            // Nullable karena PKWTT, PHK, Resign, Pensiun,
            // dan End_Contract tidak menggunakan sequence PKWT.
            $table->tinyInteger('pkwt_sequence')
                ->nullable()
                ->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn('pkwt_sequence');
        });
    }
};