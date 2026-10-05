<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table) {
            $table->string('ter_category', 1)
                ->nullable()
                ->after('ptkp_status');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table) {
            $table->dropColumn('ter_category');
        });
    }
};