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

// ROUTE IMPOR & DOWNLOAD TEMPLATE KARYAWAN (Wajib ditaruh sebelum Route::resource)
Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
Route::get('employees/download-template', [EmployeeController::class, 'downloadTemplate'])->name('employees.download-template');

// 2. Master Data Diri Karyawan (CRUD)
Route::resource('employees', EmployeeController::class);

// Route AJAX Dependent Dropdown Wilayah
Route::get('api/cities', [EmployeeController::class, 'getCities'])->name('api.cities');
Route::get('api/districts', [EmployeeController::class, 'getDistricts'])->name('api.districts');
Route::get('api/villages', [EmployeeController::class, 'getVillages'])->name('api.villages');