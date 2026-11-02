<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('weekday_overtime_hours', 5, 1)
                ->default(0)
                ->after('overtime_hours');

            $table->json('sunday_overtime_details')
                ->nullable()
                ->after('weekday_overtime_hours');
        });

        // Preserve all existing monthly overtime hours as weekday overtime.
        DB::table('payrolls')->update([
            'weekday_overtime_hours' => DB::raw('COALESCE(overtime_hours, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'sunday_overtime_details',
                'weekday_overtime_hours',
            ]);
        });
    }
};
