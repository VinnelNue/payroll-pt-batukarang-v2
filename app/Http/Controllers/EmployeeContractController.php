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
        $employees = Employee::with('activeContract')->latest('id_employee')->paginate(10);

        return view('contracts.index', compact('employees'));
    }

    // 2. Menampilkan Form Setup Kontrak untuk 1 Karyawan
    public function edit(Employee $employee)
    {
        $contract = $employee->activeContract;

        return view('contracts.contract', compact('employee', 'contract'));
    }

    // 3. Menyimpan / Meng-update Kontrak Karyawan
    public function update(Request $request, Employee $employee)
    {
        $contract = $employee->activeContract;

        // Helper pembersih format titik / koma ribuan Rupiah
        $cleanRupiah = function ($value) {
            if (empty($value)) return 0;
            return (float) preg_replace('/[^0-9]/', '', (string)$value);
        };

        // 1. Clean Input Rupiah (Gaji, Tunjangan, & BPJS Manual)
        $request->merge([
            'basic_salary'            => $cleanRupiah($request->basic_salary),
            'allowance'               => $cleanRupiah($request->allowance),
            'manual_bpjs_tk_employee' => $cleanRupiah($request->manual_bpjs_tk_employee),
            'manual_bpjs_ks_employee' => $cleanRupiah($request->manual_bpjs_ks_employee),
            'manual_bpjs_company'     => $cleanRupiah($request->manual_bpjs_company),
        ]);

        // 2. PROTEKSI BACKEND: Jika user BUKAN manager_keuangan / super_admin,
        // kunci nilai Gapok, Tunjangan, & PTKP ke data kontrak lama agar tidak di-bypass
        if (!in_array(auth()->user()->role, ['manager_keuangan', 'super_admin'])) {
            $request->merge([
                'basic_salary' => $contract?->basic_salary ?? 0,
                'allowance'    => $contract?->allowance ?? 0,
                'ptkp_status'  => $contract?->ptkp_status ?? 'TK/0',
            ]);
        }

        // 3. Validasi Input Data
        $validated = $request->validate([
            'job_title'               => 'required|string|max:255',
            'department'              => 'nullable|string|max:255', // Divisi
            'placement_area'          => 'nullable|string|max:255', // Area Penempatan
            'category'                => 'nullable|string|max:10',
            'level'                   => 'nullable|integer',
            'basic_salary'            => 'required|numeric|min:0',
            'allowance'               => 'required|numeric|min:0',
            
            // BPJS Checkbox & Manual Override
            'is_bpjstk_active'        => 'boolean',
            'is_bpjs_health_active'   => 'boolean',
            'use_manual_bpjs'         => 'boolean',
            'manual_bpjs_tk_employee' => 'nullable|numeric|min:0',
            'manual_bpjs_ks_employee' => 'nullable|numeric|min:0',
            'manual_bpjs_company'     => 'nullable|numeric|min:0',
            
            // Status Kontrak & Pajak
            'employment_type'         => 'required|string',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'exit_date'               => 'nullable|date',
            'exit_reason'             => 'nullable|string|max:500',
            'ptkp_status'             => 'required|string|max:10',
        ]);

        // Penyesuaian Boolean Checkbox
        $validated['is_bpjstk_active']      = $request->has('is_bpjstk_active');
        $validated['is_bpjs_health_active'] = $request->has('is_bpjs_health_active');
        $validated['use_manual_bpjs']       = $request->has('use_manual_bpjs');

        // 4. Cek apakah statusnya adalah terminasi (PHK/Resign/Pensiun/End_Contract)
        $isTerminated = in_array($request->employment_type, ['PHK', 'Resign', 'Pensiun', 'End_Contract']);

        // Nonaktifkan record kontrak lama
        EmployeeContract::where('employee_id', $employee->id_employee)->update(['is_active' => false]);

        // 5. Buat record kontrak baru
        $employee->activeContract()->create(array_merge($validated, [
            'is_active' => !$isTerminated,
        ]));

        // 6. Update status keaktifan di Master Employee
        $employee->update([
            'is_active' => !$isTerminated
        ]);

        $message = $isTerminated 
            ? 'Status karyawan telah diperbarui menjadi ' . $request->employment_type . ' (Non-Aktif).'
            : 'Data Penempatan, Divisi, & Gaji Acuan karyawan berhasil disimpan!';

        return redirect()->route('contracts.index')->with('success', $message);
    }
}