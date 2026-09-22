<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;

class EmployeeContractController extends Controller
{
    /**
     * Menentukan apakah user boleh melihat data sensitif
     * berdasarkan role dan level yang SUDAH tersimpan.
     */
    private function canViewSensitiveData(
        string $role,
        ?int $contractLevel
    ): bool {
        // Super Admin dan Manager Keuangan selalu boleh melihat.
        if (in_array($role, [
            'super_admin',
            'manager_keuangan',
        ])) {
            return true;
        }

        // Head HRD:
        // Level <= 13  -> boleh melihat
        // Level > 13   -> tidak boleh melihat
        if ($role === 'head_hrd') {
            return is_null($contractLevel)
                || $contractLevel <= 13;
        }

        // HRD dan Karyawan tidak boleh melihat data sensitif.
        return false;
    }


    /**
     * Menghitung tunjangan tetap berdasarkan level.
     *
     * Sementara:
     *
     * Level 1  = 2%
     * Level 2  = 4%
     * Level 3  = 6%
     * dst.
     *
     * Rumus:
     *
     * basic_salary x (level x 2%)
     */
    private function calculateAllowance(
        float|int $basicSalary,
        ?int $level
    ): float {
        if (!$level || $level < 1) {
            return 0;
        }

        $percentage = $level * 0.02;

        return round(
            $basicSalary * $percentage,
            2
        );
    }


