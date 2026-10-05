<?php



namespace App\Http\Controllers;



use App\Models\Employee;

use App\Models\ContractLocal;

use App\Models\ContractHistoryLocal;

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



                $history =

                    $employee

                        ->contract

                        ?->currentHistory;



                $employee->current_contract_history =

                    $history;



                $employee->current_contract_level =

                    $history?->level;



                $employee->has_contract_master =

                    $employee->contract !== null;



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



        $history =

            $contract?->currentHistory;



        $histories =

            $contract?->histories ?? collect();



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



        $history =

            $contract->currentHistory;



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



        abort_unless(

            (int) $contract->current_contract_history_id ===

            (int) $history->id_contract_history,

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



            $request->merge([



                'basic_salary' =>

                    $oldBasicSalary,



                'allowance' =>

                    $oldAllowance,



                'ptkp_status' =>

                    $oldPtkpStatus,



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

                'nullable|date',



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



                $history->update(

                    $validated

                );



                $contract->update([



                    'current_contract_history_id' =>

                        $history->id_contract_history,



                    'is_active' =>

                        !$isTerminated,

                ]);



                $employee->update([

                    'is_active' =>

                        !$isTerminated,

                ]);

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

            $currentHistory?->level;



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

            $currentHistory?->basic_salary ?? 0;



        $oldAllowance =

            $currentHistory?->allowance ?? 0;



        $oldPtkpStatus =

            $this->normalizePtkpCode($currentHistory?->ptkp_status ?? null)

                ?? 'TK0';



        $oldTerCategory =

            in_array(strtoupper(trim((string) ($currentHistory?->ter_category ?? ''))), ['A', 'B', 'C'], true)

                ? strtoupper(trim((string) $currentHistory?->ter_category))

                : null;



        $oldUseManualBpjs =

            $currentHistory?->use_manual_bpjs ?? false;



        $oldManualBpjsTkEmployee =

            $currentHistory?->manual_bpjs_tk_employee ?? 0;



        $oldManualBpjsKsEmployee =

            $currentHistory?->manual_bpjs_ks_employee ?? 0;



        $oldManualBpjsCompany =

            $currentHistory?->manual_bpjs_company ?? 0;



        $oldCategory =

            $currentHistory?->category;



        $oldLevel =

            $currentHistory?->level;



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

                'nullable|date',



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



            $validated['basic_salary'] =

                $oldBasicSalary;



            $validated['allowance'] =

                $oldAllowance;



            $validated['ptkp_status'] =

                $oldPtkpStatus;



            $validated['level'] =

                $oldLevel;



            $validated['category'] =

                $oldCategory;



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



        /*

        |--------------------------------------------------------------------------

        | PKWT SEQUENCE

        |--------------------------------------------------------------------------

        |

        | Sequence dari Status Karyawan UI dihormati.

        | Bila tidak dikirim, gunakan sequence berikutnya.

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

                    : $this->getNextPkwtSequence($contract);



            $duplicateSequence = ContractHistoryLocal::query()

                ->where('contract_id', $contract->id_contract)

                ->where('employment_type', 'PKWT')

                ->where('pkwt_sequence', $validated['pkwt_sequence'])

                ->exists();



            if ($duplicateSequence) {

                return back()

                    ->withInput()

                    ->withErrors([

                        'pkwt_sequence' =>

                            'Nomor kontrak tersebut sudah digunakan. Pilih nomor kontrak berikutnya.',

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



                if ($currentHistory) {



                    $currentHistory->update([

                        'is_active' => false,

                    ]);

                }



                /*

                |--------------------------------------------------------------------------

                | CREATE HISTORY BARU

                |--------------------------------------------------------------------------

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

                |--------------------------------------------------------------------------

                | UPDATE MASTER

                |--------------------------------------------------------------------------

                */



                $contract->update([



                    'current_contract_history_id' =>

                        $newHistory->id_contract_history,



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



        return Carbon::today()->greaterThanOrEqualTo(

            Carbon::parse(

                $history->end_date

            )

        );

    }

}