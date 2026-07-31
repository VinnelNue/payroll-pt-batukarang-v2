<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeContractController; // <-- WAJIB ADD INI DI ATAS

/*
|--------------------------------------------------------------------------
| Web Routes - Payroll 2.0 PT Batu Karang
|--------------------------------------------------------------------------
*/

// 1. Dashboard Utama
Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| ROUTE MODUL MASTER KARYAWAN (DATA DIRI)
|--------------------------------------------------------------------------
*/
// Import & Download Template
Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
Route::get('employees/download-template', [EmployeeController::class, 'downloadTemplate'])->name('employees.download-template');

// Route AJAX Dependent Dropdown Wilayah Laravolt
Route::get('employees/get-cities', [EmployeeController::class, 'getCities'])->name('employees.get-cities');
Route::get('employees/get-districts', [EmployeeController::class, 'getDistricts'])->name('employees.get-districts');
Route::get('employees/get-villages', [EmployeeController::class, 'getVillages'])->name('employees.get-villages');

// Alias untuk kompatibilitas route api.*
Route::get('api/cities', [EmployeeController::class, 'getCities'])->name('api.cities');
Route::get('api/districts', [EmployeeController::class, 'getDistricts'])->name('api.districts');
Route::get('api/villages', [EmployeeController::class, 'getVillages'])->name('api.villages');

Route::resource('employees', EmployeeController::class);

/*
|--------------------------------------------------------------------------
| ROUTE MODUL PENEMPATAN, JABATAN & KONTRAK KERJA
|--------------------------------------------------------------------------
*/
Route::get('contracts', [EmployeeContractController::class, 'index'])->name('contracts.index');
Route::get('contracts/{employee}/edit', [EmployeeContractController::class, 'edit'])->name('contracts.edit');
Route::put('contracts/{employee}', [EmployeeContractController::class, 'update'])->name('contracts.update');