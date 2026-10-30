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
            'contractMaster.histories',

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
                            'contractMaster.histories',
                            function ($history) use ($search) {
                                $today = Carbon::today()->toDateString();

                                $history
                                    ->whereDate('start_date', '<=', $today)
                                    ->where(function ($period) use ($today) {
                                        $period->whereNull('end_date')
                                            ->orWhereDate('end_date', '>=', $today);
                                    })
                                    ->where(function ($period) use ($today) {
                                        $period->whereNull('exit_date')
                                            ->orWhereDate('exit_date', '>=', $today);
                                    })
                                    ->where(function ($fields) use ($search) {
                                        $fields->where('job_title', 'LIKE', "%{$search}%")
                                            ->orWhere('department', 'LIKE', "%{$search}%")
                                            ->orWhere('placement_area', 'LIKE', "%{$search}%");
                                    });
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



                $contract = $employee->contractMaster;
                $history = $contract
                    ? $this->selectEffectiveHistory($contract)
                    : null;

                if ($contract) {
                    // Display the history effective today instead of trusting a possibly future pointer.
                    $contract->setRelation('currentHistory', $history);
                }



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



        $history = $contract
            ? $this->selectEffectiveHistory($contract)
            : null;



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
          'contractMaster.histories',
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



    $history = $this->selectEffectiveHistory($contract);



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



    // Riwayat target sudah dipilih berdasarkan tanggal efektif hari ini.
    // Jangan bergantung pada current_contract_history_id karena pointer lama
    // bisa tertinggal atau menunjuk ke riwayat masa depan.


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

    // PTKP memiliki izin tersendiri: HRD boleh mengubah PTKP untuk level <= 13,
    // tetapi tidak mendapatkan akses ke gaji maupun BPJS.
    $canEditPtkp = $isFinance
        || (($isHeadHrd || $isKeuangan || $isHrd) && $oldLevelSensitive);


    // Snapshot input finansial sebelum normalisasi/proteksi akses. Ini tidak
    // mengizinkan akses tambahan; hanya untuk menjelaskan bila perubahan ditolak.
    $financialInputBeforeNormalization = [];
    foreach ([
        'basic_salary',
        'allowance',
        'ptkp_status',
        'is_bpjstk_active',
        'is_bpjs_health_active',
        'use_manual_bpjs',
        'manual_bpjs_tk_employee',
        'manual_bpjs_ks_employee',
        'manual_bpjs_company',
    ] as $financialField) {
        if ($request->exists($financialField)) {
            $financialInputBeforeNormalization[$financialField] = $request->input($financialField);
        }
    }




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



        'basic_salary' => $canEditFinancial
            ? ['required', 'numeric', 'min:0', 'max:999999999999.99']
            : ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],

        'allowance' => $canEditFinancial
            ? ['required', 'numeric', 'min:0', 'max:999999999999.99']
            : ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],



        'is_bpjstk_active' => [

            'nullable',

            'boolean',

        ],



        'is_bpjs_health_active' => [

            'nullable',

            'boolean',

        ],



        'ptkp_status' => $canEditPtkp
            ? ['required', 'string', 'max:10']
            : ['nullable', 'string', 'max:10'],



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

            'in:PKWT,Freelance,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',

        ],

        'pkwt_sequence' => [
            'required_if:employment_type,PKWT',
            'nullable',
            'integer',
            'min:1',
            'max:7',
        ],

        'freelance_sequence' => [
            'required_if:employment_type,Freelance',
            'nullable',
            'integer',
            'min:1',
            'max:6',
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

            'after_or_equal:start_date',

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



        if (!$canEditPtkp) {
            $validated['ptkp_status'] = $history->ptkp_status;
        }



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



    // Sama seperti Contract Local: ketika Head HRD menaikkan level dari
    // level yang dapat diakses ke level 14+, nilai finansial lama tetap
    // dipertahankan. Perubahan nominal selanjutnya harus melalui kewenangan Finance.
    $headHrdSavingAbove13 = $isHeadHrd && $oldLevelSensitive && $newLevelHigh;
    if ($headHrdSavingAbove13) {
        $validated['basic_salary'] = $history->basic_salary;
        $validated['allowance'] = $history->allowance;
        $validated['ptkp_status'] = $history->ptkp_status;
        $validated['is_bpjstk_active'] = $history->is_bpjstk_active;
        $validated['is_bpjs_health_active'] = $history->is_bpjs_health_active;
        $validated['use_manual_bpjs'] = $history->use_manual_bpjs;
        $validated['manual_bpjs_tk_employee'] = $history->manual_bpjs_tk_employee;
        $validated['manual_bpjs_ks_employee'] = $history->manual_bpjs_ks_employee;
        $validated['manual_bpjs_company'] = $history->manual_bpjs_company;
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
    | PKWT / FREELANCE SEQUENCE
    |--------------------------------------------------------------------------
    */

    if ($validated['employment_type'] === 'PKWT') {
        $validated['pkwt_sequence'] = (int) ($validated['pkwt_sequence'] ?? 0);
        $validated['freelance_sequence'] = null;

        $duplicatePkwt = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->where('employment_type', 'PKWT')
            ->where('pkwt_sequence', $validated['pkwt_sequence'])
            ->where('id_contract_history_outer_island', '!=', $history->id_contract_history_outer_island)
            ->exists();

        if ($duplicatePkwt) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pkwt_sequence' => 'Nomor PKWT tersebut sudah dipakai pada riwayat lain. Pilih nomor periode yang belum digunakan.',
            ]);
        }
    } elseif ($validated['employment_type'] === 'Freelance') {
        $validated['pkwt_sequence'] = null;
        $validated['freelance_sequence'] = (int) $validated['freelance_sequence'];

        $duplicateFreelance = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->where('employment_type', 'Freelance')
            ->where('freelance_sequence', $validated['freelance_sequence'])
            ->where('id_contract_history_outer_island', '!=', $history->id_contract_history_outer_island)
            ->exists();

        if ($duplicateFreelance) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'freelance_sequence' => 'Periode Freelance tersebut sudah dipakai pada riwayat lain. Pilih periode 1–6 yang belum digunakan.',
            ]);
        }
    } else {
        $validated['pkwt_sequence'] = null;
        $validated['freelance_sequence'] = null;
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

        'freelance_sequence' =>

            $validated['freelance_sequence'] ?? null,



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

\|--------------------------------------------------------------------------

\| DATABASE TRANSACTION + ROW LOCK

\|--------------------------------------------------------------------------

*/



DB::transaction(function () use (

    $employee,

    $contract,

    $history,

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
    | LOCK HISTORY YANG EFEKTIF HARI INI
    |--------------------------------------------------------------------------
    | Gunakan ID hasil pemilihan berdasarkan tanggal efektif, bukan pointer
    | current_contract_history_id yang mungkin sudah stale.
    */

    $lockedHistory = ContractHistoryOuterIsland::query()
        ->where('id_contract_history_outer_island', $history->id_contract_history_outer_island)
        ->where('contract_outer_island_id', $lockedContract->id_contract_outer_island)
        ->lockForUpdate()
        ->firstOrFail();

    $today = Carbon::today()->toDateString();
    $stillEffective = $lockedHistory->start_date
        && Carbon::parse($lockedHistory->start_date)->toDateString() <= $today
        && (!$lockedHistory->end_date || Carbon::parse($lockedHistory->end_date)->toDateString() >= $today)
        && (!$lockedHistory->exit_date || Carbon::parse($lockedHistory->exit_date)->toDateString() >= $today);

    abort_unless($stillEffective, 409, 'Riwayat kontrak sudah tidak berlaku hari ini. Muat ulang halaman sebelum menyimpan.');





    $this->assertNoHistoryOverlap(
        $lockedContract,
        $historyData['start_date'],
        $historyData['end_date'] ?? null,
        $historyData['exit_date'] ?? null,
        $lockedHistory->id_contract_history_outer_island,
        false
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

            'freelance_sequence' =>

                $historyData['freelance_sequence'],



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
    | SINKRONISASI HISTORY AKTIF BERDASARKAN TANGGAL
    |--------------------------------------------------------------------------
    | Jangan otomatis menjadikan row yang diedit sebagai current hanya karena
    | form menyimpannya. Periode aktif harus mencakup tanggal hari ini.
    */
    $this->syncCurrentHistory($lockedContract, $employee);

});



    /*

    |--------------------------------------------------------------------------

    | SUCCESS MESSAGE

    |--------------------------------------------------------------------------

    */



    // Beri tahu dengan jelas jika input finansial yang berbeda ditolak oleh batas akses.
    $financialChangeBlocked = false;
    if (!$canEditFinancial) {
        foreach ($financialInputBeforeNormalization as $field => $incoming) {
            $current = $history->{$field};

            if (in_array($field, [
                'basic_salary',
                'allowance',
                'manual_bpjs_tk_employee',
                'manual_bpjs_ks_employee',
                'manual_bpjs_company',
            ], true)) {
                $incoming = (float) $this->cleanRupiah($incoming);
                $current = (float) ($current ?? 0);
            } elseif (in_array($field, [
                'is_bpjstk_active',
                'is_bpjs_health_active',
                'use_manual_bpjs',
            ], true)) {
                $incoming = in_array($incoming, [true, 1, '1', 'on', 'true'], true);
                $current = (bool) $current;
            } else {
                $incoming = trim((string) $incoming);
                $current = trim((string) $current);
            }

            if ($incoming !== $current) {
                $financialChangeBlocked = true;
                break;
            }
        }
    }

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

            $financialChangeBlocked
                ? 'Perubahan data non-finansial tersimpan, tetapi gaji/BPJS/PTKP tidak berubah karena batas akses role atau level kontrak.'
                : $message

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

        $role = auth()->user()?->role;
        $isFinance = $this->isFinanceRole($role);
        $isHeadHrd = $this->isHeadHrdRole($role);
        $isKeuangan = $role === self::KEUANGAN_ROLE;
        $isHrd = $role === self::HRD_ROLE;

        abort_unless($isFinance || $isHeadHrd || $isKeuangan || $isHrd, 403);


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



        $currentHistory = $this->selectEffectiveHistory($contract);



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

        $canEditFinancial = $isFinance
            || (($isHeadHrd || $isKeuangan) && $currentLevelSensitive);

        $canEditPtkp = $isFinance
            || (($isHeadHrd || $isKeuangan || $isHrd) && $currentLevelSensitive);



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



                'basic_salary' => $canEditFinancial
                    ? ['required', 'numeric', 'min:0']
                    : ['nullable', 'numeric', 'min:0'],

                'allowance' => $canEditFinancial
                    ? ['required', 'numeric', 'min:0']
                    : ['nullable', 'numeric', 'min:0'],



                'is_bpjstk_active' => [

                    'nullable',

                    'boolean',

                ],



                'is_bpjs_health_active' => [

                    'nullable',

                    'boolean',

                ],



                'ptkp_status' => $canEditPtkp
                    ? ['required', 'string', 'max:10']
                    : ['nullable', 'string', 'max:10'],

                'change_type' => [
                    'required',
                    'string',
                    'in:initial_contract,contract_renewal,level_up,level_down,salary_adjustment,job_promotion,job_demotion,position_change',
                ],

                'change_reason' => [
                    'nullable',
                    'string',
                    'max:500',
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

                    'in:PKWT,Freelance,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract',

                ],

                'pkwt_sequence' => [
                    'required_if:employment_type,PKWT',
                    'nullable',
                    'integer',
                    'min:1',
                    'max:7',
                ],

                'freelance_sequence' => [
                    'required_if:employment_type,Freelance',
                    'nullable',
                    'integer',
                    'min:1',
                    'max:6',
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

                    'after_or_equal:start_date',

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
        | CHANGE TYPE RULES — parity with Contract Local
        |--------------------------------------------------------------------------
        */

        $changeType = $validated['change_type'];

        if ($currentHistory && $changeType === 'initial_contract') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'change_type' => 'Karyawan ini sudah memiliki riwayat kontrak. Pilih jenis perubahan yang sesuai.',
            ]);
        }

        if (!$currentHistory && $changeType !== 'initial_contract') {
            $changeType = 'initial_contract';
            $validated['change_type'] = $changeType;
        }

        $reasonRequiredTypes = [
            'level_up', 'level_down', 'salary_adjustment',
            'job_promotion', 'job_demotion', 'position_change',
        ];

        if (in_array($changeType, $reasonRequiredTypes, true)
            && trim((string) ($validated['change_reason'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'change_reason' => 'Alasan perubahan wajib diisi untuk perubahan level, gaji, jabatan, atau penempatan.',
            ]);
        }

        if ($isHrd && in_array($changeType, [
            'level_up', 'level_down', 'salary_adjustment', 'job_promotion', 'job_demotion',
        ], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'change_type' => 'Role HRD tidak memiliki akses untuk membuat perubahan level, gaji, promosi, atau demosi jabatan.',
            ]);
        }

        $oldLevelNumber = $currentHistory && is_numeric($currentHistory->level)
            ? (int) $currentHistory->level
            : null;
        $newLevelNumber = is_numeric($validated['level'] ?? null)
            ? (int) $validated['level']
            : null;
        $newLevelHigh = $newLevelNumber !== null && $newLevelNumber >= 14;
        $oldCategory = $currentHistory?->category;
        $oldBasicSalary = (float) ($currentHistory?->basic_salary ?? 0);
        $oldAllowance = (float) ($currentHistory?->allowance ?? 0);
        $oldPtkp = $currentHistory?->ptkp_status ?? 'TK/0';
        $oldBpjsTk = (bool) ($currentHistory?->is_bpjstk_active ?? false);
        $oldBpjsHealth = (bool) ($currentHistory?->is_bpjs_health_active ?? false);
        $oldManualBpjs = (bool) ($currentHistory?->use_manual_bpjs ?? false);
        $oldManualTk = (float) ($currentHistory?->manual_bpjs_tk_employee ?? 0);
        $oldManualHealth = (float) ($currentHistory?->manual_bpjs_ks_employee ?? 0);
        $oldManualCompany = (float) ($currentHistory?->manual_bpjs_company ?? 0);

        if ($currentHistory && $changeType !== 'contract_renewal') {
            // A level/pay/position change is a new snapshot inside the same employment period.
            $validated['employment_type'] = $currentHistory->employment_type;
            $validated['pkwt_sequence'] = $currentHistory->pkwt_sequence;
            $validated['freelance_sequence'] = $currentHistory->freelance_sequence;
            $validated['end_date'] = $currentHistory->end_date?->format('Y-m-d');
            $validated['exit_date'] = $currentHistory->exit_date?->format('Y-m-d');
            $validated['exit_reason'] = $currentHistory->exit_reason;
            $validated['ptkp_status'] = $oldPtkp;
            $validated['is_bpjstk_active'] = $oldBpjsTk;
            $validated['is_bpjs_health_active'] = $oldBpjsHealth;
            $validated['use_manual_bpjs'] = $oldManualBpjs;
            $validated['manual_bpjs_tk_employee'] = $oldManualTk;
            $validated['manual_bpjs_ks_employee'] = $oldManualHealth;
            $validated['manual_bpjs_company'] = $oldManualCompany;
        }

        if ($currentHistory && in_array($changeType, ['level_up', 'level_down'], true)) {
            if (!is_numeric($validated['level'] ?? null) || (int) $validated['level'] === $oldLevelNumber) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'level' => 'Level baru harus berbeda dari level sebelumnya.',
                ]);
            }
            if ($changeType === 'level_up' && $oldLevelNumber !== null && (int) $validated['level'] <= $oldLevelNumber) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'level' => 'Untuk Naik Level, level baru harus lebih tinggi dari level sebelumnya.',
                ]);
            }
            if ($changeType === 'level_down' && $oldLevelNumber !== null && (int) $validated['level'] >= $oldLevelNumber) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'level' => 'Untuk Turun Level, level baru harus lebih rendah dari level sebelumnya.',
                ]);
            }
            foreach (['job_title', 'department', 'placement_area', 'nik_fingerprint', 'fingerprint_pin'] as $field) {
                $validated[$field] = $currentHistory->{$field};
            }
        } elseif ($currentHistory && $changeType === 'salary_adjustment') {
            $validated['level'] = $currentHistory->level;
            $validated['category'] = $oldCategory;
            foreach (['job_title', 'department', 'placement_area', 'nik_fingerprint', 'fingerprint_pin'] as $field) {
                $validated[$field] = $currentHistory->{$field};
            }
        } elseif ($currentHistory && $changeType === 'position_change') {
            $validated['level'] = $currentHistory->level;
            $validated['category'] = $oldCategory;
            $validated['basic_salary'] = $oldBasicSalary;
            $validated['allowance'] = $oldAllowance;
        } elseif ($currentHistory && in_array($changeType, ['job_promotion', 'job_demotion'], true)) {
            if (trim((string) ($validated['job_title'] ?? '')) === trim((string) $currentHistory->job_title)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'job_title' => 'Masukkan jabatan baru yang berbeda untuk promosi atau penurunan jabatan.',
                ]);
            }
        }

        // Head HRD may change a level to 14+, but financial values remain inherited.
        $headHrdSavingAbove13 = $isHeadHrd
            && $currentHistory
            && is_numeric($validated['level'] ?? null)
            && (int) $validated['level'] > 13;

        if ($headHrdSavingAbove13) {
            $validated['basic_salary'] = $oldBasicSalary;
            $validated['allowance'] = $oldAllowance;
            $validated['ptkp_status'] = $oldPtkp;
            $validated['is_bpjstk_active'] = $oldBpjsTk;
            $validated['is_bpjs_health_active'] = $oldBpjsHealth;
            $validated['use_manual_bpjs'] = $oldManualBpjs;
            $validated['manual_bpjs_tk_employee'] = $oldManualTk;
            $validated['manual_bpjs_ks_employee'] = $oldManualHealth;
            $validated['manual_bpjs_company'] = $oldManualCompany;
        }

        // HRD never edits salary/BPJS or level on an existing contract; PTKP remains
        // independently editable only when the stored level is <= 13.
        if ($isHrd && $currentHistory) {
            $validated['basic_salary'] = $oldBasicSalary;
            $validated['allowance'] = $oldAllowance;
            $validated['level'] = $currentHistory->level;
            $validated['category'] = $oldCategory;
            $validated['is_bpjstk_active'] = $oldBpjsTk;
            $validated['is_bpjs_health_active'] = $oldBpjsHealth;
            $validated['use_manual_bpjs'] = $oldManualBpjs;
            $validated['manual_bpjs_tk_employee'] = $oldManualTk;
            $validated['manual_bpjs_ks_employee'] = $oldManualHealth;
            $validated['manual_bpjs_company'] = $oldManualCompany;
        }

        if ($changeType === 'position_change' && $currentHistory) {
            $validated['allowance'] = $oldAllowance;
        } elseif ($canEditFinancial && !$headHrdSavingAbove13 && !$newLevelHigh) {
            // Allowance remains a server-side calculation, not a trusted browser value.
            $validated['allowance'] = $this->calculateAllowance(
                $validated['basic_salary'] ?? $oldBasicSalary,
                $validated['level'] ?? null
            );
        }

        if (!$validated['use_manual_bpjs']) {
            $validated['manual_bpjs_tk_employee'] = 0;
            $validated['manual_bpjs_ks_employee'] = 0;
            $validated['manual_bpjs_company'] = 0;
        }

        // New sequence numbers are allocated only for a true renewal / first contract.
        // Changes within the same contract period retain the existing PKWT/Freelance slot.
        if (in_array($changeType, ['initial_contract', 'contract_renewal'], true)) {
            if ($validated['employment_type'] === 'PKWT') {
                $validated['pkwt_sequence'] = (int) ($validated['pkwt_sequence'] ?? 0);
                $validated['freelance_sequence'] = null;
                $duplicate = ContractHistoryOuterIsland::query()
                    ->where('contract_outer_island_id', $contract->id_contract_outer_island)
                    ->where('employment_type', 'PKWT')
                    ->where('pkwt_sequence', $validated['pkwt_sequence'])
                    ->exists();
                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'pkwt_sequence' => 'Nomor PKWT tersebut sudah digunakan. Pilih nomor kontrak berikutnya.',
                    ]);
                }
            } elseif ($validated['employment_type'] === 'Freelance') {
                $validated['pkwt_sequence'] = null;
                $validated['freelance_sequence'] = (int) ($validated['freelance_sequence'] ?? 0);
                $duplicate = ContractHistoryOuterIsland::query()
                    ->where('contract_outer_island_id', $contract->id_contract_outer_island)
                    ->where('employment_type', 'Freelance')
                    ->where('freelance_sequence', $validated['freelance_sequence'])
                    ->exists();
                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'freelance_sequence' => 'Nomor periode Freelance tersebut sudah digunakan. Pilih periode berikutnya.',
                    ]);
                }
            } else {
                $validated['pkwt_sequence'] = null;
                $validated['freelance_sequence'] = null;
            }
        }

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

        if ($isHrd && $newLevelNumber !== null && $newLevelNumber >= 14) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'level' => 'HRD hanya dapat mengisi level sampai 13. Untuk level 14 ke atas, hubungi Manager Keuangan atau Head HRD sesuai kewenangan.',
            ]);
        }



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



            if (!$currentLevelSensitive) {
                $validated['category'] = $currentHistory->category;
            }



            if (!$currentLevelSensitive) {
                $validated['level'] = $currentHistory->level;
            }



            $validated['basic_salary'] =

                $currentHistory->basic_salary;



            $validated['allowance'] =

                $currentHistory->allowance;



            if (!$canEditPtkp) {
                $validated['ptkp_status'] = $currentHistory->ptkp_status;
            }



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



        if ($changeType === 'position_change' && $currentHistory) {
            $validated['allowance'] = $oldAllowance;
        } elseif (
            $canEditFinancial
            && !$headHrdSavingAbove13
            && !$newLevelHigh
        ) {
            $validated['allowance'] = $this->calculateAllowance(
                $validated['basic_salary'] ?? $oldBasicSalary,
                $validated['level'] ?? null
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



        // PKWT/Freelance sequence has already been normalized above.


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



        $newStartDate = Carbon::parse($validated['start_date'])->startOfDay();

        if ($currentHistory) {
            $currentStartDate = Carbon::parse($currentHistory->start_date)->startOfDay();
            if ($newStartDate->lessThanOrEqualTo($currentStartDate)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Tanggal efektif harus setelah tanggal mulai riwayat yang sedang berlaku.',
                ]);
            }

            $currentEndDate = $currentHistory->end_date
                ? Carbon::parse($currentHistory->end_date)->startOfDay()
                : null;

            if ($changeType !== 'contract_renewal' && $currentEndDate && $newStartDate->greaterThan($currentEndDate)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Tanggal efektif perubahan level/gaji/jabatan harus berada di dalam periode kontrak saat ini. Untuk tanggal setelah kontrak berakhir, pilih Perpanjangan Kontrak.',
                ]);
            }

            if ($changeType === 'contract_renewal' && $currentEndDate && $newStartDate->lessThanOrEqualTo($currentEndDate)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Tanggal mulai kontrak baru harus setelah tanggal berakhir kontrak sebelumnya (' . $currentEndDate->format('d-m-Y') . ').',
                ]);
            }

            if ($changeType !== 'contract_renewal') {
                $validated['end_date'] = $currentHistory->end_date?->format('Y-m-d');
                $validated['exit_date'] = $currentHistory->exit_date?->format('Y-m-d');
                $validated['exit_reason'] = $currentHistory->exit_reason;
            } elseif (!$isTerminated) {
                $validated['exit_date'] = null;
                $validated['exit_reason'] = null;
            }
        }

        $validated['change_type'] = $changeType;
        $validated['change_reason'] = trim((string) ($validated['change_reason'] ?? '')) ?: null;

        /*

        |--------------------------------------------------------------------------

        | CREATE NEW HISTORY

        |--------------------------------------------------------------------------

        */



        $freelanceSequence = $validated['freelance_sequence'] ?? null;
        unset($validated['freelance_sequence']);

        if ($currentHistory && $currentHistory->start_date
            && Carbon::parse($validated['start_date'])->toDateString()
                <= Carbon::parse($currentHistory->start_date)->toDateString()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_date' => 'Tanggal mulai contract period baru harus setelah tanggal mulai riwayat yang berlaku saat ini. Untuk koreksi periode lama, gunakan Detail History.',
            ]);
        }

        DB::transaction(function () use (
            $contract,
            $employee,
            $currentHistory,
            $validated,
            $freelanceSequence
        ) {
            // Serialize writes for the same Contract Master. This protects the
            // date-range and sequence checks against two simultaneous submissions.
            $lockedContract = ContractOuterIsland::query()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedContract->load('histories');
            $effectiveAfterLock = $this->selectEffectiveHistory($lockedContract);
            $expectedHistoryId = $currentHistory?->id_contract_history_outer_island;
            $actualHistoryId = $effectiveAfterLock?->id_contract_history_outer_island;

            abort_unless(
                (string) $expectedHistoryId === (string) $actualHistoryId,
                409,
                'Riwayat kontrak berubah setelah halaman dibuka. Muat ulang halaman sebelum menyimpan.'
            );

            $lockedCurrentHistory = $effectiveAfterLock
                ? ContractHistoryOuterIsland::query()
                    ->whereKey($effectiveAfterLock->id_contract_history_outer_island)
                    ->where('contract_outer_island_id', $lockedContract->id_contract_outer_island)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            // Recheck sequence uniqueness after acquiring the Contract Master lock.
            if (in_array($validated['change_type'], ['initial_contract', 'contract_renewal'], true)) {
                if (($validated['employment_type'] ?? null) === 'PKWT') {
                    $duplicateSequence = ContractHistoryOuterIsland::query()
                        ->where('contract_outer_island_id', $lockedContract->id_contract_outer_island)
                        ->where('employment_type', 'PKWT')
                        ->where('pkwt_sequence', $validated['pkwt_sequence'])
                        ->lockForUpdate()
                        ->exists();

                    if ($duplicateSequence) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'pkwt_sequence' => 'Nomor PKWT tersebut baru saja digunakan. Muat ulang dan pilih nomor yang tersedia.',
                        ]);
                    }
                } elseif (($validated['employment_type'] ?? null) === 'Freelance') {
                    $duplicateSequence = ContractHistoryOuterIsland::query()
                        ->where('contract_outer_island_id', $lockedContract->id_contract_outer_island)
                        ->where('employment_type', 'Freelance')
                        ->where('freelance_sequence', $freelanceSequence)
                        ->lockForUpdate()
                        ->exists();

                    if ($duplicateSequence) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'freelance_sequence' => 'Periode Freelance tersebut baru saja digunakan. Muat ulang dan pilih periode yang tersedia.',
                        ]);
                    }
                }
            }

            $this->assertNoHistoryOverlap(
                $lockedContract,
                $validated['start_date'],
                $validated['end_date'] ?? null,
                $validated['exit_date'] ?? null,
                $lockedCurrentHistory?->id_contract_history_outer_island,
                true
            );

            // Keep the old snapshot effective through the day before the new start.
            if (
                $lockedCurrentHistory
                && $lockedCurrentHistory->start_date
                && Carbon::parse($validated['start_date'])->gt(
                    Carbon::parse($lockedCurrentHistory->start_date)->startOfDay()
                )
            ) {
                $previousEndDate = Carbon::parse($validated['start_date'])
                    ->subDay()
                    ->toDateString();

                $existingEndDate = $lockedCurrentHistory->end_date
                    ? Carbon::parse($lockedCurrentHistory->end_date)->toDateString()
                    : null;

                if ($existingEndDate === null || $existingEndDate >= $previousEndDate) {
                    $lockedCurrentHistory->forceFill([
                        'end_date' => $previousEndDate,
                        'is_active' => false,
                    ])->save();
                }
            }

            $newHistory = $lockedContract->histories()->create(array_merge(
                $validated,
                [
                    'uuid' => (string) Str::uuid(),
                    'is_active' => false,
                ]
            ));

            // Freelance sequence is kept in a separate, optional schema column.
            DB::table('employee_contract_histories_outer_island')
                ->where('id_contract_history_outer_island', $newHistory->id_contract_history_outer_island)
                ->update([
                    'freelance_sequence' => $freelanceSequence,
                    'updated_at' => now(),
                ]);

            $this->syncCurrentHistory($lockedContract, $employee);
        });



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



                : 'Contract Period baru berhasil dibuat. Status current ditentukan otomatis berdasarkan tanggal mulai dan berakhir.';



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



        $effectiveHistory = $this->selectEffectiveHistory($contract);
        $isCurrent = $effectiveHistory
            && (int) $effectiveHistory->id_contract_history_outer_island
                === (int) $history->id_contract_history_outer_island;

        $canEditHistory = $this->isFinanceRole(auth()->user()?->role);



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
                'canViewSensitiveData',
                'canEditHistory'

            )

        );

    }



    /**
     * Edit stored history is restricted to Finance manager / Super Admin.
     */
    public function updateHistory(
        Request $request,
        EmployeeOuterIsland $employee,
        ContractHistoryOuterIsland $history
    ) {
        abort_unless($this->isFinanceRole(auth()->user()?->role), 403);

        $employee->load('contractMaster');
        $contract = $employee->contractMaster;

        abort_unless(
            $contract
                && (int) $history->contract_outer_island_id === (int) $contract->id_contract_outer_island,
            404
        );

        $validated = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'placement_area' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:10'],
            'level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'employment_type' => ['required', 'in:PKWT,Freelance,PKWTT,Probation,Internship,PHK,Resign,Pensiun,End_Contract'],
            'pkwt_sequence' => ['nullable', 'integer', 'min:1', 'max:7'],
            'freelance_sequence' => ['nullable', 'integer', 'min:1', 'max:6'],
            'change_type' => ['nullable', 'string', 'in:initial_contract,contract_renewal,level_up,level_down,salary_adjustment,job_promotion,job_demotion,position_change'],
            'change_reason' => ['nullable', 'string', 'max:500'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'exit_reason' => ['nullable', 'string', 'max:500'],
            'fingerprint_pin' => ['nullable', 'string', 'max:255'],
            'nik_fingerprint' => ['nullable', 'string', 'max:255'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['required', 'numeric', 'min:0'],
            'ptkp_status' => ['required', 'string', 'max:10'],
            'is_bpjstk_active' => ['nullable', 'boolean'],
            'is_bpjs_health_active' => ['nullable', 'boolean'],
            'use_manual_bpjs' => ['nullable', 'boolean'],
            'manual_bpjs_tk_employee' => ['nullable', 'numeric', 'min:0'],
            'manual_bpjs_ks_employee' => ['nullable', 'numeric', 'min:0'],
            'manual_bpjs_company' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ([
            'basic_salary', 'allowance',
            'manual_bpjs_tk_employee', 'manual_bpjs_ks_employee', 'manual_bpjs_company',
        ] as $field) {
            $validated[$field] = $this->cleanRupiah($validated[$field] ?? 0);
        }

        foreach (['is_bpjstk_active', 'is_bpjs_health_active', 'use_manual_bpjs'] as $field) {
            $validated[$field] = $request->boolean($field);
        }

        $historyChangeType = $history->change_type;
        if (!in_array($historyChangeType, ['initial_contract', 'contract_renewal'], true)) {
            // A split history row (level/pay/position change) stays in the same
            // PKWT/Freelance period; correction must not allocate a duplicate slot.
            $validated['employment_type'] = $history->employment_type;
            $validated['pkwt_sequence'] = $history->pkwt_sequence;
            $validated['freelance_sequence'] = $history->freelance_sequence;
        } elseif ($validated['employment_type'] === 'PKWT') {
            if (empty($validated['pkwt_sequence'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'pkwt_sequence' => 'Nomor PKWT wajib dipilih.',
                ]);
            }
            $duplicate = ContractHistoryOuterIsland::query()
                ->where('contract_outer_island_id', $contract->id_contract_outer_island)
                ->where('employment_type', 'PKWT')
                ->where('pkwt_sequence', $validated['pkwt_sequence'])
                ->where('id_contract_history_outer_island', '!=', $history->id_contract_history_outer_island)
                ->exists();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'pkwt_sequence' => 'Nomor PKWT tersebut sudah dipakai pada riwayat kontrak lain.',
                ]);
            }
            $validated['freelance_sequence'] = null;
        } elseif ($validated['employment_type'] === 'Freelance') {
            if (empty($validated['freelance_sequence'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'freelance_sequence' => 'Nomor periode Freelance wajib dipilih.',
                ]);
            }
            $duplicate = ContractHistoryOuterIsland::query()
                ->where('contract_outer_island_id', $contract->id_contract_outer_island)
                ->where('employment_type', 'Freelance')
                ->where('freelance_sequence', $validated['freelance_sequence'])
                ->where('id_contract_history_outer_island', '!=', $history->id_contract_history_outer_island)
                ->exists();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'freelance_sequence' => 'Periode Freelance tersebut sudah dipakai pada riwayat kontrak lain.',
                ]);
            }
            $validated['pkwt_sequence'] = null;
        } else {
            $validated['pkwt_sequence'] = null;
            $validated['freelance_sequence'] = null;
        }

        DB::transaction(function () use ($history, $validated, $contract, $employee) {
            $lockedContract = ContractOuterIsland::query()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedHistory = ContractHistoryOuterIsland::query()
                ->whereKey($history->getKey())
                ->where('contract_outer_island_id', $lockedContract->id_contract_outer_island)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertNoHistoryOverlap(
                $lockedContract,
                $validated['start_date'],
                $validated['end_date'] ?? null,
                $validated['exit_date'] ?? null,
                $lockedHistory->id_contract_history_outer_island,
                false
            );

            $lockedHistory->update($validated);
            $lockedContract->load('histories');
            $this->syncCurrentHistory($lockedContract, $employee);
        });

        return redirect()
            ->route('contracts.outer_island.period.show', [
                'employee' => $employee->uuid,
                'history' => $history->getKey(),
            ])
            ->with('success', 'Riwayat kontrak berhasil dikoreksi.');
    }

    /**
     * Reject overlapping contract periods. Dates are inclusive; the effective
     * history being replaced may be shortened to the day before the new period.
     */
    private function assertNoHistoryOverlap(
        ContractOuterIsland $contract,
        string $startDate,
        ?string $endDate = null,
        ?string $exitDate = null,
        ?int $ignoreHistoryId = null,
        bool $closeIgnoredHistoryBeforeNewStart = false
    ): void {
        $newStart = Carbon::parse($startDate)->startOfDay();
        $newEnds = array_filter([$endDate, $exitDate]);
        $newEnd = $newEnds
            ? Carbon::parse(min(array_map(fn ($date) => Carbon::parse($date)->toDateString(), $newEnds)))->endOfDay()
            : null;

        if ($newEnd && $newEnd->lt($newStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'end_date' => 'Tanggal akhir/keluar tidak boleh sebelum tanggal mulai kontrak.',
            ]);
        }

        $rows = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->when($ignoreHistoryId, fn ($query) => $query->where('id_contract_history_outer_island', '!=', $ignoreHistoryId))
            ->lockForUpdate()
            ->get();

        // The current effective row is intentionally excluded because storePeriod
        // closes it to (new start - 1 day) inside the same transaction.
        if ($closeIgnoredHistoryBeforeNewStart && $ignoreHistoryId) {
            $ignored = ContractHistoryOuterIsland::query()
                ->where('id_contract_history_outer_island', $ignoreHistoryId)
                ->where('contract_outer_island_id', $contract->id_contract_outer_island)
                ->lockForUpdate()
                ->first();

            if ($ignored && $ignored->start_date
                && Carbon::parse($ignored->start_date)->startOfDay()->gte($newStart)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Tanggal mulai period baru harus setelah tanggal mulai riwayat yang sedang berlaku.',
                ]);
            }
        }

        foreach ($rows as $row) {
            if (!$row->start_date) {
                continue;
            }

            $otherStart = Carbon::parse($row->start_date)->startOfDay();
            $otherEnds = array_filter([
                $row->end_date ? Carbon::parse($row->end_date)->toDateString() : null,
                $row->exit_date ? Carbon::parse($row->exit_date)->toDateString() : null,
            ]);
            $otherEnd = $otherEnds
                ? Carbon::parse(min($otherEnds))->endOfDay()
                : null;

            $startsBeforeNewEnds = $newEnd === null || $otherStart->lte($newEnd);
            $newStartsBeforeOtherEnds = $otherEnd === null || $otherEnd->gte($newStart);

            if ($startsBeforeNewEnds && $newStartsBeforeOtherEnds) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => 'Periode kontrak bertumpang tindih dengan riwayat ID '
                        . $row->id_contract_history_outer_island
                        . '. Sesuaikan tanggal akhir riwayat tersebut terlebih dahulu.',
                ]);
            }
        }
    }

    /**
     * Choose the history whose date range covers the requested date.
     * It does not rely on current_contract_history_id because that pointer
     * can be stale or point to a future-dated record.
     */
    private function selectEffectiveHistory(
        ContractOuterIsland $contract,
        ?Carbon $asOf = null
    ): ?ContractHistoryOuterIsland {
        $date = ($asOf ?? Carbon::today())->toDateString();

        $rows = $contract->relationLoaded('histories')
            ? $contract->histories
            : $contract->histories()->get();

        return $rows
            ->filter(function (ContractHistoryOuterIsland $row) use ($date) {
                if (!$row->start_date) {
                    return false;
                }

                $start = Carbon::parse($row->start_date)->toDateString();
                $end = $row->end_date
                    ? Carbon::parse($row->end_date)->toDateString()
                    : null;
                $exit = $row->exit_date
                    ? Carbon::parse($row->exit_date)->toDateString()
                    : null;

                return $start <= $date
                    && ($end === null || $end >= $date)
                    && ($exit === null || $exit >= $date);
            })
            ->sort(function ($a, $b) {
                $startOrder = Carbon::parse($b->start_date)->timestamp
                    <=> Carbon::parse($a->start_date)->timestamp;

                return $startOrder !== 0
                    ? $startOrder
                    : ((int) $b->id_contract_history_outer_island
                        <=> (int) $a->id_contract_history_outer_island);
            })
            ->first();
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

    ): ?int {

        $used = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->where('employment_type', 'PKWT')
            ->pluck('pkwt_sequence')
            ->filter(fn ($sequence) => $sequence !== null && $sequence !== '')
            ->map(fn ($sequence) => (int) $sequence)
            ->all();

        foreach (range(1, 7) as $sequence) {
            if (!in_array($sequence, $used, true)) {
                return $sequence;
            }
        }

        return null;
    }



    /**
     * Sinkronkan current_contract_history_id dan semua penanda is_active
     * berdasarkan periode yang benar-benar mencakup tanggal hari ini.
     * Riwayat masa depan/lampau tetap tersimpan tetapi tidak jadi current.
     */
    private function syncCurrentHistory(
        ContractOuterIsland $contract,
        EmployeeOuterIsland $employee
    ): ?ContractHistoryOuterIsland {
        $today = Carbon::today()->toDateString();

        $candidate = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('exit_date')
                    ->orWhereDate('exit_date', '>=', $today);
            })
            // Periode yang paling baru mulai dan masih mencakup hari ini menjadi current.
            // PKWTT hanya menang sebagai tie-breaker bila tanggal mulainya sama;
            // kontrak permanen lama tidak boleh menimpa PKWT/Freelance yang lebih baru.
            ->orderByDesc('start_date')
            ->orderByRaw("CASE WHEN employment_type = 'PKWTT' AND end_date IS NULL THEN 0 ELSE 1 END")
            ->orderByDesc('id_contract_history_outer_island')
            ->get()
            ->first(fn ($row) => !$this->isTerminatedEmploymentType($row->employment_type));

        ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $contract->id_contract_outer_island)
            ->update(['is_active' => false]);

        if ($candidate) {
            ContractHistoryOuterIsland::query()
                ->where('id_contract_history_outer_island', $candidate->id_contract_history_outer_island)
                ->update(['is_active' => true]);
        }

        $isActive = $candidate !== null;

        $contract->forceFill([
            'current_contract_history_id' => $candidate?->id_contract_history_outer_island,
            'is_active' => $isActive,
        ])->save();

        $employee->forceFill([
            'is_active' => $isActive,
        ])->save();

        return $candidate;
    }


    private function isContractExpired(?ContractHistoryOuterIsland $history): bool

    {

        if (!$history || !$history->end_date) {

            return false;

        }



        return Carbon::today()->greaterThan(

            Carbon::parse($history->end_date)

        );

    }

}