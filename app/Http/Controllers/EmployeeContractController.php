<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;

class EmployeeContractController extends Controller
{   
    // 1. Menampilkan Tabel Master Penempatan & Kontrak
    public function index()
    {
        // Mengambil data karyawan beserta kontrak aktifnya
        $employees = Employee::with('activeContract')->latest('id_employee')->paginate(10);

        return view('contracts.index', compact('employees'));
    }

    // 2. Menampilkan Form Setup Kontrak untuk 1 Karyawan
    public function edit(Employee $employee)
    {
        // Ambil kontrak aktif jika sudah ada
        $contract = $employee->activeContract;

        // Path view disesuaikan ke folder resources/views/contracts/edit.blade.php
        return view('contracts.contract', compact('employee', 'contract'));
    }

    // 3. Menyimpan / Meng-update Kontrak Karyawan
    public function update(Request $request, Employee $employee)
    {
        // 1. Bersihkan format titik Rupiah
        if ($request->has('basic_salary')) {
            $request->merge([
                'basic_salary' => str_replace('.', '', $request->basic_salary),
            ]);
        }
        if ($request->has('allowance')) {
            $request->merge([
                'allowance' => str_replace('.', '', $request->allowance),
            ]);
        }

        // 2. Validasi Input
        $validated = $request->validate([
            'job_title'             => 'required|string|max:255',
            'category'              => 'nullable|string|max:10',
            'level'                 => 'nullable|integer',
            'basic_salary'          => 'required|numeric|min:0',
            'allowance'             => 'required|numeric|min:0',
            'is_bpjstk_active'      => 'boolean',
            'is_bpjs_health_active' => 'boolean',
            'employment_type'       => 'required|string',
            'start_date'            => 'required|date',
            'end_date'              => 'nullable|date|after_or_equal:start_date',
            'exit_date'             => 'nullable|date',
            'exit_reason'           => 'nullable|string|max:500',
            'ptkp_status'           => 'required|string|max:10',
        ]);

        $validated['is_bpjstk_active'] = $request->has('is_bpjstk_active');
        $validated['is_bpjs_health_active'] = $request->has('is_bpjs_health_active');

        // 3. Cek apakah statusnya adalah PHK/Resign/Berhenti
        $isTerminated = in_array($request->employment_type, ['PHK', 'Resign', 'Pensiun', 'End_Contract']);

        // Nonaktifkan record kontrak lama
        EmployeeContract::where('employee_id', $employee->id_employee)->update(['is_active' => false]);

        // 4. Buat record kontrak baru
        $employee->activeContract()->create(array_merge($validated, [
            'is_active' => !$isTerminated, // Jika PHK, maka is_active = false
        ]));

        // 5. Update status keaktifan di Master Employee
        $employee->update([
            'is_active' => !$isTerminated
        ]);

        $message = $isTerminated 
            ? 'Status karyawan telah diperbarui menjadi ' . $request->employment_type . ' (Non-Aktif).'
            : 'Data Penempatan & Gaji Acuan karyawan berhasil disimpan!';

        return redirect()->route('contracts.index')->with('success', $message);
    }
}