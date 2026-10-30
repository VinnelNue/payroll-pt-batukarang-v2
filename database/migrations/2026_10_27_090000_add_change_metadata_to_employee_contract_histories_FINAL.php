<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table): void {
            $table->string('change_type', 40)->nullable()->after('pkwt_sequence');
            $table->text('change_reason')->nullable()->after('change_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contract_histories', function (Blueprint $table): void {
            $table->dropColumn(['change_reason', 'change_type']);
        });
    }
};