    /**
     * Membersihkan input Rupiah.
     *
     * Contoh:
     * Rp 5.000.000 -> 5000000
     * 5.000.000    -> 5000000
     */
    private function cleanRupiah($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) preg_replace(
            '/[^0-9]/',
            '',
            (string) $value
        );
    }


    /**
     * INDEX
     */
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;

        $employees = Employee::with('activeContract')
            ->oldest('id_employee')
            ->paginate(10);

        $employees->getCollection()->transform(
            function ($employee) use ($role) {

                $contract = $employee->activeContract;

                if ($contract) {

                    $employee->can_view_sensitive_data =
                        $this->canViewSensitiveData(
                            $role,
                            $contract->level
                        );

                } else {

                    $employee->can_view_sensitive_data = false;
                }

                return $employee;
            }
        );

        return view(
            'contracts.local.index',
            compact('employees')
        );
    }


    /**
     * EDIT
     */
    public function edit(Employee $employee)
    {
        $user = auth()->user();
        $role = $user->role;

        $contract = $employee->activeContract;

        /**
         * Permission berdasarkan LEVEL YANG SUDAH TERSIMPAN.
         *
         * Untuk perubahan level sebelum Save,
         * JavaScript di Blade yang akan menangani
         * masking finansial secara realtime.
         */
        $canViewSensitiveData =
            $this->canViewSensitiveData(
                $role,
                $contract?->level
            );

        return view(
            'contracts.local.contract',
            compact(
                'employee',
                'contract',
                'canViewSensitiveData'
            )
        );
    }


    /**
     * UPDATE
     */
    public function update(
        Request $request,
        Employee $employee
    ) {
        $user = auth()->user();
        $role = $user->role;

        $contract = $employee->activeContract;

        /*
        |--------------------------------------------------------------------------
        | LEVEL LAMA
        |--------------------------------------------------------------------------
        */

        $currentLevel = $contract?->level;


        /*
        |--------------------------------------------------------------------------
        | DATA LAMA YANG DILINDUNGI
        |--------------------------------------------------------------------------
        */

        $oldBasicSalary =
            $contract?->basic_salary ?? 0;

        $oldAllowance =
            $contract?->allowance ?? 0;

        $oldPtkpStatus =
            $contract?->ptkp_status ?? 'TK/0';

        $oldUseManualBpjs =
            $contract?->use_manual_bpjs ?? false;

        $oldManualBpjsTkEmployee =
            $contract?->manual_bpjs_tk_employee ?? 0;

        $oldManualBpjsKsEmployee =
            $contract?->manual_bpjs_ks_employee ?? 0;

        $oldManualBpjsCompany =
            $contract?->manual_bpjs_company ?? 0;

        $oldLevel =
            $contract?->level;

        $oldCategory =
            $contract?->category;


        /*
        |--------------------------------------------------------------------------
        | HEAD HRD - KONTRAK LEVEL > 13
        |--------------------------------------------------------------------------
        |
        | Jika level yang SUDAH TERSIMPAN > 13,
        | Head HRD masih boleh update:
        |
        | - Job Title
        | - Department
        | - Placement
        | - Fingerprint
        | - Employment Type
        | - PKWT Sequence
        | - Start Date
        | - End Date
        | - Exit Date
        | - Exit Reason
        | - Status BPJS
        |
        | Tetapi TIDAK boleh mengubah:
        |
        | - Category
        | - Level
        | - Basic Salary
        | - Allowance
        | - PTKP
        | - Manual BPJS
        |
        */

        if (
            $role === 'head_hrd'
            &&
            !is_null($currentLevel)
            &&
            $currentLevel > 13
        ) {

            /*
            |--------------------------------------------------------------------------
            | KUNCI DATA SENSITIF
            |--------------------------------------------------------------------------
            */

            $request->merge([

                'category' =>
                    $oldCategory,

                'level' =>
                    $oldLevel,

                'basic_salary' =>
                    $oldBasicSalary,

                'allowance' =>
                    $oldAllowance,

                'ptkp_status' =>
                    $oldPtkpStatus,

                'use_manual_bpjs' =>
                    $oldUseManualBpjs,

                'manual_bpjs_tk_employee' =>
                    $oldManualBpjsTkEmployee,

                'manual_bpjs_ks_employee' =>
                    $oldManualBpjsKsEmployee,

                'manual_bpjs_company' =>
                    $oldManualBpjsCompany,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | HRD & KARYAWAN
        |--------------------------------------------------------------------------
        |
        | Semua data sensitif dikembalikan ke nilai database.
        */

        if (in_array($role, [
            'hrd',
            'karyawan',
        ])) {

            $request->merge([

                'basic_salary' =>
                    $oldBasicSalary,

                'allowance' =>
                    $oldAllowance,

                'ptkp_status' =>
                    $oldPtkpStatus,

                'level' =>
                    $oldLevel,

                'category' =>
                    $oldCategory,

                'manual_bpjs_tk_employee' =>
                    $oldManualBpjsTkEmployee,

                'manual_bpjs_ks_employee' =>
                    $oldManualBpjsKsEmployee,

                'manual_bpjs_company' =>
                    $oldManualBpjsCompany,

                'use_manual_bpjs' =>
                    $oldUseManualBpjs,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CLEAN RUPIAH
        |--------------------------------------------------------------------------
        */

        $request->merge([

            'basic_salary' =>
                $this->cleanRupiah(
                    $request->input('basic_salary')
                ),

            'allowance' =>
                $this->cleanRupiah(
                    $request->input('allowance')
                ),

            'manual_bpjs_tk_employee' =>
                $this->cleanRupiah(
                    $request->input(
                        'manual_bpjs_tk_employee'
                    )
                ),

            'manual_bpjs_ks_employee' =>
                $this->cleanRupiah(
                    $request->input(
                        'manual_bpjs_ks_employee'
                    )
                ),

            'manual_bpjs_company' =>
                $this->cleanRupiah(
                    $request->input(
                        'manual_bpjs_company'
                    )
                ),
        ]);


        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'job_title' =>
                'required|string|max:255',

            'department' =>
                'nullable|string|max:255',

            'placement_area' =>
                'nullable|string|max:255',

            'fingerprint_pin' =>
                'nullable|string|max:50',

            'nik_fingerprint' =>
                'nullable|string|max:50',

            'category' =>
                'nullable|string|max:10',

            'level' =>
                'nullable|integer|min:1',

            'basic_salary' =>
                'required|numeric|min:0',

            'allowance' =>
                'required|numeric|min:0',

            'is_bpjstk_active' =>
                'nullable|boolean',

            'is_bpjs_health_active' =>
                'nullable|boolean',

            'use_manual_bpjs' =>
                'nullable|boolean',

            'manual_bpjs_tk_employee' =>
                'nullable|numeric|min:0',

            'manual_bpjs_ks_employee' =>
                'nullable|numeric|min:0',

            'manual_bpjs_company' =>
                'nullable|numeric|min:0',

            /*
            |--------------------------------------------------------------------------
            | PKWT
            |--------------------------------------------------------------------------
            */

            'employment_type' =>
                'required|string',

            'pkwt_sequence' =>
                'nullable|required_if:employment_type,PKWT|integer|min:1|max:10',

            /*
            |--------------------------------------------------------------------------
            | TANGGAL
            |--------------------------------------------------------------------------
            */

            'start_date' =>
                'required|date',

            'end_date' =>
                'nullable|date|after_or_equal:start_date',

            'exit_date' =>
                'nullable|date',

            'exit_reason' =>
                'nullable|string|max:500',

            /*
            |--------------------------------------------------------------------------
            | PAJAK
            |--------------------------------------------------------------------------
            */

            'ptkp_status' =>
                'required|string|max:10',
        ]);


        /*
        |--------------------------------------------------------------------------
        | BOOLEAN
        |--------------------------------------------------------------------------
        */

        $validated['is_bpjstk_active'] =
            $request->boolean(
                'is_bpjstk_active'
            );

        $validated['is_bpjs_health_active'] =
            $request->boolean(
                'is_bpjs_health_active'
            );

        $validated['use_manual_bpjs'] =
            $request->boolean(
                'use_manual_bpjs'
            );


        /*
        |--------------------------------------------------------------------------
        | PKWT SEQUENCE
        |--------------------------------------------------------------------------
        |
        | Hanya PKWT yang membutuhkan nomor kontrak.
        */

        if (
            $validated['employment_type'] !== 'PKWT'
        ) {

            $validated['pkwt_sequence'] = null;
        }


        /*
        |--------------------------------------------------------------------------
        | LEVEL BARU
        |--------------------------------------------------------------------------
        */

        $newLevel =
            $validated['level'] ?? null;


        /*
        |--------------------------------------------------------------------------
        | HEAD HRD -> LEVEL > 13
        |--------------------------------------------------------------------------
        |
        | Ini penting:
        |
        | Jika Head HRD melakukan:
        |
        | Level 12 -> Level 15
        |
        | maka Head HRD BOLEH menyimpan Level 15.
        |
        | Tetapi semua nilai finansial tetap menggunakan
        | nilai sebelumnya.
        |
        */

        $headHrdSavingAbove13 =
            $role === 'head_hrd'
            &&
            !is_null($newLevel)
            &&
            $newLevel > 13;


        if ($headHrdSavingAbove13) {

            /*
            |--------------------------------------------------------------------------
            | PERTAHANKAN DATA FINANSIAL LAMA
            |--------------------------------------------------------------------------
            */

            $validated['basic_salary'] =
                $oldBasicSalary;

            $validated['allowance'] =
                $oldAllowance;

            $validated['ptkp_status'] =
                $oldPtkpStatus;

            $validated['manual_bpjs_tk_employee'] =
                $oldManualBpjsTkEmployee;

            $validated['manual_bpjs_ks_employee'] =
                $oldManualBpjsKsEmployee;

            $validated['manual_bpjs_company'] =
                $oldManualBpjsCompany;

            $validated['use_manual_bpjs'] =
                $oldUseManualBpjs;
        }


        /*
        |--------------------------------------------------------------------------
        | HITUNG TUNJANGAN OTOMATIS
        |--------------------------------------------------------------------------
        |
        | Berlaku untuk:
        |
        | - Super Admin
        | - Manager Keuangan
        | - Head HRD Level <= 13
        |
        | Tidak berlaku ketika Head HRD menyimpan
        | Level > 13.
        */

        if (!$headHrdSavingAbove13) {

            if (
                in_array($role, [
                    'super_admin',
                    'manager_keuangan',
                ])
                ||
                (
                    $role === 'head_hrd'
                    &&
                    (
                        is_null($newLevel)
                        ||
                        $newLevel <= 13
                    )
                )
            ) {

                $validated['allowance'] =
                    $this->calculateAllowance(
                        $validated['basic_salary'],
                        $newLevel
                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | MANUAL BPJS
        |--------------------------------------------------------------------------
        */

        if (
            !$validated['use_manual_bpjs']
        ) {

            $validated[
                'manual_bpjs_tk_employee'
            ] = 0;

            $validated[
                'manual_bpjs_ks_employee'
            ] = 0;

            $validated[
                'manual_bpjs_company'
            ] = 0;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS KARYAWAN
        |--------------------------------------------------------------------------
        */

        $isTerminated = in_array(
            $validated['employment_type'],
            [
                'PHK',
                'Resign',
                'Pensiun',
                'End_Contract',
            ]
        );

        $validated['is_active'] =
            !$isTerminated;


        /*
        |--------------------------------------------------------------------------
        | SIMPAN CONTRACT
        |--------------------------------------------------------------------------
        */

        if ($contract) {

            $contract->update(
                $validated
            );

        } else {

            $validated['employee_id'] =
                $employee->id_employee;

            EmployeeContract::create(
                $validated
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS EMPLOYEE
        |--------------------------------------------------------------------------
        */

        $employee->update([
            'is_active' =>
                !$isTerminated,
        ]);


        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        $message = $isTerminated
            ? 'Status karyawan telah diperbarui menjadi '
                . $request->employment_type
                . ' (Non-Aktif).'
            : 'Data Penempatan, Divisi, & Gaji Acuan karyawan berhasil disimpan!';


        return redirect()
            ->route(
                'contracts.local.index'
            )
            ->with(
                'success',
                $message
            );
    }
}