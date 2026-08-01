<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert Default Setting BPJS
        DB::table('company_settings')->insert([
            ['key' => 'bpjs_tk_employee_rate', 'value' => '2.0', 'description' => 'Potongan BPJS TK Karyawan (%)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bpjs_ks_employee_rate', 'value' => '1.0', 'description' => 'Potongan BPJS Kesehatan Karyawan (%)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bpjs_ks_max_cap', 'value' => '12000000', 'description' => 'Batas Maksimum Gaji BPJS Kesehatan (Rp)', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};