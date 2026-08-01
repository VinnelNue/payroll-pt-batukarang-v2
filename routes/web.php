<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeContractController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| ROUTE GUEST (LOGIN & LOGOUT)
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| ROUTE TERKUNCI (HARUS LOGIN / MIDDLEWARE AUTH)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    // 1. Dashboard Utama
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Modul Master Karyawan
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::get('employees/download-template', [EmployeeController::class, 'downloadTemplate'])->name('employees.download-template');

    Route::get('employees/get-cities', [EmployeeController::class, 'getCities'])->name('employees.get-cities');
    Route::get('employees/get-districts', [EmployeeController::class, 'getDistricts'])->name('employees.get-districts');
    Route::get('employees/get-villages', [EmployeeController::class, 'getVillages'])->name('employees.get-villages');

    Route::get('api/cities', [EmployeeController::class, 'getCities'])->name('api.cities');
    Route::get('api/districts', [EmployeeController::class, 'getDistricts'])->name('api.districts');
    Route::get('api/villages', [EmployeeController::class, 'getVillages'])->name('api.villages');

    Route::resource('employees', EmployeeController::class);

    // 3. Modul Kontrak Kerja
    Route::get('contracts', [EmployeeContractController::class, 'index'])->name('contracts.index');
    Route::get('contracts/{employee}/edit', [EmployeeContractController::class, 'edit'])->name('contracts.edit');
    Route::put('contracts/{employee}', [EmployeeContractController::class, 'update'])->name('contracts.update');

    // 4. Modul Payroll & Tax
    Route::get('/payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('/absensi/input', [PayrollController::class, 'create'])->name('absensi.create');
    Route::get('/payrolls/export-bca', [PayrollController::class, 'exportBca'])->name('payrolls.export-bca');
    Route::post('/payrolls/store', [PayrollController::class, 'store'])->name('payrolls.store');
    Route::post('/payrolls/import', [PayrollController::class, 'import'])->name('payrolls.import');

    // Action Lock, Request Unlock, Unlock, Reject
    Route::post('/payrolls/lock', [PayrollController::class, 'lockCalculation'])->name('payrolls.lock');
    Route::post('/payrolls/request-unlock', [PayrollController::class, 'requestUnlock'])->name('payrolls.requestUnlock');
    Route::post('/payrolls/unlock', [PayrollController::class, 'unlockCalculation'])->name('payrolls.unlock');
    Route::post('/payrolls/reject-unlock', [PayrollController::class, 'rejectUnlock'])->name('payrolls.reject-unlock');

    Route::get('/tax-bpjs-master', [PayrollController::class, 'taxBpjsMaster'])->name('tax-bpjs.index');
    Route::post('/tax-bpjs-master/update-bpjs', [PayrollController::class, 'updateBpjsSetting'])->name('tax-bpjs.update-bpjs');

    Route::get('/payrolls/{id}/print-pdf', [PayrollController::class, 'printPdf'])->name('payrolls.print-pdf');
    Route::get('/payrolls/{id}/send-email', [PayrollController::class, 'sendEmail'])->name('payrolls.send-email');

    // 5. Modul Personal Profile Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 6. User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});