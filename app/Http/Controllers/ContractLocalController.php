<?php

namespace App\Http\Controllers;

use App\Models\Employee;

use App\Models\ContractLocal;

use App\Models\ContractHistoryLocal;
use App\Models\Payroll;
use App\Support\ContractHistoryResolver;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

use Carbon\Carbon;

class ContractLocalController extends Controller

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

    */

    public function index(Request $request)

    {

        $this->authorizeContractAccess();

        $search = trim(

            $request->get('search', '')

        );

        $employees = Employee::with([

            'contract.currentHistory',

        
            'contract.histories',
])

            ->when(

                $search !== '',

                function ($query) use ($search) {

                    $query->where(function ($q) use ($search) {

                        $q->where(

                            'full_name',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhere(

                            'nik_ktp',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhereHas(

                            'contract.currentHistory',

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

            ->oldest('id_employee')

            ->paginate(10)

            ->withQueryString();

        /*

        |--------------------------------------------------------------------------

        | HELPER DATA

        |--------------------------------------------------------------------------

        */

        $employees->getCollection()->transform(

            function ($employee) {

                $histories = $employee->contract?->histories ?? collect();
                $effectiveToday = ContractHistoryResolver::matchingDate(
                    $histories,
                    Carbon::today()
                );
                $history = $effectiveToday->count() === 1
                    ? $effectiveToday->first()
                    : null;
                $referenceHistory = ContractHistoryResolver::latest($histories);
                $futureHistory = $histories
                    ->filter(fn ($item) => $item->start_date && Carbon::parse($item->start_date)->startOfDay()->gt(Carbon::today()))
                    ->sortBy('start_date')
                    ->first();
                $latestHistory = ContractHistoryResolver::latestStartedOnOrBefore($histories, Carbon::today());
                $latestTerminationEnd = $latestHistory
                    ? ContractHistoryResolver::effectiveEnd($latestHistory)
                    : null;
                $latestIsTermination = $latestHistory
                    && $this->isTerminatedEmploymentType((string) $latestHistory->employment_type)
                    && $latestTerminationEnd
                    && $latestTerminationEnd->lt(Carbon::today());

                $currentTerminationEnd = $history
                    ? ContractHistoryResolver::effectiveEnd($history)
                    : null;
                $currentHistoryIsTermination = $history
                    && $this->isTerminatedEmploymentType((string) $history->employment_type)
                    && $currentTerminationEnd
                    && $currentTerminationEnd->lt(Carbon::today());

                if ($effectiveToday->count() > 1) {
                    $contractStatus = 'needs_review';
                } elseif (!$employee->is_active || $latestIsTermination || $currentHistoryIsTermination) {
                    $contractStatus = 'inactive';
                } elseif ($history) {
                    $contractStatus = 'active';
                } elseif ($futureHistory) {
                    $contractStatus = 'scheduled';
                } elseif ($referenceHistory) {
                    $contractStatus = 'pending_decision';
                } else {
                    $contractStatus = 'no_history';
                }

                // Informasi history terakhir boleh ditampilkan sebagai referensi,
                // tetapi tidak pernah dijadikan current contract jika tanggalnya sudah berakhir.
                $employee->current_contract_history = $history;
                $employee->contract_reference_history = $referenceHistory;
                $employee->contract_status = $contractStatus;

                $employee->current_contract_level =

                    $history?->level;

                $employee->has_contract_master =

                    $employee->contract !== null;

                $employee->has_current_contract =

                    $history !== null;

                $employee->can_view_sensitive_data =

                    $this->canViewSensitiveData(

                        $referenceHistory

                    );

                return $employee;

            }

        );

        return view(

            'contracts.local.index',

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

    public function edit(Employee $employee)

    {

        $this->authorizeContractAccess();

        $employee->load([

            'contract.currentHistory',

            'contract.histories',

        ]);

        $contract =

            $employee->contract;

        $histories =

            $contract?->histories ?? collect();

        $history = ContractHistoryResolver::uniqueForDate(
            $histories,
            Carbon::today()
        );
        $referenceHistory = $history ?? ContractHistoryResolver::latest($histories);
        $hasFutureHistory = $histories->contains(function ($item): bool {
            return $item->start_date
                && Carbon::parse($item->start_date)->startOfDay()->gt(Carbon::today());
        });
        $todayMatches = ContractHistoryResolver::matchingDate($histories, Carbon::today());

        $latestStartedHistory = ContractHistoryResolver::latestStartedOnOrBefore($histories, Carbon::today());
        $historyEnd = $history ? ContractHistoryResolver::effectiveEnd($history) : null;
        $latestStartedEnd = $latestStartedHistory
            ? ContractHistoryResolver::effectiveEnd($latestStartedHistory)
            : null;
        $historyIsTermination = $history
            && $this->isTerminatedEmploymentType((string) $history->employment_type)
            && $historyEnd
            && $historyEnd->lt(Carbon::today());
        $latestIsTermination = $latestStartedHistory
            && $this->isTerminatedEmploymentType((string) $latestStartedHistory->employment_type)
            && $latestStartedEnd
            && $latestStartedEnd->lt(Carbon::today());

        if ($todayMatches->count() > 1) {
            $contractStatus = 'needs_review';
        } elseif (!$employee->is_active || $historyIsTermination || $latestIsTermination) {
            $contractStatus = 'inactive';
        } elseif ($history) {
            $contractStatus = 'active';
        } elseif ($hasFutureHistory) {
            $contractStatus = 'scheduled';
        } elseif ($referenceHistory) {
            $contractStatus = 'pending_decision';
        } else {
            $contractStatus = 'no_history';
        }

        /*

        |--------------------------------------------------------------------------

        | HISTORY TERBARU DI ATAS

        |--------------------------------------------------------------------------

        */

        $histories = $histories

            ->sortByDesc(

                'id_contract_history'

            )

            ->values();

        /*

        |--------------------------------------------------------------------------

        | ACCESS

        |--------------------------------------------------------------------------

        */

        $canViewSensitiveData =

            $this->canViewSensitiveData(

                $referenceHistory

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

            $referenceHistory?->level;

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

        $employmentStatusOptions =

            $this->employmentStatusOptions();

        $terOptions = ['A', 'B', 'C'];

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

            'contracts.local.contract',

            compact(

                'employee',

                'contract',

                'history',

                'referenceHistory',

                'contractStatus',

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

                'maskFinancial',

                'employmentStatusOptions',

                'terOptions'

            )

        );

    }

    /*

    |--------------------------------------------------------------------------

    | 3. UPDATE CURRENT CONTRACT

    |--------------------------------------------------------------------------

    |

    | Update current history.

    |

    | Tidak membuat history baru.

    |

    */

    public function update(

        Request $request,

        Employee $employee

    ) {

        $this->authorizeContractAccess();

        /*

        |--------------------------------------------------------------------------

        | LOAD EMPLOYEE + CONTRACT

        |--------------------------------------------------------------------------

        */

        $employee->load([

            'contract.currentHistory',

        
            'contract.histories',
]);

        $contract =

            $employee->contract;

        if (!$contract) {

            return redirect()

                ->route('contracts.local.index')

                ->with(

                    'error',

                    'Karyawan ini belum memiliki Contract Master.'

                );

        }

        $history = ContractHistoryResolver::uniqueForDate(
            $contract->histories,
            Carbon::today()
        );

        if (!$history) {

            return redirect()

                ->route('contracts.local.index')

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

        $role =

            auth()->user()?->role;

        $isFinance =

            $this->isFinanceRole($role);

        $isHeadHrd =

            $this->isHeadHrdRole($role);

        $isKeuangan =

            $role === self::KEUANGAN_ROLE;

        $isHrd =

            $role === self::HRD_ROLE;

        /*

        |--------------------------------------------------------------------------

        | AUTHORIZE

        |--------------------------------------------------------------------------

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

        */

        abort_unless((int) $history->contract_id === (int) $contract->id_contract, 404);

        /*

        |--------------------------------------------------------------------------

        | OLD LEVEL

        |--------------------------------------------------------------------------

        */

        $oldLevelNumber =

            is_numeric($history->level)

                ? (int) $history->level

                : null;

        /*

        |--------------------------------------------------------------------------

        | OLD SENSITIVE DATA

        |--------------------------------------------------------------------------

        */

        $oldBasicSalary =

            $history->basic_salary ?? 0;

        $oldAllowance =

            $history->allowance ?? 0;

        $oldPtkpStatus =

            $this->normalizePtkpCode($history->ptkp_status ?? null)

                ?? 'TK0';

        $oldTerCategory =

            in_array(strtoupper(trim((string) ($history->ter_category ?? ''))), ['A', 'B', 'C'], true)

                ? strtoupper(trim((string) $history->ter_category))

                : null;

        $oldUseManualBpjs =

            $history->use_manual_bpjs ?? false;

        $oldManualBpjsTkEmployee =

            $history->manual_bpjs_tk_employee ?? 0;

        $oldManualBpjsKsEmployee =

            $history->manual_bpjs_ks_employee ?? 0;

        $oldManualBpjsCompany =

            $history->manual_bpjs_company ?? 0;

        $oldLevel =

            $history->level;

        $oldCategory =

            $history->category;

        /*

        |--------------------------------------------------------------------------

        | HEAD HRD CURRENT LEVEL > 13

        |--------------------------------------------------------------------------

        |

        | Head HRD tetap boleh mengubah data operasional,

        | tetapi data finansial dan level/category yang sudah

        | tersimpan tetap dilindungi.

        |

        */

        if (

            $role === 'head_hrd'

            &&

            !is_null($oldLevelNumber)

            &&

            $oldLevelNumber > 13

        ) {

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

                'ter_category' =>

                    $oldTerCategory,

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

        | HRD

        |--------------------------------------------------------------------------

        |

        | HRD tidak boleh mengubah data finansial.

        |

        */

        if ($role === self::HRD_ROLE) {
            // HRD boleh mengubah PTKP hanya untuk level 13 ke bawah.
            if ($oldLevelNumber === null || $oldLevelNumber > 13) {
                $request->merge(['ptkp_status' => $oldPtkpStatus]);
            }

            $request->merge([

                'basic_salary' =>

                    $oldBasicSalary,

                'allowance' =>

                    $oldAllowance,

                'ter_category' =>

                    $oldTerCategory,

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

        | VALIDATION

        |--------------------------------------------------------------------------

        */

        $validated = $request->validate([

            'nik_fingerprint' =>

                'nullable|string|max:255',

            'fingerprint_pin' =>

                'nullable|string|max:255',

            'job_title' =>

                'required|string|max:255',

            'department' =>

                'nullable|string|max:255',

            'placement_area' =>

                'nullable|string|max:255',

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

            'ptkp_status' =>

                'required|string|max:10',

            'ter_category' =>

                'nullable|string|in:A,B,C',

            'use_manual_bpjs' =>

                'nullable|boolean',

            'manual_bpjs_tk_employee' =>

                'nullable|numeric|min:0',

            'manual_bpjs_ks_employee' =>

                'nullable|numeric|min:0',

            'manual_bpjs_company' =>

                'nullable|numeric|min:0',

            'employment_type' =>

                'required|string|in:PKWT,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',

            'pkwt_sequence' =>

                'nullable|integer|min:1|max:7',

            'start_date' =>

                'required|date',

            'end_date' =>

                'nullable|date|after_or_equal:start_date',

            'exit_date' =>

                'nullable|date|after_or_equal:start_date',

            'exit_reason' =>

                'nullable|string|max:500',

        ]);

        $validated['ptkp_status'] =

            $this->normalizePtkpCode($validated['ptkp_status'] ?? null);

        if ($validated['ptkp_status'] === null) {

            return back()

                ->withInput()

                ->withErrors([

                    'ptkp_status' =>

                        'PTKP Status tidak valid. Gunakan kode Excel seperti TK0, TK01, TK02, TK03, K01, K02, K03, atau K04.',

                ]);

        }

        $validated['ter_category'] =

            in_array(strtoupper(trim((string) ($validated['ter_category'] ?? ''))), ['A', 'B', 'C'], true)

                ? strtoupper(trim((string) $validated['ter_category']))

                : null;

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

        | NEW LEVEL

        |--------------------------------------------------------------------------

        */

        $newLevel =

            $validated['level'] ?? null;

        /*

        |--------------------------------------------------------------------------

        | HEAD HRD SAVING ABOVE 13

        |--------------------------------------------------------------------------

        |

        | Contoh:

        |

        | Level 12 → Level 15

        |

        | Level 15 boleh disimpan,

        | tetapi financial tetap menggunakan nilai lama.

        |

        */

        $headHrdSavingAbove13 =

            $role === 'head_hrd'

            &&

            !is_null($newLevel)

            &&

            $newLevel > 13;

        if ($headHrdSavingAbove13) {

            $validated['basic_salary'] =

                $oldBasicSalary;

            $validated['allowance'] =

                $oldAllowance;

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

        | ALLOWANCE

        |--------------------------------------------------------------------------

        |

        | GAPOK × (LEVEL × 2%)

        |

        */

        if (!$headHrdSavingAbove13) {

            if (

                $isFinance

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

        | MANUAL BPJS OFF

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

        | PKWT SEQUENCE

        |--------------------------------------------------------------------------

        |

        | Status Karyawan di UI menentukan sequence.

        | PKWT tetap disimpan sebagai PKWT + nomor 1..7.

        |

        */

        if (

            strtoupper(

                $validated['employment_type']

            ) === 'PKWT'

        ) {

            $validated['pkwt_sequence'] =

                !empty($validated['pkwt_sequence'])

                    ? (int) $validated['pkwt_sequence']

                    : ($history->pkwt_sequence ?? $this->getNextPkwtSequence($contract));

            $duplicateSequence = ContractHistoryLocal::query()

                ->where('contract_id', $contract->id_contract)

                ->where('id_contract_history', '!=', $history->id_contract_history)

                ->where('employment_type', 'PKWT')

                ->where('pkwt_sequence', $validated['pkwt_sequence'])

                ->exists();

            if ($duplicateSequence) {

                return back()

                    ->withInput()

                    ->withErrors([

                        'pkwt_sequence' =>

                            'Nomor kontrak tersebut sudah digunakan pada riwayat kontrak yang lain.',

                    ]);

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

        | ACTIVE STATUS

        |--------------------------------------------------------------------------

        */

        $validated['is_active'] =

            !$isTerminated;

        $protectedHistoryFields = [
            'job_title', 'department', 'placement_area', 'category', 'level',
            'basic_salary', 'allowance', 'is_bpjstk_active', 'is_bpjs_health_active',
            'ptkp_status', 'ter_category', 'use_manual_bpjs',
            'manual_bpjs_tk_employee', 'manual_bpjs_ks_employee', 'manual_bpjs_company',
            'employment_type', 'pkwt_sequence', 'start_date', 'end_date', 'exit_date',
        ];

        $historyChangesPayrollBasis = collect($protectedHistoryFields)->contains(
            function (string $field) use ($history, $validated): bool {
                if (!array_key_exists($field, $validated)) {
                    return false;
                }

                $before = $history->{$field} ?? null;
                $after = $validated[$field] ?? null;

                if (in_array($field, ['basic_salary', 'allowance', 'manual_bpjs_tk_employee', 'manual_bpjs_ks_employee', 'manual_bpjs_company'], true)) {
                    return round((float) $before, 2) !== round((float) $after, 2);
                }

                if (in_array($field, ['is_bpjstk_active', 'is_bpjs_health_active', 'use_manual_bpjs'], true)) {
                    return (int) (bool) $before !== (int) (bool) $after;
                }

                return trim((string) ($before ?? '')) !== trim((string) ($after ?? ''));
            }
        );

        if ($historyChangesPayrollBasis) {
            $historyStartMonth = Carbon::parse($history->start_date)->format('Y-m');
            $historyEndDate = $history->end_date
                ? Carbon::parse($history->end_date)
                : Carbon::today();
            if ($history->exit_date) {
                $exitDate = Carbon::parse($history->exit_date);
                if ($exitDate->lt($historyEndDate)) {
                    $historyEndDate = $exitDate;
                }
            }
            $historyEndMonth = $historyEndDate->format('Y-m');

            $hasLockedPayroll = Payroll::query()
                ->where('employee_id', $employee->id_employee)
                ->where('is_locked', true)
                ->whereBetween('period_month', [$historyStartMonth, $historyEndMonth])
                ->exists();

            if ($hasLockedPayroll) {
                return back()->withInput()->withErrors([
                    'contract' => 'History ini terkait payroll yang sudah terkunci. Koreksi data periode tersebut hanya boleh dilakukan melalui prosedur unlock resmi atau dibuat sebagai history baru dengan tanggal efektif yang benar.',
                ]);
            }
        }

        /*

        |--------------------------------------------------------------------------

        | UPDATE CURRENT HISTORY

        |--------------------------------------------------------------------------

        */

        DB::transaction(

            function () use (

                $employee,

                $contract,

                $history,

                $validated,

                $isTerminated

            ) {

                $history->update($validated);

                // Recalculate current pointer from effective dates after an edit.
                $this->syncCurrentContractPointer($contract, $employee);

            }

        );

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

                'contracts.local.index'

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

        Employee $employee

    ) {

        $this->authorizeContractAccess();

        return redirect()

            ->route(

                'contracts.local.edit',

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

        Employee $employee

    ) {

        $this->authorizeContractAccess();

        $employee->load([

            'contract.currentHistory',

            
            'contract.histories',

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

        $currentHistory = ContractHistoryResolver::uniqueForDate(
            $contract->histories,
            Carbon::today()
        );
        $requestedStartDate = $request->input('start_date');
        try {
            $requestedStartDate = $requestedStartDate
                ? Carbon::parse($requestedStartDate)->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            $requestedStartDate = Carbon::today()->toDateString();
        }
        $referenceHistory = ContractHistoryResolver::latestStartedOnOrBefore(
            $contract->histories,
            $requestedStartDate
        );

        // Kontrak pertama hanya ketika belum ada history sama sekali.
        // Current pointer NULL setelah kontrak berakhir tidak berarti kontrak pertama.
        $isInitialContract = $contract->histories->isEmpty();

        // A newly appended history may not predate the first recorded history.
        // Such a correction belongs in the explicit history-correction flow,
        // not in a new renewal/adjustment that would inherit a future record.
        if (!$isInitialContract && !$referenceHistory) {
            return back()->withInput()->withErrors([
                'start_date' => 'Tanggal mulai history baru lebih awal daripada seluruh history yang tercatat. Gunakan koreksi history yang sudah ada, bukan menambahkan periode baru.',
            ]);
        }

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

        $isHrd =

            $role === self::HRD_ROLE;

        abort_unless(

            $isFinance ||

            $isHeadHrd ||

            $isKeuangan ||

            $isHrd,

            403

        );

        /*

        |--------------------------------------------------------------------------

        | CURRENT LEVEL

        |--------------------------------------------------------------------------

        */

        $currentLevel =

            $referenceHistory?->level;

        $currentLevelNumber =

            is_numeric($currentLevel)

                ? (int) $currentLevel

                : null;

        /*

        |--------------------------------------------------------------------------

        | OLD FINANCIAL DATA

        |--------------------------------------------------------------------------

        */

        $oldBasicSalary =

            $referenceHistory?->basic_salary ?? 0;

        $oldAllowance =

            $referenceHistory?->allowance ?? 0;

        $oldPtkpStatus =

            $this->normalizePtkpCode($referenceHistory?->ptkp_status ?? null)

                ?? 'TK0';

        $oldTerCategory =

            in_array(strtoupper(trim((string) ($referenceHistory?->ter_category ?? ''))), ['A', 'B', 'C'], true)

                ? strtoupper(trim((string) $referenceHistory?->ter_category))

                : null;

        $oldUseManualBpjs =

            $referenceHistory?->use_manual_bpjs ?? false;

        $oldManualBpjsTkEmployee =

            $referenceHistory?->manual_bpjs_tk_employee ?? 0;

        $oldManualBpjsKsEmployee =

            $referenceHistory?->manual_bpjs_ks_employee ?? 0;

        $oldManualBpjsCompany =

            $referenceHistory?->manual_bpjs_company ?? 0;

        $oldCategory =

            $referenceHistory?->category;

        $oldLevel =

            $referenceHistory?->level;

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

        | VALIDATION

        |--------------------------------------------------------------------------

        */

        $validated = $request->validate([

            'change_type' => 'required|string|in:initial_contract,contract_renewal,level_up,level_down,salary_adjustment,job_promotion,job_demotion,position_change,reward,punishment',
            'change_reason' => 'nullable|string|max:1000|required_unless:change_type,initial_contract,contract_renewal',

            'nik_fingerprint' =>

                'nullable|string|max:255',

            'fingerprint_pin' =>

                'nullable|string|max:255',

            'job_title' =>

                'required|string|max:255',

            'department' =>

                'nullable|string|max:255',

            'placement_area' =>

                'nullable|string|max:255',

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

            'ptkp_status' =>

                'required|string|max:10',

            'ter_category' =>

                'nullable|string|in:A,B,C',

            'use_manual_bpjs' =>

                'nullable|boolean',

            'manual_bpjs_tk_employee' =>

                'nullable|numeric|min:0',

            'manual_bpjs_ks_employee' =>

                'nullable|numeric|min:0',

            'manual_bpjs_company' =>

                'nullable|numeric|min:0',

            'employment_type' =>

                'required|string|in:PKWT,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',

            'pkwt_sequence' =>

                'nullable|integer|min:1|max:7',

            'start_date' =>

                'required|date',

            'end_date' =>

                'nullable|date|after_or_equal:start_date',

            'exit_date' =>

                'nullable|date|after_or_equal:start_date',

            'exit_reason' =>

                'nullable|string|max:500',

        ]);

        $validated['change_type'] = trim((string) $validated['change_type']);
        $validated['change_reason'] = isset($validated['change_reason'])
            ? trim((string) $validated['change_reason'])
            : null;

        if ($validated['change_type'] === 'initial_contract' && !$isInitialContract) {
            return back()->withInput()->withErrors([
                'change_type' => 'Kontrak pertama hanya boleh dibuat jika karyawan belum memiliki history kontrak.',
            ]);
        }

        if ($validated['change_type'] !== 'initial_contract' && $isInitialContract) {
            return back()->withInput()->withErrors([
                'change_type' => 'Pilih Kontrak Pertama karena belum ada history kontrak sebelumnya.',
            ]);
        }

        if ($referenceHistory
            && $this->isTerminatedEmploymentType((string) $referenceHistory->employment_type)
            && !in_array($validated['change_type'], ['contract_renewal'], true)) {
            return back()->withInput()->withErrors([
                'change_type' => 'History terakhir adalah terminasi. Untuk memulai hubungan kerja kembali, buat periode kontrak baru sesuai keputusan HRD; jangan gunakan perubahan level, gaji, reward, atau punishment pada history terminasi.',
            ]);
        }

        $validated['ptkp_status'] =

            $this->normalizePtkpCode($validated['ptkp_status'] ?? null);

        if ($validated['ptkp_status'] === null) {

            return back()

                ->withInput()

                ->withErrors([

                    'ptkp_status' =>

                        'PTKP Status tidak valid. Gunakan kode Excel seperti TK0, TK01, TK02, TK03, K01, K02, K03, atau K04.',

                ]);

        }

        $validated['ter_category'] =

            in_array(strtoupper(trim((string) ($validated['ter_category'] ?? ''))), ['A', 'B', 'C'], true)

                ? strtoupper(trim((string) $validated['ter_category']))

                : null;

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

        | NEW LEVEL

        |--------------------------------------------------------------------------

        */

        $newLevel =

            $validated['level'] ?? null;

        // HRD boleh mengisi level untuk kontrak pertama, tetapi dibatasi level 1-13.
        $hrdCanSetInitialLevel =
            $isHrd &&
            $isInitialContract &&
            is_numeric($newLevel) &&
            (int) $newLevel >= 1 &&
            (int) $newLevel <= 13;

        if ($isHrd && $isInitialContract && is_numeric($newLevel) && (int) $newLevel > 13) {
            return back()
                ->withInput()
                ->withErrors([
                    'level' => 'HRD hanya dapat mengisi level 1 sampai 13 untuk kontrak pertama. Level di atas 13 harus diproses oleh Head HRD atau Manager Keuangan.',
                ]);
        }

        /*

        |--------------------------------------------------------------------------

        | HEAD HRD LEVEL > 13

        |--------------------------------------------------------------------------

        |

        | Level baru > 13 boleh disimpan,

        | tetapi financial menggunakan nilai lama.

        |

        */

        $headHrdSavingAbove13 =

            $role === 'head_hrd'

            &&

            !is_null($newLevel)

            &&

            $newLevel > 13;

        if ($headHrdSavingAbove13) {

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

        | HRD

        |--------------------------------------------------------------------------

        */

        if ($isHrd) {
            // Untuk kontrak pertama tanpa riwayat, HRD boleh mengisi level 1-13 dan PTKP.
            // Setelah history ada, izin kembali mengikuti level yang sudah tersimpan.
            if (!$hrdCanSetInitialLevel && (!is_numeric($oldLevel) || (int) $oldLevel > 13)) {
                $validated['ptkp_status'] = $oldPtkpStatus;
            }

            $validated['basic_salary'] =

                $oldBasicSalary;

            $validated['allowance'] =

                $oldAllowance;

            if (!$hrdCanSetInitialLevel) {
                $validated['level'] = $oldLevel;
                $validated['category'] = $oldCategory;
            }

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

        | ALLOWANCE

        |--------------------------------------------------------------------------

        */

        if (!$headHrdSavingAbove13) {

            if (

                $isFinance

                ||

                (

                    $isHeadHrd

                    &&

                    (

                        is_null($newLevel)

                        ||

                        $newLevel <= 13

                    )

                )

                ||

                $isKeuangan

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

        | MANUAL BPJS OFF

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

        // Perubahan non-renewal bukan status kerja/sequence baru. Inherit data
        // kontrak dari history pembanding sebelum validasi terminasi dan PKWT.
        if ($referenceHistory && !in_array($validated['change_type'], ['contract_renewal', 'initial_contract'], true)) {
            $validated['employment_type'] = $referenceHistory->employment_type;
            $validated['pkwt_sequence'] = $referenceHistory->pkwt_sequence;
            $validated['exit_date'] = null;
            $validated['exit_reason'] = null;
        }

        /*

        |--------------------------------------------------------------------------

        | PKWT SEQUENCE

        |--------------------------------------------------------------------------

        |

        | Sequence dari Status Karyawan UI dihormati.

        | Bila tidak dikirim, gunakan sequence berikutnya.

        |

        */

        if (strtoupper((string) $validated['employment_type']) === 'PKWT') {
            if (in_array($validated['change_type'], ['initial_contract', 'contract_renewal'], true)) {
                $validated['pkwt_sequence'] = !empty($validated['pkwt_sequence'])
                    ? (int) $validated['pkwt_sequence']
                    : $this->getNextPkwtSequence($contract);

                $duplicateSequence = ContractHistoryLocal::query()
                    ->where('contract_id', $contract->id_contract)
                    ->where('employment_type', 'PKWT')
                    ->where('pkwt_sequence', $validated['pkwt_sequence'])
                    ->exists();

                if ($duplicateSequence) {
                    return back()->withInput()->withErrors([
                        'pkwt_sequence' => 'Nomor kontrak tersebut sudah digunakan. Pilih nomor kontrak berikutnya.',
                    ]);
                }
            } else {
                // Reward, punishment, level/salary/position changes retain the
                // same contract sequence; they are not a new PKWT renewal.
                $validated['pkwt_sequence'] = $referenceHistory?->pkwt_sequence;
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

                    Carbon::parse($validated['start_date'])->format('Y-m-d');

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

        if ($referenceHistory) {
            $oldLevelValue = is_numeric($referenceHistory->level) ? (int) $referenceHistory->level : null;
            $newLevelValue = is_numeric($validated['level'] ?? null) ? (int) $validated['level'] : null;
            $oldSalaryValue = (float) ($referenceHistory->basic_salary ?? 0);
            $newSalaryValue = (float) ($validated['basic_salary'] ?? 0);
            $levelDelta = ($oldLevelValue !== null && $newLevelValue !== null) ? ($newLevelValue - $oldLevelValue) : 0;
            $salaryDelta = round($newSalaryValue - $oldSalaryValue, 2);

            switch ($validated['change_type']) {
                case 'level_up':
                    if ($oldLevelValue === null || $newLevelValue === null || $levelDelta <= 0 || $salaryDelta < 0) {
                        return back()->withInput()->withErrors(['level' => 'Naik level harus menggunakan level lebih tinggi dan gaji pokok tidak boleh lebih rendah dari sebelumnya.']);
                    }
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['department'] = $referenceHistory->department;
                    $validated['placement_area'] = $referenceHistory->placement_area;
                    break;

                case 'level_down':
                    if ($oldLevelValue === null || $newLevelValue === null || $levelDelta >= 0 || $salaryDelta > 0) {
                        return back()->withInput()->withErrors(['level' => 'Turun level harus menggunakan level lebih rendah dan gaji pokok tidak boleh lebih tinggi dari sebelumnya.']);
                    }
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['department'] = $referenceHistory->department;
                    $validated['placement_area'] = $referenceHistory->placement_area;
                    break;

                case 'salary_adjustment':
                    if ($salaryDelta == 0.0) {
                        return back()->withInput()->withErrors(['basic_salary' => 'Penyesuaian gaji harus memiliki nilai gaji pokok yang berbeda.']);
                    }
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['level'] = $referenceHistory->level;
                    $validated['category'] = $referenceHistory->category;
                    $validated['department'] = $referenceHistory->department;
                    $validated['placement_area'] = $referenceHistory->placement_area;
                    $validated['ptkp_status'] = $referenceHistory->ptkp_status;
                    break;

                case 'position_change':
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['level'] = $referenceHistory->level;
                    $validated['category'] = $referenceHistory->category;
                    $validated['basic_salary'] = $referenceHistory->basic_salary;
                    $validated['allowance'] = $referenceHistory->allowance;
                    $validated['ptkp_status'] = $referenceHistory->ptkp_status;
                    break;

                case 'reward':
                    if (($levelDelta < 0) || ($salaryDelta < 0) || ($levelDelta <= 0 && $salaryDelta <= 0)) {
                        return back()->withInput()->withErrors(['change_reason' => 'Reward harus mencatat kenaikan level dan/atau gaji; nilai lainnya tidak boleh turun.']);
                    }
                    $validated['level'] = $newLevelValue ?? $referenceHistory->level;
                    $validated['category'] = $validated['category'] ?? $referenceHistory->category;
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['department'] = $referenceHistory->department;
                    $validated['placement_area'] = $referenceHistory->placement_area;
                    $validated['ptkp_status'] = $referenceHistory->ptkp_status;
                    break;

                case 'punishment':
                    if (($levelDelta > 0) || ($salaryDelta > 0) || ($levelDelta >= 0 && $salaryDelta >= 0)) {
                        return back()->withInput()->withErrors(['change_reason' => 'Punishment harus mencatat penurunan level dan/atau gaji; nilai lainnya tidak boleh naik.']);
                    }
                    $validated['level'] = $newLevelValue ?? $referenceHistory->level;
                    $validated['category'] = $validated['category'] ?? $referenceHistory->category;
                    $validated['job_title'] = $referenceHistory->job_title;
                    $validated['department'] = $referenceHistory->department;
                    $validated['placement_area'] = $referenceHistory->placement_area;
                    $validated['ptkp_status'] = $referenceHistory->ptkp_status;
                    break;

                case 'job_promotion':
                case 'job_demotion':
                    // Jabatan baru harus disebut secara eksplisit; level/kompensasi
                    // mengikuti keputusan yang diinput, bukan persentase buatan sistem.
                    if (trim((string) $validated['job_title']) === trim((string) $referenceHistory->job_title)) {
                        return back()->withInput()->withErrors(['job_title' => 'Perubahan promosi/demosi harus mencatat jabatan baru.']);
                    }
                    break;
            }

            // Recalculate the existing company allowance formula only after
            // the final level/salary values for the event have been resolved.
            // This prevents salary-only Reward/Punishment from producing a
            // zero allowance when the level input is intentionally left blank.
            $allowanceRecalculationTypes = [
                'level_up', 'level_down', 'salary_adjustment',
                'job_promotion', 'job_demotion', 'reward', 'punishment',
            ];
            $finalLevel = $validated['level'] ?? null;
            if (
                in_array($validated['change_type'], $allowanceRecalculationTypes, true)
                && !$headHrdSavingAbove13
                && ($isFinance || $isKeuangan || ($isHeadHrd && (is_null($finalLevel) || (int) $finalLevel <= 13)))
            ) {
                $validated['allowance'] = $this->calculateAllowance(
                    $validated['basic_salary'],
                    $finalLevel
                );
            }

            // Penyesuaian gaji, perubahan level, promosi/demosi, reward,
            // punishment dan perubahan posisi bukan kontrak PKWT baru.
            if ($validated['change_type'] !== 'contract_renewal') {
                $validated['employment_type'] = $referenceHistory->employment_type;
                $validated['pkwt_sequence'] = $referenceHistory->pkwt_sequence;
            }
        }

        if ($validated['change_type'] === 'contract_renewal'
            && ($validated['employment_type'] ?? null) === 'PKWT') {
            $expectedSequence = $this->getNextPkwtSequence($contract);
            if ((int) ($validated['pkwt_sequence'] ?? 0) !== $expectedSequence) {
                return back()->withInput()->withErrors([
                    'pkwt_sequence' => "Perpanjangan PKWT harus menggunakan urutan berikutnya, yaitu kontrak {$expectedSequence}.",
                ]);
            }
        }

        /*

        |--------------------------------------------------------------------------

        | CREATE HISTORY

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

                |--------------------------------------------------------------------------

                | NONAKTIFKAN HISTORY LAMA

                |--------------------------------------------------------------------------

                */

                $lockedContract = ContractLocal::query()
                    ->whereKey($contract->id_contract)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedHistories = $lockedContract->histories()
                    ->orderBy('start_date')
                    ->lockForUpdate()
                    ->get();

                $sameStartExists = $lockedHistories->contains(function ($item) use ($validated) {
                    return $item->start_date
                        && Carbon::parse($item->start_date)->toDateString() === Carbon::parse($validated['start_date'])->toDateString();
                });
                if ($sameStartExists) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'start_date' => 'Sudah ada history kontrak pada tanggal mulai yang sama. Periksa dan koreksi tanggal efektif.',
                    ]);
                }

                $effectiveMonth = Carbon::parse($validated['start_date'])->format('Y-m');
                $effectiveEndCandidates = array_values(array_filter([
                    $validated['end_date'] ?? null,
                    $validated['exit_date'] ?? null,
                ]));
                $effectiveEndDate = $effectiveEndCandidates === []
                    ? null
                    : min(array_map(
                        static fn ($date) => Carbon::parse($date)->toDateString(),
                        $effectiveEndCandidates
                    ));

                // A new history affects locked payroll only within its own
                // effective interval. Older periods before start_date remain
                // attached to the previous history and must not block future
                // contract scheduling.
                $lockedPayrollQuery = Payroll::query()
                    ->where('employee_id', $employee->id_employee)
                    ->where('is_locked', true)
                    ->where('period_month', '>=', $effectiveMonth);

                if ($effectiveEndDate !== null) {
                    $lockedPayrollQuery->where('period_month', '<=', Carbon::parse($effectiveEndDate)->format('Y-m'));
                }

                if ($lockedPayrollQuery->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'start_date' => 'Periode Contract History yang akan dibuat beririsan dengan payroll terkunci. Gunakan prosedur unlock resmi sebelum menambahkan atau mengubah history pada periode tersebut.',
                    ]);
                }

                $lockedContract->histories()->create(array_merge($validated, [
                    'uuid' => (string) Str::uuid(),
                    'is_active' => false,
                ]));

                // Setiap history berikutnya menutup periode sebelumnya sehari
                // sebelum tanggal efektif, hanya bila periode lama masih terbuka
                // atau end_date-nya melewati tanggal history berikutnya.
                $this->normalizeContractTimeline($lockedContract);
                $this->syncCurrentContractPointer($lockedContract, $employee);

            }

        );

        /*

        |--------------------------------------------------------------------------

        | MESSAGE

        |--------------------------------------------------------------------------

        */

        $startsInFuture = Carbon::parse($validated['start_date'])
            ->startOfDay()
            ->gt(Carbon::today());

        $message = $isTerminated
            ? 'Contract Period baru berhasil dibuat. Status karyawan mengikuti tanggal efektif terminasi.'
            : ($startsInFuture
                ? 'Contract Period berhasil dibuat sebagai jadwal kontrak. Current Contract yang masih berlaku tidak diganti.'
                : 'Contract Period berhasil dibuat. Current Contract disinkronkan berdasarkan tanggal berlaku.');

        return redirect()

            ->route(

                'contracts.local.index'

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

        Employee $employee,

        ContractHistoryLocal $history

    ) {

        $this->authorizeContractAccess();

        $employee->load([

            'contract.currentHistory',

            'contract.histories',

        ]);

        $contract =

            $employee->contract;

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

            (int) $history->contract_id ===

            (int) $contract->id_contract,

            404

        );

        /*

        |--------------------------------------------------------------------------

        | CURRENT CHECK

        |--------------------------------------------------------------------------

        */

        $isCurrent =

            (int) $contract->current_contract_history_id ===

            (int) $history->id_contract_history;

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

            'contracts.local.history',

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

    private function authorizeContractAccess(): void

    {

        $role = auth()->user()?->role;

        abort_unless(

            in_array($role, [

                ...self::FINANCE_ROLES,

                ...self::HEAD_HRD_ROLES,

                self::KEUANGAN_ROLE,

                self::HRD_ROLE,

            ], true),

            403

        );

    }

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

        ?ContractHistoryLocal $history

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

        /*

        |--------------------------------------------------------------------------

        | HRD

        |--------------------------------------------------------------------------

        */

        return false;

    }

    /*

    |--------------------------------------------------------------------------

    | CAN EDIT CURRENT CONTRACT

    |--------------------------------------------------------------------------

    */

    private function canEditCurrentContract(

        ?ContractHistoryLocal $history

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

    | PTKP / EMPLOYMENT MAPPING

    |--------------------------------------------------------------------------

    */

    private function normalizePtkpCode(mixed $value): ?string

    {

        $raw = strtoupper(trim((string) ($value ?? '')));

        // Bersihkan spasi biasa dan non-breaking space dari input/browser.

        $raw = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $raw);

        $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;

        $raw = trim($raw);

        if ($raw === '' || $raw === '0') {

            return 'TK0';

        }

        /*

        |--------------------------------------------------------------------------

        | Format PTKP yang diterima

        |--------------------------------------------------------------------------

        |

        | TK0, TK1, TK2, TK3, TK4

        | TK01, TK02, TK03, TK04

        | TK/0, TK/1, TK/2, TK/3, TK/4

        |

        | K0, K1, K2, K3

        | K01, K02, K03, K04

        | K/0, K/1, K/2, K/3

        |

        | Penyimpanan:

        | TK0 -> TK0

        | TK1 -> TK01

        | TK2 -> TK02

        | TK3 -> TK03

        | TK4 -> TK04

        |

        | K0 -> K01

        | K1 -> K02

        | K2 -> K03

        | K3 -> K04

        |

        | K01-K04 dipertahankan apa adanya.

        |--------------------------------------------------------------------------

        */

        if (!preg_match(

            '/^(TK|K)\s*(?:[\/\-_]\s*)?(0[0-4]|[0-4])$/u',

            $raw,

            $match

        )) {

            return null;

        }

        $prefix = $match[1];

        $numberRaw = $match[2];

        $number = (int) $numberRaw;

        if ($prefix === 'TK') {

            if ($number < 0 || $number > 4) {

                return null;

            }

            return $number === 0

                ? 'TK0'

                : 'TK' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        }

        // K01-K04 sudah merupakan format final.

        if (strlen($numberRaw) === 2) {

            return ($number >= 1 && $number <= 4)

                ? 'K' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)

                : null;

        }

        // Format Excel K0/K1/K2/K3 berarti K/0/K/1/K/2/K/3.

        // Database menggunakan K01/K02/K03/K04.

        if ($number >= 0 && $number <= 3) {

            return 'K' . str_pad((string) ($number + 1), 2, '0', STR_PAD_LEFT);

        }

        return null;

    }

    private function employmentStatusOptions(): array

    {

        return [

            'PKWTT' => 'Tetap',

            'PKWT|1' => 'Kontrak I',

            'PKWT|2' => 'Kontrak II',

            'PKWT|3' => 'Kontrak III',

            'PKWT|4' => 'Kontrak IV',

            'PKWT|5' => 'Kontrak V',

            'PKWT|6' => 'Kontrak VI',

            'PKWT|7' => 'Kontrak VII',

        ];

    }

    /*

    |--------------------------------------------------------------------------

    | TERMINATED EMPLOYMENT TYPE

    |--------------------------------------------------------------------------

    */

    private function isTerminatedEmploymentType(

        ?string $employmentType

    ): bool {

        return ContractHistoryResolver::isTerminationType(
            (string) ($employmentType ?? '')
        );

    }

    /*

    |--------------------------------------------------------------------------

    | ENSURE CONTRACT MASTER

    |--------------------------------------------------------------------------

    */

    private function ensureContractMaster(

        Employee $employee

    ): ContractLocal {

        $contract =

            $employee->contract;

        if ($contract) {

            return $contract;

        }

        return $employee

            ->contract()

            ->create([

                'uuid' =>

                    (string) Str::uuid(),

                'is_active' =>

                    true,

            ]);

    }

    /*

    |--------------------------------------------------------------------------

    | NEXT PKWT SEQUENCE

    |--------------------------------------------------------------------------

    */

    private function getNextPkwtSequence(

        ContractLocal $contract

    ): int {

        $max =

            ContractHistoryLocal::where(

                'contract_id',

                $contract->id_contract

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

    /**
     * Recalculate the current-history pointer from inclusive effective dates.
     * If multiple histories cover today, the pointer is cleared rather than
     * silently choosing one. A future history never replaces a valid current one.
     */
    /**
     * Keep sequential histories mutually exclusive. A later history closes
     * an earlier open/overlapping period on the day before its own start.
     * Existing intentional gaps are preserved.
     */
    private function normalizeContractTimeline(ContractLocal $contract): void
    {
        $histories = $contract->histories()
            ->orderBy('start_date')
            ->orderBy('id_contract_history')
            ->lockForUpdate()
            ->get()
            ->values();

        for ($index = 0; $index < $histories->count() - 1; $index++) {
            $current = $histories[$index];
            $next = $histories[$index + 1];
            $currentStart = Carbon::parse($current->start_date)->startOfDay();
            $nextStart = Carbon::parse($next->start_date)->startOfDay();

            if ($currentStart->gte($nextStart)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Ditemukan dua history dengan tanggal mulai yang sama atau urutan tidak valid.',
                ]);
            }

            $currentEnd = $current->end_date
                ? Carbon::parse($current->end_date)->startOfDay()
                : null;
            $currentExit = $current->exit_date
                ? Carbon::parse($current->exit_date)->startOfDay()
                : null;
            $dayBeforeNext = $nextStart->copy()->subDay();

            // If termination/exit already ends the older period earlier, retain it.
            if ($currentExit && $currentExit->lt($dayBeforeNext)) {
                continue;
            }

            if ($currentEnd === null || $currentEnd->gte($nextStart)) {
                // Changing the old end boundary affects the payroll month in
                // which the new history starts. Earlier locked months remain
                // fully covered by the old history and are not changed by this
                // future-boundary normalization.
                $boundaryMonth = $nextStart->format('Y-m');
                $hasLockedPayroll = Payroll::query()
                    ->where('employee_id', $contract->employee_id)
                    ->where('is_locked', true)
                    ->where('period_month', $boundaryMonth)
                    ->exists();

                if ($hasLockedPayroll) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'start_date' => 'Periode kontrak lama beririsan dengan payroll terkunci. Buka kunci periode melalui prosedur resmi sebelum membuat perubahan tanggal history.',
                    ]);
                }

                if ($dayBeforeNext->lt($currentStart)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'start_date' => 'Tanggal mulai history baru tidak boleh sama dengan atau mendahului periode sebelumnya.',
                    ]);
                }

                $current->update([
                    'end_date' => $dayBeforeNext->toDateString(),
                    'is_active' => false,
                ]);
            }
        }
    }

    private function syncCurrentContractPointer(
        ContractLocal $contract,
        Employee $employee
    ): ?ContractHistoryLocal {
        $today = Carbon::today();
        $histories = $contract->histories()->get();
        $matches = ContractHistoryResolver::matchingDate($histories, $today);

        $contract->histories()->update(['is_active' => false]);

        if ($matches->count() === 1) {
            /** @var ContractHistoryLocal $current */
            $current = $matches->first();
            $currentEffectiveEnd = ContractHistoryResolver::effectiveEnd($current);
            $terminationEffective = $this->isTerminatedEmploymentType(
                (string) $current->employment_type
            ) && $currentEffectiveEnd !== null
                && $currentEffectiveEnd->lt($today);

            if (!$terminationEffective) {
                $current->update(['is_active' => true]);
                // Current-history linkage must not reactivate a manually
                // inactive employee/master. Employment status is separate.
                $contract->update([
                    'current_contract_history_id' => $current->id_contract_history,
                ]);
            } else {
                $contract->update([
                    'current_contract_history_id' => $current->id_contract_history,
                    'is_active' => false,
                ]);
                $employee->update(['is_active' => false]);
            }

            return $current;
        }

        // No unique history is effective today: a gap or overlap must not be
        // silently represented as a current contract.
        $contract->update(['current_contract_history_id' => null]);

        // Preserve the employee's existing active flag for future schedules or
        // overlaps. A completed termination event is the exception.
        $latestStarted = $histories
            ->filter(function ($history) use ($today) {
                return $history->start_date
                    && Carbon::parse($history->start_date)->startOfDay()->lt($today);
            })
            ->sortByDesc('start_date')
            ->first();

        $latestTerminationEnd = $latestStarted
            ? ContractHistoryResolver::effectiveEnd($latestStarted)
            : null;
        if (
            $latestStarted
            && $this->isTerminatedEmploymentType((string) $latestStarted->employment_type)
            && $latestTerminationEnd !== null
            && $latestTerminationEnd->lt($today)
        ) {
            $contract->update(['is_active' => false]);
            $employee->update(['is_active' => false]);
        }

        return null;
    }

    /*

    |--------------------------------------------------------------------------

    | CONTRACT EXPIRED

    |--------------------------------------------------------------------------

    */

    private function isContractExpired(

        ?ContractHistoryLocal $history

    ): bool {

        if (

            !$history ||

            !$history->end_date

        ) {

            return false;

        }

        return Carbon::today()->greaterThan(

            Carbon::parse(

                $history->end_date

            )

        );

    }

}
