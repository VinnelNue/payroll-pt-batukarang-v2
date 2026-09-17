<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeOuterIslandController;
use App\Http\Controllers\EmployeeContractController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ContractOuterIslandController;

/*
|--------------------------------------------------------------------------
| ROUTE GUEST (LOGIN & LOGOUT)
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.perform');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');


/*
|--------------------------------------------------------------------------
| ROUTE TERKUNCI (MIDDLEWARE AUTH)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 1. DASHBOARD UTAMA
    |--------------------------------------------------------------------------
    */

    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | 2A. MODUL MASTER KARYAWAN LOKAL
    |--------------------------------------------------------------------------
    */

    Route::prefix('employees/local')
        ->name('employees.local.')
        ->group(function () {

            Route::get('export', [EmployeeController::class, 'export'])
                ->name('export');

            Route::post('import', [EmployeeController::class, 'import'])
                ->name('import');

            Route::get('download-template', [EmployeeController::class, 'downloadTemplate'])
                ->name('download-template');
        });

    Route::resource('employees/local', EmployeeController::class)
        ->parameters(['local' => 'employee'])
        ->names('employees.local');


    /*
    |--------------------------------------------------------------------------
    | 2B. MODUL MASTER KARYAWAN LUAR PULAU
    |--------------------------------------------------------------------------
    */

    Route::prefix('employees/outer_island')
        ->name('employees.outer_island.')
        ->group(function () {

            Route::get('export', [EmployeeOuterIslandController::class, 'export'])
                ->name('export');

            Route::post('import', [EmployeeOuterIslandController::class, 'import'])
                ->name('import');

            Route::get('download-template', [EmployeeOuterIslandController::class, 'downloadTemplate'])
                ->name('download-template');
        });

    Route::resource('employees/outer_island', EmployeeOuterIslandController::class)
        ->names('employees.outer_island');


    /*
    |--------------------------------------------------------------------------
    | 3. API REGION INDONESIA
    |--------------------------------------------------------------------------
    */

    Route::prefix('api')
        ->name('api.')
        ->group(function () {

            Route::get('cities', [EmployeeController::class, 'getCities'])
                ->name('cities');

            Route::get('districts', [EmployeeController::class, 'getDistricts'])
                ->name('districts');

            Route::get('villages', [EmployeeController::class, 'getVillages'])
                ->name('villages');
        });


    /*
    |--------------------------------------------------------------------------
    | 4. MODUL KONTRAK KERJA LOKAL & Luar Pulau
    |--------------------------------------------------------------------------
    */

    Route::prefix('contracts/local')
        ->name('contracts.local.')
        ->group(function () {

            Route::get('/', [EmployeeContractController::class, 'index'])
                ->name('index');

            Route::get('{employee}/edit', [EmployeeContractController::class, 'edit'])
                ->name('edit');

            Route::put('{employee}', [EmployeeContractController::class, 'update'])
                ->name('update');
        });


    Route::prefix('contracts/outer_island')
        ->name('contracts.outer_island.')
        ->group(function () {

            Route::get('/', [ContractOuterIslandController::class, 'index'])
                ->name('index');

            Route::get('{employeeOuterIsland}/edit', [ContractOuterIslandController::class, 'edit'])
                ->name('edit');

            Route::put('{employeeOuterIsland}', [ContractOuterIslandController::class, 'update'])
                ->name('update');
        });

    /*
    |--------------------------------------------------------------------------
    | 5. MODUL PAYROLL & TAX - LOCAL
    |--------------------------------------------------------------------------
    |
    | Sebelumnya:
    |
    | /payrolls
    | /absensi/input
    |
    | Sekarang:
    |
    | /payrolls/local
    | /payrolls/local/create
    |
    */

    Route::prefix('payrolls/local')
        ->name('payrolls.local.')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Payroll Index / Rekap
            |--------------------------------------------------------------------------
            */

            Route::get('/', [PayrollController::class, 'index'])
                ->name('index');


            /*
            |--------------------------------------------------------------------------
            | Input Absensi & Variabel Payroll
            |--------------------------------------------------------------------------
            */

            Route::get('create', [PayrollController::class, 'create'])
                ->name('create');


            /*
            |--------------------------------------------------------------------------
            | Simpan Payroll
            |--------------------------------------------------------------------------
            */

            Route::post('store', [PayrollController::class, 'store'])
                ->name('store');


            /*
            |--------------------------------------------------------------------------
            | Import Absensi
            |--------------------------------------------------------------------------
            */

            Route::post('import', [PayrollController::class, 'import'])
                ->name('import');


            /*
            |--------------------------------------------------------------------------
            | Export BCA
            |--------------------------------------------------------------------------
            */

            Route::get('export-bca', [PayrollController::class, 'exportBca'])
                ->name('export-bca');


            /*
            |--------------------------------------------------------------------------
            | LOCK & UNLOCK
            |--------------------------------------------------------------------------
            */

            Route::post('lock', [PayrollController::class, 'lockCalculation'])
                ->name('lock');

            Route::post('request-unlock', [PayrollController::class, 'requestUnlock'])
                ->name('requestUnlock');

            Route::post('unlock', [PayrollController::class, 'unlockCalculation'])
                ->name('unlock');

            Route::post('reject-unlock', [PayrollController::class, 'rejectUnlock'])
                ->name('rejectUnlock');


            /*
            |--------------------------------------------------------------------------
            | DOCUMENT OUTPUT
            |--------------------------------------------------------------------------
            */

            Route::get('{uuid}/print-pdf', [PayrollController::class, 'printPdf'])
                ->name('print-pdf');

            Route::get('{uuid}/send-email', [PayrollController::class, 'sendEmail'])
                ->name('send-email');
        });


    /*
    |--------------------------------------------------------------------------
    | 6. TAX & BPJS MASTER
    |--------------------------------------------------------------------------
    */

    Route::get('/tax-bpjs-master', [PayrollController::class, 'taxBpjsMaster'])
        ->name('tax-bpjs.index');

    Route::post('/tax-bpjs-master/update-bpjs', [PayrollController::class, 'updateBpjsSetting'])
        ->name('tax-bpjs.update-bpjs');


    /*
    |--------------------------------------------------------------------------
    | 7. PROFILE SETTINGS
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');


    /*
    |--------------------------------------------------------------------------
    | 8. USER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::resource('users', UserController::class)
        ->only([
            'index',
            'store',
            'destroy'
        ]);
});