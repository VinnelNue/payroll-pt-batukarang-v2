<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            $table->date('holiday_date');

            $table->string('name', 150);

            $table->string('type', 30)->default('national');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                'holiday_date',
                'holidays_date_unique'
            );

            $table->index(
                ['holiday_date', 'is_active'],
                'holidays_date_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};