<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;

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
| ROUTE CUSTOM KARYAWAN (Wajib ditaruh SEBELUM Route::resource)
|--------------------------------------------------------------------------
*/
// Import & Download Template
Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
Route::get('employees/download-template', [EmployeeController::class, 'downloadTemplate'])->name('employees.download-template');

// Route AJAX Dependent Dropdown Wilayah Laravolt
Route::get('employees/get-cities', [EmployeeController::class, 'getCities'])->name('employees.get-cities');
Route::get('employees/get-districts', [EmployeeController::class, 'getDistricts'])->name('employees.get-districts');
Route::get('employees/get-villages', [EmployeeController::class, 'getVillages'])->name('employees.get-villages');

// Alias untuk kompatibilitas route api.* (jika ada script lama yang memanggil)
Route::get('api/cities', [EmployeeController::class, 'getCities'])->name('api.cities');
Route::get('api/districts', [EmployeeController::class, 'getDistricts'])->name('api.districts');
Route::get('api/villages', [EmployeeController::class, 'getVillages'])->name('api.villages');

/*
|--------------------------------------------------------------------------
| ROUTE RESOURCE KARYAWAN (Wajib paling bawah dari modul employees)
|--------------------------------------------------------------------------
*/
Route::resource('employees', EmployeeController::class);