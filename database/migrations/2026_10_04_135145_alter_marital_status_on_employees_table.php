<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('marital_status', 10)
                ->default('TK0')
                ->change();
        });
    }

    public function down(): void
    {
        /*
        |----------------------------------------------------------------------
        | Convert kembali ke format lama sebelum ENUM
        |----------------------------------------------------------------------
        */

        \DB::table('employees')
            ->where('marital_status', 'like', 'K%')
            ->update([
                'marital_status' => 'married',
            ]);

        \DB::table('employees')
            ->where('marital_status', 'like', 'TK%')
            ->update([
                'marital_status' => 'single',
            ]);

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', [
                'single',
                'married',
                'divorced',
            ])
            ->default('single')
            ->change();
        });
    }
};