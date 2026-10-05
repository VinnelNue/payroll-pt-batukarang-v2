<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | REBUILD LOCAL CONTRACT MASTER
        |--------------------------------------------------------------------------
        |
        | Data employee_contracts lama sengaja tidak dipertahankan.
        |
        | Struktur baru:
        |
        | employees
        |     ↓
        | employee_contracts
        |
        | Contract history akan dibuat pada migration terpisah.
        |
        */

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('employee_contracts');

        Schema::enableForeignKeyConstraints();


        /*
        |--------------------------------------------------------------------------
        | CREATE CONTRACT MASTER
        |--------------------------------------------------------------------------
        */

        Schema::create('employee_contracts', function (Blueprint $table) {

            $table->id('id_contract');

            $table->uuid('uuid')
                ->unique();

            $table->unsignedBigInteger('employee_id');

            /*
            |--------------------------------------------------------------------------
            | CURRENT HISTORY
            |--------------------------------------------------------------------------
            |
            | Belum diberi FK di sini.
            | FK akan dipasang setelah employee_contract_histories dibuat.
            |
            */

            $table->unsignedBigInteger('current_contract_history_id')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->unique(
                'employee_id',
                'employee_contract_employee_unique'
            );

            $table->index(
                'current_contract_history_id',
                'employee_contract_current_history_idx'
            );


            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEY: CONTRACT → EMPLOYEE
            |--------------------------------------------------------------------------
            */

            $table->foreign(
                'employee_id',
                'fk_contract_employee'
            )
                ->references('id_employee')
                ->on('employees')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }


    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('employee_contracts');

        Schema::enableForeignKeyConstraints();
    }
};