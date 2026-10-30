<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contract_histories_outer_island')) {
            return;
        }

        Schema::table('employee_contract_histories_outer_island', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contract_histories_outer_island', 'change_type')) {
                $table->string('change_type', 100)->nullable();
            }

            if (!Schema::hasColumn('employee_contract_histories_outer_island', 'change_reason')) {
                $table->text('change_reason')->nullable();
            }

        });
    }

    public function down(): void
    {
        /*
         * Intentionally do not drop these optional history metadata columns.
         * The columns may have existed before this migration on an upgraded
         * database; dropping them here could destroy contract audit notes.
         */
    }
};
