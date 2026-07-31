<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            // Ubah enum employment_type agar mendukung status PHK, Resign, dll.
            $table->string('employment_type')->default('PKWT')->change(); 
            
            // Kolom baru untuk pencatatan keluar/PHK
            $table->date('exit_date')->nullable()->after('end_date');
            $table->text('exit_reason')->nullable()->after('exit_date');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['exit_date', 'exit_reason']);
        });
    }
};