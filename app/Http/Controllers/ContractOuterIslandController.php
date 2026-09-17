<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractOuterIslandController extends Controller
{
    // 1. Menampilkan Tabel Master Penempatan & Kontrak Karyawan Luar Pulau
    public function index()
    {
        $employees = EmployeeOuterIsland::with('activeContract')
            ->oldest('id_employee_outer_island')
            ->paginate(10);

        return view('contracts.outer_island.index', compact('employees'));
    }

    // 2. Menampilkan Form Setup Kontrak untuk 1 Karyawan Luar Pulau
    public function edit(EmployeeOuterIsland $employeeOuterIsland)
    {
        $contract = $employeeOuterIsland->activeContract;

        return view('contracts.outer_island.contract', compact('employeeOuterIsland', 'contract'));
    }

    // 3. Menyimpan / Meng-update Kontrak Karyawan Luar Pulau
    public function update(Request $request, EmployeeOuterIsland $employeeOuterIsland)
    {
        $contract = $employeeOuterIsland->activeContract;

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
            'fingerprint_pin'         => 'nullable|string|max:50',  // PIN Mesin Absen
            'nik_fingerprint'         => 'nullable|string|max:50',  // NIK Mesin Absen
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
        ContractOuterIsland::where('employee_outer_island_id', $employeeOuterIsland->id_employee_outer_island)
            ->update(['is_active' => false]);

        // 5. Buat record kontrak baru
        $employeeOuterIsland->contracts()->create(array_merge($validated, [
            'uuid'      => (string) Str::uuid(),
            'is_active' => !$isTerminated,
        ]));

        // 6. Update status keaktifan di Master Employee Outer Island
        $employeeOuterIsland->update([
            'is_active' => !$isTerminated
        ]);

        $message = $isTerminated 
            ? 'Status karyawan luar pulau telah diperbarui menjadi ' . $request->employment_type . ' (Non-Aktif).'
            : 'Data Penempatan, Divisi, & Gaji Acuan karyawan luar pulau berhasil disimpan!';

        return redirect()->route('contracts.outer_island.index')->with('success', $message);
    }
}