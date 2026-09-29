<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland;
use App\Models\ContractHistoryOuterIsland;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
class ContractOuterIslandController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ROLE CONFIGURATION
    |--------------------------------------------------------------------------
    */

    private const FINANCE_ROLES = [
        'manager_keuangan',
        'super_admin',
    ];

    private const HEAD_HRD_ROLES = [
        'head_hrd',
        'kepala_hrd',
    ];

    private const KEUANGAN_ROLE = 'keuangan';

    private const HRD_ROLE = 'hrd';

    /*
    |--------------------------------------------------------------------------
    | 1. INDEX
    |--------------------------------------------------------------------------
    |
    | Menampilkan daftar karyawan Outer Island.
    |
    | Sumber data kontrak:
    |
    | Employee
    |   -> Contract Master
    |       -> Current History
    |
    */

    public function index(Request $request)
    {
        $search = trim(
            $request->get('search', '')
        );

        $employees = EmployeeOuterIsland::with([
            'contractMaster.currentHistory',
        ])
            ->when(
                $search !== '',
                function ($query) use ($search) {

                    $query->where(function ($q) use ($search) {

                        $q->where(
                            'full_name_outer',
                            'LIKE',
                            "%{$search}%"
                        )

                        ->orWhere(
                            'nik_ktp_outer',
                            'LIKE',
                            "%{$search}%"
                        )

                        ->orWhereHas(
                            'contractMaster.currentHistory',
                            function ($history) use ($search) {

                                $history
                                    ->where(
                                        'job_title',
                                        'LIKE',
                                        "%{$search}%"
                                    )

                                    ->orWhere(
                                        'department',
                                        'LIKE',
                                        "%{$search}%"
                                    )

                                    ->orWhere(
                                        'placement_area',
                                        'LIKE',
                                        "%{$search}%"
                                    );
                            }
                        );
                    });
                }
            )
            ->oldest(
                'id_employee_outer_island'
            )
            ->paginate(10)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | HELPER DATA
        |--------------------------------------------------------------------------
        */

        $employees->getCollection()->transform(
            function ($employee) {

                $history =
                    $employee
                        ->contractMaster
                        ?->currentHistory;

                $employee->current_contract_history =
                    $history;

                $employee->current_contract_level =
                    $history?->level;

                $employee->has_contract_master =
                    $employee->contractMaster !== null;

                $employee->has_current_contract =
                    $history !== null;

                $employee->can_view_sensitive_data =
                    $this->canViewSensitiveData(
                        $history
                    );

                return $employee;
            }
        );

        return view(
            'contracts.outer_island.index',
            compact(
                'employees',
                'search'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(
        EmployeeOuterIsland $employee
    ) {
        $employee->load([
            'contractMaster.currentHistory',
            'contractMaster.histories',
        ]);

        $contract =
            $employee->contractMaster;

        $history =
            $contract?->currentHistory;

        $histories =
            $contract?->histories ?? collect();

        /*
        |--------------------------------------------------------------------------
        | HISTORY TERBARU DI ATAS
        |--------------------------------------------------------------------------
        */

        $histories =
            $histories
                ->sortByDesc(
                    'id_contract_history_outer_island'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | ACCESS
        |--------------------------------------------------------------------------
        */

        $canViewSensitiveData =
            $this->canViewSensitiveData(
                $history
            );

        $canEditCurrent =
            $this->canEditCurrentContract(
                $history
            );

        /*
        |--------------------------------------------------------------------------
        | NEXT PKWT
        |--------------------------------------------------------------------------
        */

        $nextPkwtSequence =
            $contract
                ? $this->getNextPkwtSequence($contract)
                : 1;

        /*
        |--------------------------------------------------------------------------
        | LEVEL
        |--------------------------------------------------------------------------
        */

        $contractLevel =
            $history?->level;

        $levelNumber =
            is_numeric($contractLevel)
                ? (int) $contractLevel
                : null;

        $isHighLevel =
            $levelNumber !== null &&
            $levelNumber >= 14;

        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */

        $userRole =
            auth()->user()?->role;

        $isManagerKeuangan =
            $this->isFinanceRole(
                $userRole
            );

        $isHeadHrd =
            $this->isHeadHrdRole(
                $userRole
            );

        $isKeuangan =
            $userRole === self::KEUANGAN_ROLE;

        $isHrd =
            $userRole === self::HRD_ROLE;

        /*
        |--------------------------------------------------------------------------
        | LEVEL / CATEGORY
        |--------------------------------------------------------------------------
        */

        $canSeeLevelCategory =
            $isManagerKeuangan
            ||
            (
                (
                    $isHeadHrd
                    ||
                    $isKeuangan
                    ||
                    $isHrd
                )
                &&
                (
                    $levelNumber === null
                    ||
                    $levelNumber <= 13
                )
            );

        $maskLevelCategory =
            !$isManagerKeuangan
            &&
            (
                $isHeadHrd
                ||
                $isKeuangan
                ||
                $isHrd
            )
            &&
            $isHighLevel;

        /*
        |--------------------------------------------------------------------------
        | FINANCIAL
        |--------------------------------------------------------------------------
        */

        $canSeeSalary =
            $canViewSensitiveData;

        $maskFinancial =
            !$canSeeSalary;

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        $hasContractMaster =
            $contract !== null;

        $hasCurrentHistory =
            $history !== null;

        return view(
            'contracts.outer_island.contract',
            compact(
                'employee',
                'contract',
                'history',
                'histories',
                'hasContractMaster',
                'hasCurrentHistory',
                'canViewSensitiveData',
                'canEditCurrent',
                'nextPkwtSequence',
                'contractLevel',
                'levelNumber',
                'isHighLevel',
                'userRole',
                'isManagerKeuangan',
                'isHeadHrd',
                'isKeuangan',
                'isHrd',
                'canSeeLevelCategory',
                'maskLevelCategory',
                'canSeeSalary',
                'maskFinancial'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. UPDATE CURRENT CONTRACT
    |--------------------------------------------------------------------------
    |
    | Save Current Contract:
    |
    | - Tidak membuat history baru.
    | - Update current history yang sama.
    |
    */
public function update(
    Request $request,
    EmployeeOuterIsland $employee
) {
    /*
    |--------------------------------------------------------------------------
    | LOAD EMPLOYEE + CONTRACT
    |--------------------------------------------------------------------------
    */

    $employee->load([
        'contractMaster.currentHistory',
    ]);

    $contract = $employee->contractMaster;

    if (!$contract) {
        return redirect()
            ->route('contracts.outer_island.index')
            ->with(
                'error',
                'Karyawan ini belum memiliki Contract Master.'
            );
    }

    $history = $contract->currentHistory;

    if (!$history) {
        return redirect()
            ->route('contracts.outer_island.index')
            ->with(
                'error',
                'Karyawan ini belum memiliki Contract Period.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE
    |--------------------------------------------------------------------------
    */

    $role = auth()->user()?->role;

    $isFinance = $this->isFinanceRole($role);

    $isHeadHrd = $this->isHeadHrdRole($role);

    $isKeuangan =
        $role === self::KEUANGAN_ROLE;

    $isHrd =
        $role === self::HRD_ROLE;

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZE ROLE
    |--------------------------------------------------------------------------
    |
    | Hanya role yang memang diperbolehkan mengubah contract.
    |
    */

    abort_unless(
        $isFinance ||
        $isHeadHrd ||
        $isKeuangan ||
        $isHrd,
        403
    );

    /*
    |--------------------------------------------------------------------------
    | CURRENT POINTER VALIDATION
    |--------------------------------------------------------------------------
    |
    | Pastikan history yang diedit benar-benar current history.
    |
    */

    abort_unless(
        (int) $contract->current_contract_history_id ===
        (int) $history->id_contract_history_outer_island,
        403
    );

    /*
    |--------------------------------------------------------------------------
    | OLD LEVEL
    |--------------------------------------------------------------------------
    */

    $oldLevelNumber =
        is_numeric($history->level)
            ? (int) $history->level
            : null;

    $oldLevelSensitive =
        $oldLevelNumber === null ||
        $oldLevelNumber <= 13;

    /*
    |--------------------------------------------------------------------------
    | FINANCIAL ACCESS
    |--------------------------------------------------------------------------
    |
    | Finance:
    |     Semua level
    |
    | Head HRD:
    |     <=13
    |
    | Keuangan:
    |     <=13
    |
    | HRD:
    |     Tidak boleh mengubah financial
    |
    */

    $canEditFinancial =
        $isFinance ||
        (
            (
                $isHeadHrd ||
                $isKeuangan
            )
            &&
            $oldLevelSensitive
        );

    /*
    |--------------------------------------------------------------------------
    | CLEAN MONEY INPUT
    |--------------------------------------------------------------------------
    |
    | Kita hanya membersihkan field money.
    |
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
    | VALIDATION
    |--------------------------------------------------------------------------
    |
    | Validasi dilakukan SERVER-SIDE.
    | Jangan percaya readonly/disabled/masked input dari Blade.
    |
    */

    $validated = $request->validate([

        'job_title' => [
            'required',
            'string',
            'max:255',
        ],

        'department' => [
            'nullable',
            'string',
            'max:255',
        ],

        'placement_area' => [
            'nullable',
            'string',
            'max:255',
        ],

        'fingerprint_pin' => [
            'nullable',
            'string',
            'max:255',
        ],

        'nik_fingerprint' => [
            'nullable',
            'string',
            'max:255',
        ],

        'category' => [
            'nullable',
            'string',
            'max:10',
        ],

        'level' => [
            'nullable',
            'integer',
            'min:0',
            'max:100',
        ],

        'basic_salary' => [
            'required',
            'numeric',
            'min:0',
            'max:999999999999.99',
        ],

        'allowance' => [
            'required',
            'numeric',
            'min:0',
            'max:999999999999.99',
        ],

        'is_bpjstk_active' => [
            'nullable',
            'boolean',
        ],

        'is_bpjs_health_active' => [
            'nullable',
            'boolean',
        ],

        'ptkp_status' => [
            'required',
            'string',
            'max:10',
        ],

        'use_manual_bpjs' => [
            'nullable',
            'boolean',
        ],

        'manual_bpjs_tk_employee' => [
            'nullable',
            'numeric',
            'min:0',
            'max:999999999999.99',
        ],

        'manual_bpjs_ks_employee' => [
            'nullable',
            'numeric',
            'min:0',
            'max:999999999999.99',
        ],

        'manual_bpjs_company' => [
            'nullable',
            'numeric',
            'min:0',
            'max:999999999999.99',
        ],

        'employment_type' => [
            'required',
            'string',
            'in:PKWT,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',
        ],

        'start_date' => [
            'required',
            'date',
        ],

        'end_date' => [
            'nullable',
            'date',
            'after_or_equal:start_date',
        ],

        'exit_date' => [
            'nullable',
            'date',
        ],

        'exit_reason' => [
            'nullable',
            'string',
            'max:500',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE BOOLEAN
    |--------------------------------------------------------------------------
    */

    $validated['is_bpjstk_active'] =
        $request->has('is_bpjstk_active');

    $validated['is_bpjs_health_active'] =
        $request->has('is_bpjs_health_active');

    $validated['use_manual_bpjs'] =
        $request->has('use_manual_bpjs');

    /*
    |--------------------------------------------------------------------------
    | NEW LEVEL
    |--------------------------------------------------------------------------
    */

    $newLevel =
        $validated['level'] ?? null;

    $newLevelNumber =
        is_numeric($newLevel)
            ? (int) $newLevel
            : null;

    $newLevelHigh =
        $newLevelNumber !== null &&
        $newLevelNumber >= 14;

    /*
    |--------------------------------------------------------------------------
    | PROTECT LEVEL + CATEGORY
    |--------------------------------------------------------------------------
    |
    | Finance:
    |     boleh
    |
    | Head HRD / Keuangan:
    |     boleh selama CURRENT <=13
    |
    | HRD:
    |     tidak boleh
    |
    | Current >=14:
    |     non-finance tidak boleh mengubah
    |
    */

    if (
        !$isFinance &&
        !$isHeadHrd &&
        !$isKeuangan
    ) {
        $validated['category'] =
            $history->category;

        $validated['level'] =
            $history->level;
    }

    elseif (
        !$isFinance &&
        !$oldLevelSensitive
    ) {
        $validated['category'] =
            $history->category;

        $validated['level'] =
            $history->level;
    }

    /*
    |--------------------------------------------------------------------------
    | PROTECT FINANCIAL DATA
    |--------------------------------------------------------------------------
    */

    if (!$canEditFinancial) {

        $validated['basic_salary'] =
            $history->basic_salary;

        $validated['allowance'] =
            $history->allowance;

        $validated['ptkp_status'] =
            $history->ptkp_status;

        $validated['is_bpjstk_active'] =
            $history->is_bpjstk_active;

        $validated['is_bpjs_health_active'] =
            $history->is_bpjs_health_active;

        $validated['use_manual_bpjs'] =
            $history->use_manual_bpjs;

        $validated['manual_bpjs_tk_employee'] =
            $history->manual_bpjs_tk_employee;

        $validated['manual_bpjs_ks_employee'] =
            $history->manual_bpjs_ks_employee;

        $validated['manual_bpjs_company'] =
            $history->manual_bpjs_company;
    }

    /*
    |--------------------------------------------------------------------------
    | LEVEL >=14
    |--------------------------------------------------------------------------
    |
    | Finance (manager_keuangan / super_admin) tetap boleh menyimpan
    | salary, allowance, PTKP, dan BPJS baru untuk semua level.
    |
    | Jangan menimpa input financial ketika level berubah dari <=13
    | menjadi >=14. Protection financial untuk user non-finance tetap
    | ditangani oleh blok $canEditFinancial di atas.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | ALLOWANCE OTOMATIS
    |--------------------------------------------------------------------------
    |
    | Hanya financial editor.
    |
    | GAPOK × (LEVEL × 2%)
    |
    */

    if (
        $canEditFinancial &&
        !$newLevelHigh
    ) {

        $validated['allowance'] =
            $this->calculateAllowance(
                $validated['basic_salary'],
                $validated['level']
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MANUAL BPJS OFF
    |--------------------------------------------------------------------------
    */

    if (!$validated['use_manual_bpjs']) {

        $validated['manual_bpjs_tk_employee'] = 0;

        $validated['manual_bpjs_ks_employee'] = 0;

        $validated['manual_bpjs_company'] = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | PKWT SEQUENCE
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            $validated['employment_type']
        ) === 'PKWT'
    ) {

        if (
            strtoupper(
                $history->employment_type
            ) === 'PKWT'
            &&
            $history->pkwt_sequence
        ) {

            /*
            | Tetap PKWT -> sequence tetap.
            */

            $validated['pkwt_sequence'] =
                $history->pkwt_sequence;

        } else {

            /*
            | Masuk PKWT -> sequence berikutnya.
            */

            $validated['pkwt_sequence'] =
                $this->getNextPkwtSequence(
                    $contract
                );
        }

    } else {

        $validated['pkwt_sequence'] = null;
    }

    /*
    |--------------------------------------------------------------------------
    | TERMINATION
    |--------------------------------------------------------------------------
    */

    $isTerminated =
        $this->isTerminatedEmploymentType(
            $validated['employment_type']
        );

    if ($isTerminated) {

        if (
            empty(
                $validated['exit_date']
            )
        ) {

            $validated['exit_date'] =
                now()->format('Y-m-d');
        }

        if (
            empty(
                $validated['exit_reason']
            )
        ) {

            $validated['exit_reason'] =
                $validated['employment_type'];
        }

    } else {

        $validated['exit_date'] = null;

        $validated['exit_reason'] = null;
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL SERVER-SIDE PAYLOAD
    |--------------------------------------------------------------------------
    |
    | Jangan update seluruh $request.
    | Hanya field yang memang diizinkan.
    |
    */

    $historyData = [

        'job_title' =>
            $validated['job_title'],

        'department' =>
            $validated['department'] ?? null,

        'placement_area' =>
            $validated['placement_area'] ?? null,

        'fingerprint_pin' =>
            $validated['fingerprint_pin'] ?? null,

        'nik_fingerprint' =>
            $validated['nik_fingerprint'] ?? null,

        'category' =>
            $validated['category'] ?? null,

        'level' =>
            $validated['level'] ?? null,

        'basic_salary' =>
            $validated['basic_salary'],

        'allowance' =>
            $validated['allowance'],

        'is_bpjstk_active' =>
            $validated['is_bpjstk_active'],

        'is_bpjs_health_active' =>
            $validated['is_bpjs_health_active'],

        'ptkp_status' =>
            $validated['ptkp_status'],

        'use_manual_bpjs' =>
            $validated['use_manual_bpjs'],

        'manual_bpjs_tk_employee' =>
            $validated['manual_bpjs_tk_employee'],

        'manual_bpjs_ks_employee' =>
            $validated['manual_bpjs_ks_employee'],

        'manual_bpjs_company' =>
            $validated['manual_bpjs_company'],

        'employment_type' =>
            $validated['employment_type'],

        'pkwt_sequence' =>
            $validated['pkwt_sequence'],

        'start_date' =>
            $validated['start_date'],

        'end_date' =>
            $validated['end_date'] ?? null,

        'exit_date' =>
            $validated['exit_date'] ?? null,

        'exit_reason' =>
            $validated['exit_reason'] ?? null,

        'is_active' =>
            !$isTerminated,
    ];

 /*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION + ROW LOCK
|--------------------------------------------------------------------------
*/

DB::transaction(function () use (
    $employee,
    $contract,
    $historyData,
    $isTerminated
) {

    /*
    |--------------------------------------------------------------------------
    | LOCK CONTRACT
    |--------------------------------------------------------------------------
    */

    $lockedContract = ContractOuterIsland::query()
        ->where(
            'id_contract_outer_island',
            $contract->id_contract_outer_island
        )
        ->lockForUpdate()
        ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | CURRENT HISTORY ID
    |--------------------------------------------------------------------------
    */

    $currentHistoryId =
        $lockedContract->current_contract_history_id;

    abort_unless(
        $currentHistoryId,
        409
    );


    /*
    |--------------------------------------------------------------------------
    | LOCK CURRENT HISTORY
    |--------------------------------------------------------------------------
    */

    $lockedHistory = ContractHistoryOuterIsland::query()
        ->where(
            'id_contract_history_outer_island',
            $currentHistoryId
        )
        ->lockForUpdate()
        ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | POINTER CHECK
    |--------------------------------------------------------------------------
    */

    abort_unless(
        (int) $lockedContract->current_contract_history_id ===
        (int) $lockedHistory->id_contract_history_outer_island,
        409
    );


    /*
    |--------------------------------------------------------------------------
    | UPDATE HISTORY LANGSUNG KE DATABASE
    |--------------------------------------------------------------------------
    |
    | PENTING:
    | Jangan menggunakan:
    |
    |     $lockedHistory->update($historyData);
    |
    | Untuk financial.
    |
    | Kita update langsung menggunakan Query Builder supaya
    | basic_salary dan allowance langsung masuk pada SAVE PERTAMA.
    |
    */

    DB::table('employee_contract_histories_outer_island')
        ->where(
            'id_contract_history_outer_island',
            $lockedHistory->id_contract_history_outer_island
        )
        ->update([
            'job_title' =>
                $historyData['job_title'],

            'department' =>
                $historyData['department'],

            'placement_area' =>
                $historyData['placement_area'],

            'fingerprint_pin' =>
                $historyData['fingerprint_pin'],

            'nik_fingerprint' =>
                $historyData['nik_fingerprint'],

            'category' =>
                $historyData['category'],

            'level' =>
                $historyData['level'],

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL
            |--------------------------------------------------------------------------
            */

            'basic_salary' =>
                $historyData['basic_salary'],

            'allowance' =>
                $historyData['allowance'],

            'is_bpjstk_active' =>
                $historyData['is_bpjstk_active'],

            'is_bpjs_health_active' =>
                $historyData['is_bpjs_health_active'],

            'ptkp_status' =>
                $historyData['ptkp_status'],

            'use_manual_bpjs' =>
                $historyData['use_manual_bpjs'],

            'manual_bpjs_tk_employee' =>
                $historyData['manual_bpjs_tk_employee'],

            'manual_bpjs_ks_employee' =>
                $historyData['manual_bpjs_ks_employee'],

            'manual_bpjs_company' =>
                $historyData['manual_bpjs_company'],

            /*
            |--------------------------------------------------------------------------
            | CONTRACT
            |--------------------------------------------------------------------------
            */

            'employment_type' =>
                $historyData['employment_type'],

            'pkwt_sequence' =>
                $historyData['pkwt_sequence'],

            'start_date' =>
                $historyData['start_date'],

            'end_date' =>
                $historyData['end_date'],

            'exit_date' =>
                $historyData['exit_date'],

            'exit_reason' =>
                $historyData['exit_reason'],

            'is_active' =>
                $historyData['is_active'],

            'updated_at' =>
                now(),
        ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE CONTRACT MASTER
    |--------------------------------------------------------------------------
    */

    $lockedContract->update([
        'current_contract_history_id' =>
            $lockedHistory->id_contract_history_outer_island,

        'is_active' =>
            !$isTerminated,
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE EMPLOYEE
    |--------------------------------------------------------------------------
    */

    $employee->update([
        'is_active' =>
            !$isTerminated,
    ]);
});

    /*
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    $message =
        $isTerminated

            ? 'Contract berhasil diperbarui dan karyawan menjadi '
                . $validated['employment_type']
                . ' (Non-Aktif).'

            : 'Perubahan Contract berhasil disimpan.';

    return redirect()
        ->route(
            'contracts.outer_island.index'
        )
        ->with(
            'success',
            $message
        );
}
    /*
    |--------------------------------------------------------------------------
    | 4. CREATE PERIOD
    |--------------------------------------------------------------------------
    */

    public function createPeriod(
        EmployeeOuterIsland $employee
    ) {
        return redirect()
            ->route(
                'contracts.outer_island.edit',
                $employee
            )
            ->with(
                'open_new_period',
                true
            );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. STORE NEW CONTRACT PERIOD
    |--------------------------------------------------------------------------
    */

    public function storePeriod(
        Request $request,
        EmployeeOuterIsland $employee
    ) {
        $employee->load([
            'contractMaster.currentHistory',
            'contractMaster.histories',
        ]);

        /*
        |--------------------------------------------------------------------------
        | ENSURE MASTER
        |--------------------------------------------------------------------------
        */

        $contract =
            $this->ensureContractMaster(
                $employee
            );

        $contract->load([
            'currentHistory',
            'histories',
        ]);

        $currentHistory =
            $contract->currentHistory;

        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */

        $role =
            auth()->user()?->role;

        $isFinance =
            $this->isFinanceRole($role);

        $isHeadHrd =
            $this->isHeadHrdRole($role);

        $isKeuangan =
            $role === self::KEUANGAN_ROLE;

        /*
        |--------------------------------------------------------------------------
        | CURRENT LEVEL
        |--------------------------------------------------------------------------
        */

        $currentLevel =
            $currentHistory?->level;

        $currentLevelNumber =
            is_numeric($currentLevel)
                ? (int) $currentLevel
                : null;

        $currentLevelSensitive =
            $currentLevelNumber === null
            ||
            $currentLevelNumber <= 13;

        /*
        |--------------------------------------------------------------------------
        | CLEAN RUPIAH
        |--------------------------------------------------------------------------
        */

        $request->merge([

            'basic_salary' =>
                $this->cleanRupiah(
                    $request->input(
                        'basic_salary'
                    )
                ),

            'allowance' =>
                $this->cleanRupiah(
                    $request->input(
                        'allowance'
                    )
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
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([

                'job_title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'department' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'placement_area' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'fingerprint_pin' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'nik_fingerprint' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'category' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'level' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                'basic_salary' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'allowance' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'is_bpjstk_active' => [
                    'nullable',
                    'boolean',
                ],

                'is_bpjs_health_active' => [
                    'nullable',
                    'boolean',
                ],

                'ptkp_status' => [
                    'required',
                    'string',
                    'max:10',
                ],

                'use_manual_bpjs' => [
                    'nullable',
                    'boolean',
                ],

                'manual_bpjs_tk_employee' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'manual_bpjs_ks_employee' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'manual_bpjs_company' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'employment_type' => [
                    'required',
                    'string',
                    'in:PKWT,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',
                ],

                'start_date' => [
                    'required',
                    'date',
                ],

                'end_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:start_date',
                ],

                'exit_date' => [
                    'nullable',
                    'date',
                ],

                'exit_reason' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | BPJS BUTTON NORMALIZATION
        |--------------------------------------------------------------------------
        */

        $validated['is_bpjstk_active'] =
            $request->has(
                'is_bpjstk_active'
            );

        $validated['is_bpjs_health_active'] =
            $request->has(
                'is_bpjs_health_active'
            );

        $validated['use_manual_bpjs'] =
            $request->has(
                'use_manual_bpjs'
            );

        /*
        |--------------------------------------------------------------------------
        | NEW LEVEL
        |--------------------------------------------------------------------------
        */

        $newLevel =
            $validated['level'] ?? null;

        $newLevelNumber =
            is_numeric($newLevel)
                ? (int) $newLevel
                : null;

        $newLevelHigh =
            $newLevelNumber !== null
            &&
            $newLevelNumber >= 14;

        /*
        |--------------------------------------------------------------------------
        | FINANCIAL ACCESS
        |--------------------------------------------------------------------------
        */

        $canEditFinancial =
            $isFinance
            ||
            (
                (
                    $isHeadHrd
                    ||
                    $isKeuangan
                )
                &&
                $currentLevelSensitive
            );

        /*
        |--------------------------------------------------------------------------
        | BENAR-BENAR BELUM ADA HISTORY
        |--------------------------------------------------------------------------
        */

        if (!$currentHistory) {

            /*
            | Non-finance tidak boleh membuat
            | data finansial sensitif level >=14.
            */

            if (
                !$isFinance
                &&
                $newLevelHigh
            ) {

                $validated['basic_salary'] = 0;

                $validated['allowance'] = 0;

                $validated['ptkp_status'] =
                    'TK/0';

                $validated['is_bpjstk_active'] =
                    false;

                $validated['is_bpjs_health_active'] =
                    false;

                $validated['use_manual_bpjs'] =
                    false;

                $validated['manual_bpjs_tk_employee'] = 0;

                $validated['manual_bpjs_ks_employee'] = 0;

                $validated['manual_bpjs_company'] = 0;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HRD
        |--------------------------------------------------------------------------
        */

        if (
            !$isFinance
            &&
            !$isHeadHrd
            &&
            !$isKeuangan
            &&
            $currentHistory
        ) {

            $validated['category'] =
                $currentHistory->category;

            $validated['level'] =
                $currentHistory->level;

            $validated['basic_salary'] =
                $currentHistory->basic_salary;

            $validated['allowance'] =
                $currentHistory->allowance;

            $validated['ptkp_status'] =
                $currentHistory->ptkp_status;

            $validated['is_bpjstk_active'] =
                $currentHistory->is_bpjstk_active;

            $validated['is_bpjs_health_active'] =
                $currentHistory->is_bpjs_health_active;

            $validated['use_manual_bpjs'] =
                $currentHistory->use_manual_bpjs;

            $validated['manual_bpjs_tk_employee'] =
                $currentHistory->manual_bpjs_tk_employee;

            $validated['manual_bpjs_ks_employee'] =
                $currentHistory->manual_bpjs_ks_employee;

            $validated['manual_bpjs_company'] =
                $currentHistory->manual_bpjs_company;
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT LEVEL >=14
        |--------------------------------------------------------------------------
        */

        elseif (
            !$isFinance
            &&
            !$currentLevelSensitive
            &&
            $currentHistory
        ) {

            $validated['category'] =
                $currentHistory->category;

            $validated['level'] =
                $currentHistory->level;

            $validated['basic_salary'] =
                $currentHistory->basic_salary;

            $validated['allowance'] =
                $currentHistory->allowance;

            $validated['ptkp_status'] =
                $currentHistory->ptkp_status;

            $validated['is_bpjstk_active'] =
                $currentHistory->is_bpjstk_active;

            $validated['is_bpjs_health_active'] =
                $currentHistory->is_bpjs_health_active;

            $validated['use_manual_bpjs'] =
                $currentHistory->use_manual_bpjs;

            $validated['manual_bpjs_tk_employee'] =
                $currentHistory->manual_bpjs_tk_employee;

            $validated['manual_bpjs_ks_employee'] =
                $currentHistory->manual_bpjs_ks_employee;

            $validated['manual_bpjs_company'] =
                $currentHistory->manual_bpjs_company;
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT <=13 -> NEW LEVEL >=14
        |--------------------------------------------------------------------------
        */

        if (
            $canEditFinancial
            &&
            $newLevelHigh
            &&
            $currentHistory
        ) {

            $validated['basic_salary'] =
                $currentHistory->basic_salary;

            $validated['allowance'] =
                $currentHistory->allowance;

            $validated['ptkp_status'] =
                $currentHistory->ptkp_status;

            $validated['is_bpjstk_active'] =
                $currentHistory->is_bpjstk_active;

            $validated['is_bpjs_health_active'] =
                $currentHistory->is_bpjs_health_active;

            $validated['use_manual_bpjs'] =
                $currentHistory->use_manual_bpjs;

            $validated['manual_bpjs_tk_employee'] =
                $currentHistory->manual_bpjs_tk_employee;

            $validated['manual_bpjs_ks_employee'] =
                $currentHistory->manual_bpjs_ks_employee;

            $validated['manual_bpjs_company'] =
                $currentHistory->manual_bpjs_company;
        }

        /*
        |--------------------------------------------------------------------------
        | ALLOWANCE
        |--------------------------------------------------------------------------
        */

        if (
            $canEditFinancial
            &&
            !$newLevelHigh
        ) {

            $validated['allowance'] =
                $this->calculateAllowance(
                    $validated['basic_salary'],
                    $validated['level']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | MANUAL BPJS OFF
        |--------------------------------------------------------------------------
        */

        if (
            !$validated['use_manual_bpjs']
        ) {

            $validated['manual_bpjs_tk_employee'] = 0;

            $validated['manual_bpjs_ks_employee'] = 0;

            $validated['manual_bpjs_company'] = 0;
        }

        /*
        |--------------------------------------------------------------------------
        | PKWT SEQUENCE
        |--------------------------------------------------------------------------
        */

        if (
            strtoupper(
                $validated['employment_type']
            ) === 'PKWT'
        ) {

            $validated['pkwt_sequence'] =
                $this->getNextPkwtSequence(
                    $contract
                );

        } else {

            $validated['pkwt_sequence'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | TERMINATION
        |--------------------------------------------------------------------------
        */

        $isTerminated =
            $this->isTerminatedEmploymentType(
                $validated['employment_type']
            );

        if ($isTerminated) {

            if (
                empty(
                    $validated['exit_date']
                )
            ) {

                $validated['exit_date'] =
                    now()->format('Y-m-d');
            }

            if (
                empty(
                    $validated['exit_reason']
                )
            ) {

                $validated['exit_reason'] =
                    $validated['employment_type'];
            }

        } else {

            $validated['exit_date'] = null;

            $validated['exit_reason'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE NEW HISTORY
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $contract,
                $employee,
                $currentHistory,
                $validated,
                $isTerminated
            ) {

                /*
                |----------------------------------------------------------------------
                | NONAKTIFKAN HISTORY LAMA
                |----------------------------------------------------------------------
                */

                if ($currentHistory) {

                    $currentHistory->update([
                        'is_active' => false,
                    ]);
                }

                /*
                |----------------------------------------------------------------------
                | CREATE HISTORY BARU
                |----------------------------------------------------------------------
                */

                $newHistory =
                    $contract
                        ->histories()
                        ->create(
                            array_merge(
                                $validated,
                                [
                                    'uuid' =>
                                        (string) Str::uuid(),

                                    'is_active' =>
                                        !$isTerminated,
                                ]
                            )
                        );

                /*
                |----------------------------------------------------------------------
                | UPDATE MASTER
                |----------------------------------------------------------------------
                */

                $contract->update([
                    'current_contract_history_id' =>
                        $newHistory
                            ->id_contract_history_outer_island,

                    'is_active' =>
                        !$isTerminated,
                ]);

                /*
                |----------------------------------------------------------------------
                | UPDATE EMPLOYEE
                |----------------------------------------------------------------------
                */

                $employee->update([
                    'is_active' =>
                        !$isTerminated,
                ]);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        $message =
            $isTerminated

                ? 'Contract Period baru berhasil dibuat dan karyawan menjadi '
                    . $validated['employment_type']
                    . ' (Non-Aktif).'

                : 'Contract Period baru berhasil dibuat dan menjadi Current Contract.';

        return redirect()
            ->route(
                'contracts.outer_island.index'
            )
            ->with(
                'success',
                $message
            );
    }

    /*
    |--------------------------------------------------------------------------
    | 6. SHOW HISTORY
    |--------------------------------------------------------------------------
    */

    public function showHistory(
        EmployeeOuterIsland $employee,
        ContractHistoryOuterIsland $history
    ) {
        $employee->load([
            'contractMaster.currentHistory',
            'contractMaster.histories',
        ]);

        $contract =
            $employee->contractMaster;

        abort_unless(
            $contract,
            404
        );

        /*
        |--------------------------------------------------------------------------
        | HISTORY HARUS MILIK CONTRACT EMPLOYEE
        |--------------------------------------------------------------------------
        */

        abort_unless(
            (int) $history->contract_outer_island_id
                ===
            (int) $contract->id_contract_outer_island,
            404
        );

        /*
        |--------------------------------------------------------------------------
        | CURRENT CHECK
        |--------------------------------------------------------------------------
        */

        $isCurrent =
            (int) $contract->current_contract_history_id
                ===
            (int) $history->id_contract_history_outer_island;

        /*
        |--------------------------------------------------------------------------
        | ACCESS
        |--------------------------------------------------------------------------
        */

        $canViewSensitiveData =
            $this->canViewSensitiveData(
                $history
            );

        return view(
            'contracts.outer_island.history',
            compact(
                'employee',
                'contract',
                'history',
                'isCurrent',
                'canViewSensitiveData'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function isFinanceRole(
        ?string $role
    ): bool {

        return in_array(
            $role,
            self::FINANCE_ROLES,
            true
        );
    }

    private function isHeadHrdRole(
        ?string $role
    ): bool {

        return in_array(
            $role,
            self::HEAD_HRD_ROLES,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CAN VIEW SENSITIVE DATA
    |--------------------------------------------------------------------------
    |
    | Finance:
    |     Semua level
    |
    | Head HRD:
    |     <=13
    |
    | Keuangan:
    |     <=13
    |
    | HRD:
    |     Tidak melihat finansial
    |
    */

    private function canViewSensitiveData(
        ?ContractHistoryOuterIsland $history
    ): bool {

        $role =
            auth()->user()?->role;

        /*
        |--------------------------------------------------------------------------
        | FINANCE
        |--------------------------------------------------------------------------
        */

        if (
            $this->isFinanceRole($role)
        ) {

            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | HEAD HRD
        |--------------------------------------------------------------------------
        */

        if (
            $this->isHeadHrdRole($role)
        ) {

            if (!$history) {
                return true;
            }

            if (
                $history->level === null
            ) {

                return true;
            }

            return (int) $history->level <= 13;
        }

        /*
        |--------------------------------------------------------------------------
        | KEUANGAN
        |--------------------------------------------------------------------------
        */

        if (
            $role === self::KEUANGAN_ROLE
        ) {

            if (!$history) {
                return true;
            }

            if (
                $history->level === null
            ) {

                return true;
            }

            return (int) $history->level <= 13;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | CAN EDIT CURRENT CONTRACT
    |--------------------------------------------------------------------------
    */

    private function canEditCurrentContract(
        ?ContractHistoryOuterIsland $history
    ): bool {

        if (!$history) {
            return false;
        }

        $role =
            auth()->user()?->role;

        /*
        |--------------------------------------------------------------------------
        | FINANCE
        |--------------------------------------------------------------------------
        */

        if (
            $this->isFinanceRole($role)
        ) {

            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | HEAD HRD / KEUANGAN / HRD
        |--------------------------------------------------------------------------
        */

        return in_array(
            $role,
            [
                ...self::HEAD_HRD_ROLES,
                self::KEUANGAN_ROLE,
                self::HRD_ROLE,
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE ALLOWANCE
    |--------------------------------------------------------------------------
    |
    | GAPOK × (LEVEL × 2%)
    |
    */

    private function calculateAllowance(
        $basicSalary,
        $level
    ) {

        $basicSalary =
            (float) $basicSalary;

        $level =
            $level !== null
                ? (int) $level
                : 0;

        if (
            $basicSalary <= 0
            ||
            $level <= 0
        ) {

            return 0;
        }

        return round(
            $basicSalary *
            ($level * 0.02),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAN RUPIAH
    |--------------------------------------------------------------------------
    */

    private function cleanRupiah(
        $value
    ) {

        if (
            $value === null
            ||
            $value === ''
        ) {

            return 0;
        }

        $clean =
            preg_replace(
                '/[^0-9]/',
                '',
                (string) $value
            );

        return $clean === ''
            ? 0
            : (float) $clean;
    }

    /*
    |--------------------------------------------------------------------------
    | TERMINATION
    |--------------------------------------------------------------------------
    */

    private function isTerminatedEmploymentType(
        ?string $employmentType
    ): bool {

        return in_array(
            $employmentType,
            [
                'PHK',
                'Resign',
                'Pensiun',
                'End_Contract',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ENSURE CONTRACT MASTER
    |--------------------------------------------------------------------------
    */

    private function ensureContractMaster(
        EmployeeOuterIsland $employee
    ): ContractOuterIsland {

        $contract =
            $employee->contractMaster;

        if ($contract) {

            return $contract;
        }

        return $employee
            ->contractMaster()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'is_active' => true,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | NEXT PKWT SEQUENCE
    |--------------------------------------------------------------------------
    */

    private function getNextPkwtSequence(
        ContractOuterIsland $contract
    ): int {

        $max =
            ContractHistoryOuterIsland::where(
                'contract_outer_island_id',
                $contract->id_contract_outer_island
            )
                ->where(
                    'employment_type',
                    'PKWT'
                )
                ->max(
                    'pkwt_sequence'
                );

        return ((int) $max) + 1;
    }

    private function isContractExpired(?ContractHistoryOuterIsland $history): bool
    {
        if (!$history || !$history->end_date) {
            return false;
        }

        return Carbon::today()->greaterThanOrEqualTo(
            Carbon::parse($history->end_date)
        );
    }
}