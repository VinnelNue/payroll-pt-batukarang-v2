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
        | PENTING
        |--------------------------------------------------------------------------
        | Data employee TIDAK disentuh.
        |
        | Data contract lama boleh dihapus karena sudah memiliki export.
        |
        | Struktur baru:
        |
        | employees_outer_island
        |        |
        |        v
        | employee_contracts_outer_island
        |        |
        |        v
        | employee_contract_histories_outer_island
        |
        */

        /*
        |--------------------------------------------------------------------------
        | 1. Hapus history lama jika ada
        |--------------------------------------------------------------------------
        */

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists(
            'employee_contract_histories_outer_island'
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Hapus Contract Master lama
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists(
            'employee_contracts_outer_island'
        );

        Schema::enableForeignKeyConstraints();

        /*
        |--------------------------------------------------------------------------
        | 3. Buat Contract Master
        |--------------------------------------------------------------------------
        |
        | Satu employee = satu Contract Master.
        |
        | Contract Master tidak menyimpan detail kontrak.
        | Detail kontrak berada di Contract History.
        |
        */

        Schema::create('employee_contracts_outer_island', function (Blueprint $table) {

            $table->id('id_contract_outer_island');

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger(
                'employee_outer_island_id'
            );

            /*
            |--------------------------------------------------------------------------
            | History aktif/current
            |--------------------------------------------------------------------------
            |
            | Nullable karena Master bisa dibuat terlebih dahulu
            | sebelum Contract History dibuat.
            |
            */

            $table->unsignedBigInteger(
                'current_contract_history_id'
            )->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Satu employee hanya boleh memiliki satu Contract Master
            |--------------------------------------------------------------------------
            */

            $table->unique(
                'employee_outer_island_id',
                'outer_contract_employee_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            $table->index(
                'current_contract_history_id',
                'outer_contract_current_history_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | FK Employee
            |--------------------------------------------------------------------------
            */

            $table->foreign(
                'employee_outer_island_id',
                'fk_oi_contract_employee'
            )
                ->references('id_employee_outer_island')
                ->on('employees_outer_island')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        /*
        |--------------------------------------------------------------------------
        | 4. Buat Contract History
        |--------------------------------------------------------------------------
        */

        Schema::create('employee_contract_histories_outer_island', function (Blueprint $table) {

            $table->id(
                'id_contract_history_outer_island'
            );

            $table->uuid('uuid')->unique();

            /*
            |--------------------------------------------------------------------------
            | Parent Contract Master
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger(
                'contract_outer_island_id'
            );

            /*
            |--------------------------------------------------------------------------
            | Fingerprint
            |--------------------------------------------------------------------------
            */

            $table->string('nik_fingerprint')
                ->nullable();

            $table->string('fingerprint_pin')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Posisi / Organisasi
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
            | Salary
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'basic_salary',
                15,
                2
            )->default(0);

            $table->decimal(
                'allowance',
                15,
                2
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | BPJS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_bpjstk_active')
                ->default(true);

            $table->boolean('is_bpjs_health_active')
                ->default(true);

            $table->string(
                'ptkp_status',
                10
            )->default('TK/0');

            /*
            |--------------------------------------------------------------------------
            | Manual BPJS Override
            |--------------------------------------------------------------------------
            */

            $table->boolean('use_manual_bpjs')
                ->default(false);

            $table->decimal(
                'manual_bpjs_tk_employee',
                15,
                2
            )->default(0);

            $table->decimal(
                'manual_bpjs_ks_employee',
                15,
                2
            )->default(0);

            $table->decimal(
                'manual_bpjs_company',
                15,
                2
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Jenis Kontrak
            |--------------------------------------------------------------------------
            */

            $table->string('employment_type')
                ->default('PKWT');

            /*
            |--------------------------------------------------------------------------
            | PKWT Sequence
            |--------------------------------------------------------------------------
            |
            | PKWT 1
            | PKWT 2
            | PKWT 3
            |
            | PKWTT / Probation / dll = NULL
            |
            */

            $table->unsignedInteger(
                'pkwt_sequence'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Periode
            |--------------------------------------------------------------------------
            */

            $table->date('start_date');

            $table->date('end_date')
                ->nullable();

            $table->date('exit_date')
                ->nullable();

            $table->text('exit_reason')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            $table->index(
                'contract_outer_island_id',
                'outer_history_contract_idx'
            );

            $table->index(
                [
                    'contract_outer_island_id',
                    'is_active'
                ],
                'outer_history_active_idx'
            );

            $table->index(
                [
                    'contract_outer_island_id',
                    'pkwt_sequence'
                ],
                'outer_history_pkwt_idx'
            );

            $table->index(
                [
                    'start_date',
                    'end_date'
                ],
                'outer_history_period_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | FK History → Contract Master
            |--------------------------------------------------------------------------
            */

            $table->foreign(
                'contract_outer_island_id',
                'fk_oi_history_contract'
            )
                ->references('id_contract_outer_island')
                ->on('employee_contracts_outer_island')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        /*
        |--------------------------------------------------------------------------
        | 5. FK Contract Master → Current History
        |--------------------------------------------------------------------------
        |
        | SEKARANG baru dibuat karena kedua tabel sudah tersedia.
        |
        */

        Schema::table(
            'employee_contracts_outer_island',
            function (Blueprint $table) {

                $table->foreign(
                    'current_contract_history_id',
                    'fk_oi_master_current_history'
                )
                    ->references(
                        'id_contract_history_outer_island'
                    )
                    ->on(
                        'employee_contract_histories_outer_island'
                    )
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Hapus FK Master → History
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable(
            'employee_contracts_outer_island'
        )) {

            Schema::table(
                'employee_contracts_outer_island',
                function (Blueprint $table) {

                    $table->dropForeign(
                        'fk_oi_master_current_history'
                    );
                }
            );
        }

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists(
            'employee_contract_histories_outer_island'
        );

        Schema::dropIfExists(
            'employee_contracts_outer_island'
        );

        Schema::enableForeignKeyConstraints();
    }
};