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
        | CREATE LOCAL CONTRACT HISTORY
        |--------------------------------------------------------------------------
        |
        | Struktur:
        |
        | employee_contracts
        |        ↓
        | employee_contract_histories
        |
        */

        Schema::create('employee_contract_histories', function (Blueprint $table) {

            $table->id('id_contract_history');

            $table->uuid('uuid')
                ->unique();

            $table->unsignedBigInteger('contract_id');

            /*
            |--------------------------------------------------------------------------
            | IDENTITY / FINGERPRINT
            |--------------------------------------------------------------------------
            */

            $table->string('nik_fingerprint')
                ->nullable();

            $table->string('fingerprint_pin')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | POSITION
            |--------------------------------------------------------------------------
            */

            $table->string('job_title');

            $table->string('department')
                ->nullable();

            $table->string('placement_area')
                ->nullable();

            $table->string('category', 10)
                ->nullable();

            $table->integer('level')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | SALARY
            |--------------------------------------------------------------------------
            */

            $table->decimal('basic_salary', 15, 2)
                ->default(0);

            $table->decimal('allowance', 15, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | BPJS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_bpjstk_active')
                ->default(true);

            $table->boolean('is_bpjs_health_active')
                ->default(true);

            $table->string('ptkp_status', 10)
                ->default('TK/0');

            $table->boolean('use_manual_bpjs')
                ->default(false);

            $table->decimal('manual_bpjs_tk_employee', 15, 2)
                ->default(0);

            $table->decimal('manual_bpjs_ks_employee', 15, 2)
                ->default(0);

            $table->decimal('manual_bpjs_company', 15, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | EMPLOYMENT
            |--------------------------------------------------------------------------
            */

            $table->string('employment_type')
                ->default('PKWT');

            $table->unsignedInteger('pkwt_sequence')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | CONTRACT PERIOD
            |--------------------------------------------------------------------------
            */

            $table->date('start_date');

            $table->date('end_date')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TERMINATION
            |--------------------------------------------------------------------------
            */

            $table->date('exit_date')
                ->nullable();

            $table->text('exit_reason')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(
                'contract_id',
                'employee_history_contract_idx'
            );

            $table->index(
                ['contract_id', 'is_active'],
                'employee_history_active_idx'
            );

            $table->index(
                ['contract_id', 'pkwt_sequence'],
                'employee_history_pkwt_idx'
            );

            $table->index(
                ['start_date', 'end_date'],
                'employee_history_period_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEY
            |--------------------------------------------------------------------------
            */

            $table->foreign(
                'contract_id',
                'fk_history_contract'
            )
                ->references('id_contract')
                ->on('employee_contracts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });


        /*
        |--------------------------------------------------------------------------
        | CURRENT HISTORY FOREIGN KEY
        |--------------------------------------------------------------------------
        |
        | Sekarang employee_contracts dan
        | employee_contract_histories sudah sama-sama ada.
        |
        */

        Schema::table('employee_contracts', function (Blueprint $table) {

            $table->foreign(
                'current_contract_history_id',
                'fk_contract_current_history'
            )
                ->references('id_contract_history')
                ->on('employee_contract_histories')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }


    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | REMOVE CURRENT HISTORY FOREIGN KEY
        |--------------------------------------------------------------------------
        */

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropForeign('fk_contract_current_history');
        });


        /*
        |--------------------------------------------------------------------------
        | REMOVE HISTORY TABLE
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists('employee_contract_histories');
    }
};