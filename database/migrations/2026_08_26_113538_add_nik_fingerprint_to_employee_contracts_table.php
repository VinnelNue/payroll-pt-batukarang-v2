<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('employee_contracts', function (Blueprint $table) {
        $table->string('nik_fingerprint')->nullable()->after('employee_id');
    });
}

public function down()
{
    Schema::table('employee_contracts', function (Blueprint $table) {
        $table->dropColumn('nik_fingerprint');
    });
}
};
