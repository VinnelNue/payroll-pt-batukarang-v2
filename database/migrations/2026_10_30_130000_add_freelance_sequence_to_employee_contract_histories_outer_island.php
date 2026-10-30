<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'employee_contract_histories_outer_island';

        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'freelance_sequence')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedInteger('freelance_sequence')->nullable();
        });
    }

    public function down(): void
    {
        // Intentionally preserve the column: it may have existed before this
        // migration on upgraded databases and is used by stored contract history.
    }
};
