<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\EmployeeOuterIsland;
use App\Models\PayrollOuterIsland;
use App\Models\ContractOuterIsland;
use App\Models\ContractHistoryOuterIsland;
use App\Models\AttendanceRecordOuterIsland;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollOuterIslandController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ROLE
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

    private const HRD_ROLE = 'hrd';

    /*
    |--------------------------------------------------------------------------
    | PAYROLL SETTINGS
    |--------------------------------------------------------------------------
    */

    private const DEFAULT_CUTOFF_DAY = 26;
    private const MIN_CUTOFF_DAY = 20;
    private const MAX_CUTOFF_DAY = 28;

    private const DEFAULT_STANDARD_WORK_DAYS = 26;
    private const DEFAULT_OVERTIME_RATE = 20000;

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE STATUS
    |--------------------------------------------------------------------------
    */

    private const ATTENDANCE_STATUSES = [
        'H',
        'H0.5',
        'A',
        'I',
        'SKD',
        'S',
        'C',
        'CM',
        'M/HB',
        'HB',
        '-',
    ];

    /*
    |--------------------------------------------------------------------------
    | ROLE HELPERS
    |--------------------------------------------------------------------------
    */

    private function userRole(): string
    {
        return strtolower(
            trim(
                Auth::user()?->role ?? ''
            )
        );
    }

    private function isFinanceRole(): bool
    {
        return in_array(
            $this->userRole(),
            self::FINANCE_ROLES,
            true
        );
    }

    private function isHeadHrd(): bool
    {
        return in_array(
            $this->userRole(),
            self::HEAD_HRD_ROLES,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BOOLEAN NORMALIZER
    |--------------------------------------------------------------------------
    */

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        return in_array(
            strtolower(trim((string) $value)),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT CONTRACT
    |--------------------------------------------------------------------------
    */

    private function currentContract(
        EmployeeOuterIsland $employee
    ): ?ContractHistoryOuterIsland {
        return $employee
            ->contractMaster
            ?->currentHistory;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT EXPIRATION
    |--------------------------------------------------------------------------
    |
    | RULE:
    |
    | end_date = 2027-09-25
    | maka pada 2027-09-25 kontrak dianggap HABIS.
    |
    */

    private function isContractExpired(
        ?ContractHistoryOuterIsland $history
    ): bool {
        if (!$history || !$history->end_date) {
            return false;
        }

        return Carbon::today()->greaterThanOrEqualTo(
            Carbon::parse($history->end_date)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT STATUS
    |--------------------------------------------------------------------------
    |
    | Tidak menyimpan status baru ke database.
    | Status hanya dihitung sementara untuk indikator.
    |
    */

    private function getContractStatus(
        EmployeeOuterIsland $employee
    ): array {
        $contract = $employee->contractMaster;

        if (!$contract) {
            return [
                'active' => false,
                'status' => 'Tidak Ada Kontrak',
                'history' => null,
            ];
        }

        $history = $contract->currentHistory;

        if (!$history) {
            return [
                'active' => false,
                'status' => 'Kontrak Habis',
                'history' => null,
            ];
        }

        if (
            !$contract->is_active
            || !$history->is_active
        ) {
            return [
                'active' => false,
                'status' => 'Kontrak Habis',
                'history' => $history,
            ];
        }

        if ($this->isContractExpired($history)) {
            return [
                'active' => false,
                'status' => 'Kontrak Habis',
                'history' => $history,
            ];
        }

        if (
            $history->start_date
            && Carbon::today()->lt(
                Carbon::parse($history->start_date)
            )
        ) {
            return [
                'active' => false,
                'status' => 'Belum Masuk Periode Kontrak',
                'history' => $history,
            ];
        }

        return [
            'active' => true,
            'status' => 'Aktif',
            'history' => $history,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC CONTRACT EXPIRATION
    |--------------------------------------------------------------------------
    |
    | Jika current contract sudah expired:
    |
    | history:
    |     is_active = 0
    |
    | contract:
    |     is_active = 0
    |     current_contract_history_id = NULL
    |
    | employee:
    |     TIDAK DIUBAH
    |
    */

    private function syncContractExpiration(
        ?EmployeeOuterIsland $employee
    ): ?ContractHistoryOuterIsland {
        if (!$employee) {
            return null;
        }

        $contract = $employee->contractMaster;

        if (!$contract) {
            return null;
        }

        $contract->loadMissing(
            'currentHistory'
        );

        $history = $contract->currentHistory;

        if (!$history) {
            return null;
        }

        if (!$history->is_active) {
            return null;
        }

        if (!$contract->is_active) {
            return null;
        }

        if (!$history->end_date) {
            return $history;
        }

        if (!$this->isContractExpired($history)) {
            return $history;
        }

        DB::transaction(
            function () use (
                $contract,
                $history
            ) {
                $lockedContract =
                    ContractOuterIsland::query()
                        ->where(
                            'id_contract_outer_island',
                            $contract->id_contract_outer_island
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$lockedContract) {
                    return;
                }

                $lockedHistory =
                    ContractHistoryOuterIsland::query()
                        ->where(
                            'id_contract_history_outer_island',
                            $history->id_contract_history_outer_island
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$lockedHistory) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Pastikan history masih current
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $lockedContract->current_contract_history_id
                    !==
                    (int) $lockedHistory->id_contract_history_outer_island
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Recheck expiration
                |--------------------------------------------------------------------------
                */

                if (
                    !$lockedHistory->is_active
                    ||
                    !$lockedHistory->end_date
                    ||
                    !$this->isContractExpired($lockedHistory)
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Matikan history
                |--------------------------------------------------------------------------
                */

                $lockedHistory->update([
                    'is_active' => false,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Matikan contract master
                |--------------------------------------------------------------------------
                */

                $lockedContract->update([
                    'current_contract_history_id' => null,
                    'is_active' => false,
                ]);

                /*
                |--------------------------------------------------------------------------
                | EMPLOYEE TIDAK DINONAKTIFKAN
                |--------------------------------------------------------------------------
                */
            }
        );

        return null;
    }

   /*
|--------------------------------------------------------------------------
| PREPARE CONTRACT INDICATOR
|--------------------------------------------------------------------------
*/

private function prepareContractIndicator(
    EmployeeOuterIsland $employee,
    ?Carbon $periodStart = null,
    ?Carbon $periodEnd = null
): void {

    /*
    |--------------------------------------------------------------------------
    | DEFAULT
    |--------------------------------------------------------------------------
    */

    $employee->contract_status =
        'Tidak Ada Kontrak';

    $employee->contract_status_active =
        false;

    $employee->has_active_contract =
        false;

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE INACTIVE
    |--------------------------------------------------------------------------
    */

    if (!$employee->is_active) {

        $employee->contract_status =
            'Karyawan Tidak Aktif';

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT MASTER
    |--------------------------------------------------------------------------
    */

    $contractMaster =
        $employee->contractMaster;

    if (!$contractMaster) {

        $employee->contract_status =
            'Tidak Ada Kontrak';

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT HISTORY
    |--------------------------------------------------------------------------
    */

    $history =
        $contractMaster->currentHistory;

    if (!$history) {

        $employee->contract_status =
            'Kontrak Habis';

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORY INACTIVE
    |--------------------------------------------------------------------------
    */

    if (!$history->is_active) {

        $employee->contract_status =
            'Kontrak Habis';

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | START DATE WAJIB
    |--------------------------------------------------------------------------
    */

    if (!$history->start_date) {

        $employee->contract_status =
            'Tidak Ada Kontrak';

        return;
    }

    $contractStart =
        Carbon::parse(
            $history->start_date
        )->startOfDay();

    $contractEnd =
        $history->end_date
            ? Carbon::parse(
                $history->end_date
            )->startOfDay()
            : null;

    /*
    |--------------------------------------------------------------------------
    | PERIOD-BASED STATUS
    |--------------------------------------------------------------------------
    |
    | Jika periodStart/periodEnd diberikan, status mengikuti BULAN YANG DIPILIH,
    | bukan kondisi tanggal hari ini.
    |--------------------------------------------------------------------------
    */

    if ($periodStart && $periodEnd) {

        /*
        | Bulan yang dipilih seluruhnya sebelum contract mulai.
        */

        if (
            $periodEnd->lt(
                $contractStart
            )
        ) {

            $employee->contract_status =
                'Belum Masuk Periode Kontrak';

            return;
        }

        /*
        | Bulan yang dipilih seluruhnya setelah / mulai pada end_date.
        | end_date sendiri dianggap expired.
        */

        if (
            $contractEnd &&
            $periodStart->gte(
                $contractEnd
            )
        ) {

            $employee->contract_status =
                'Kontrak Habis';

            return;
        }

        $employee->contract_status =
            'Aktif';

        $employee->contract_status_active =
            true;

        $employee->has_active_contract =
            true;

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS BERDASARKAN HARI INI
    |--------------------------------------------------------------------------
    */

    $today =
        Carbon::today();

    if (
        $today->lt(
            $contractStart
        )
    ) {

        $employee->contract_status =
            'Belum Masuk Periode Kontrak';

        return;
    }

    if (
        $contractEnd &&
        $today->gte(
            $contractEnd
        )
    ) {

        $employee->contract_status =
            'Kontrak Habis';

        return;
    }

    $employee->contract_status =
        'Aktif';

    $employee->contract_status_active =
        true;

    $employee->has_active_contract =
        true;
}

    /*
    |--------------------------------------------------------------------------
    | FINANCIAL ACCESS
    |--------------------------------------------------------------------------
    */

    private function canViewFinancial(
        EmployeeOuterIsland $employee
    ): bool {
        /*
        | Finance selalu boleh melihat financial.
        */

        if ($this->isFinanceRole()) {
            return true;
        }

        /*
        | Head HRD hanya boleh melihat financial
        | apabila level tersimpan <= 13.
        */

        if (!$this->isHeadHrd()) {
            return false;
        }

        $contract =
            $this->currentContract(
                $employee
            );

        if (!$contract) {
            return false;
        }

        if ($contract->level === null) {
            return false;
        }

        return (int) $contract->level <= 13;
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE ALLOWANCE
    |--------------------------------------------------------------------------
    */

    private function calculateAllowance(
        ?ContractHistoryOuterIsland $contract
    ): float {
        if (!$contract) {
            return 0;
        }

        $basicSalary =
            (float) (
                $contract->basic_salary ?? 0
            );

        $level =
            (int) (
                $contract->level ?? 0
            );

        if (
            $basicSalary <= 0
            || $level <= 0
        ) {
            return 0;
        }

        $percentage =
            $level * 0.02;

        return round(
            $basicSalary * $percentage,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $period =
            $request->get(
                'period',
                now()->format('Y-m')
            );

        $periodStart =
            Carbon::createFromFormat(
                'Y-m',
                $period
            )->startOfMonth();

        $periodEnd =
            $periodStart
                ->copy()
                ->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | CUTOFF
        |--------------------------------------------------------------------------
        */

        $cutoffDay =
            (int) CompanySetting::where(
                'key',
                'attendance_cutoff_day'
            )->value('value');

        if (
            $cutoffDay < self::MIN_CUTOFF_DAY
            ||
            $cutoffDay > self::MAX_CUTOFF_DAY
        ) {
            $cutoffDay =
                self::DEFAULT_CUTOFF_DAY;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees =
            EmployeeOuterIsland::query()
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'contractMaster.currentHistory',
                ])
                ->orderBy(
                    'id_employee_outer_island',
                    'asc'
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | PAYROLL
        |--------------------------------------------------------------------------
        */

        $payrolls =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->get()
                ->keyBy(
                    'employee_outer_island_id'
                );

        /*
        |--------------------------------------------------------------------------
        | LOCK
        |--------------------------------------------------------------------------
        */

        $isLocked =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->where(
                    'is_locked',
                    true
                )
                ->exists();

        /*
        |--------------------------------------------------------------------------
        | PER EMPLOYEE
        |--------------------------------------------------------------------------
        */

        foreach ($employees as $employee) {

            /*
            | Contract menjadi indikator utama.
            */

            $this->prepareContractIndicator(
                $employee,
                $periodStart,
                $periodEnd
            );

            $employee->payroll =
                $payrolls->get(
                    $employee->id_employee_outer_island
                );

            $employee->can_view_financial =
                $this->canViewFinancial(
                    $employee
                );
        }

        /*
        |--------------------------------------------------------------------------
        | COLUMN ACCESS
        |--------------------------------------------------------------------------
        */

        $showFinancialColumns =
            $this->isFinanceRole()
            ||
            $this->isHeadHrd();

        $canManageCutoff =
            $this->isFinanceRole();

        return view(
            'payrolls.outer_island.index',
            compact(
                'employees',
                'payrolls',
                'period',
                'cutoffDay',
                'isLocked',
                'showFinancialColumns',
                'canManageCutoff'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $period =
            $request->get(
                'period',
                now()->format('Y-m')
            );

        /*
        |--------------------------------------------------------------------------
        | CUTOFF
        |--------------------------------------------------------------------------
        */

        $cutoffDay =
            (int) CompanySetting::where(
                'key',
                'attendance_cutoff_day'
            )->value('value');

        if (
            $cutoffDay < self::MIN_CUTOFF_DAY
            ||
            $cutoffDay > self::MAX_CUTOFF_DAY
        ) {
            $cutoffDay =
                self::DEFAULT_CUTOFF_DAY;
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT PERIOD LOCK
        |--------------------------------------------------------------------------
        */

        $isLocked =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->where(
                    'is_locked',
                    true
                )
                ->exists();

        /*
        |--------------------------------------------------------------------------
        | NEXT PERIOD
        |--------------------------------------------------------------------------
        */

        $nextPeriod =
            Carbon::createFromFormat(
                'Y-m',
                $period
            )
                ->addMonth()
                ->format('Y-m');

        $isNextPeriodLocked =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $nextPeriod
                )
                ->where(
                    'is_locked',
                    true
                )
                ->exists();

        /*
        |--------------------------------------------------------------------------
        | DATE RANGE
        |--------------------------------------------------------------------------
        */

        $start =
            Carbon::createFromFormat(
                'Y-m',
                $period
            )->startOfMonth();

        $end =
            $start
                ->copy()
                ->endOfMonth();

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees =
            EmployeeOuterIsland::query()
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'contractMaster.currentHistory',
                ])
                ->orderBy(
                    'full_name_outer'
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | EXISTING PAYROLL
        |--------------------------------------------------------------------------
        */

        $payrolls =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->get()
                ->keyBy(
                    'employee_outer_island_id'
                );

        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE DATABASE
        |--------------------------------------------------------------------------
        */

        $attendanceRecords =
            AttendanceRecordOuterIsland::query()
                ->whereBetween(
                    'attendance_date',
                    [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]
                )
                ->get()
                ->groupBy(
                    'employee_outer_island_id'
                );

        /*
        |--------------------------------------------------------------------------
        | HOLIDAYS
        |--------------------------------------------------------------------------
        */

        $holidays =
            Holiday::query()
                ->whereBetween(
                    'holiday_date',
                    [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | BPJS SETTINGS
        |--------------------------------------------------------------------------
        */

        $bpjsTkEmployeeRate =
            CompanySetting::where(
                'key',
                'bpjs_tk_employee_rate'
            )->value('value');

        $bpjsKsEmployeeRate =
            CompanySetting::where(
                'key',
                'bpjs_ks_employee_rate'
            )->value('value');

        $bpjsKsMaxCap =
            CompanySetting::where(
                'key',
                'bpjs_ks_max_cap'
            )->value('value');

        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */

        $isFinanceRole =
            $this->isFinanceRole();

        $isHeadHrd =
            $this->isHeadHrd();

        $showFinancialColumns =
            $isFinanceRole
            ||
            $isHeadHrd;

        $canManageCutoff =
            $isFinanceRole;

        /*
        |--------------------------------------------------------------------------
        | PER EMPLOYEE
        |--------------------------------------------------------------------------
        */

        foreach ($employees as $employee) {

            /*
            | Contract indicator.
            */

            $this->prepareContractIndicator(
                $employee,
                $start,
                $end
            );

            $employee->payroll =
                $payrolls->get(
                    $employee->id_employee_outer_island
                );

            $employee->attendance =
                $attendanceRecords->get(
                    $employee->id_employee_outer_island,
                    collect()
                );

            $employee->can_view_financial =
                $this->canViewFinancial(
                    $employee
                );

            $employee->calculated_allowance =
                $this->calculateAllowance(
                    $this->currentContract(
                        $employee
                    )
                );
        }

        return view(
            'payrolls.outer_island.create',
            compact(
                'period',
                'nextPeriod',
                'cutoffDay',
                'isLocked',
                'isNextPeriodLocked',
                'employees',
                'payrolls',
                'attendanceRecords',
                'holidays',
                'bpjsTkEmployeeRate',
                'bpjsKsEmployeeRate',
                'bpjsKsMaxCap',
                'isFinanceRole',
                'isHeadHrd',
                'showFinancialColumns',
                'canManageCutoff'
            )
        );
    }
/*
|--------------------------------------------------------------------------
| GENERATE ATTENDANCE
|--------------------------------------------------------------------------
*/

public function generateAttendance(
    Request $request
) {
    abort_unless(
        $this->isFinanceRole(),
        403
    );

    $validated =
        $request->validate([
            'period' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

    $period =
        $validated['period'];

    /*
    |--------------------------------------------------------------------------
    | CHECK LOCK
    |--------------------------------------------------------------------------
    */

    $locked =
        PayrollOuterIsland::query()
            ->where(
                'period_month',
                $period
            )
            ->where(
                'is_locked',
                true
            )
            ->exists();

    if ($locked) {
        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $period]
            )
            ->with(
                'error',
                "Periode {$period} sudah di-lock dan tidak dapat digenerate ulang."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PERIOD
    |--------------------------------------------------------------------------
    */

    $periodStart =
        Carbon::createFromFormat(
            'Y-m',
            $period
        )->startOfMonth();

    $periodEnd =
        $periodStart
            ->copy()
            ->endOfMonth();

    /*
    |--------------------------------------------------------------------------
    | HOLIDAYS
    |--------------------------------------------------------------------------
    */

    $holidays =
        Holiday::query()
            ->whereBetween(
                'holiday_date',
                [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ]
            )
            ->get()
            ->keyBy(
                function ($holiday) {
                    return Carbon::parse(
                        $holiday->holiday_date
                    )->format('Y-m-d');
                }
            );

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEES
    |--------------------------------------------------------------------------
    |
    | Employee harus:
    |
    | - aktif
    | - contract master aktif
    | - current history aktif
    |
    */

    $employees =
        EmployeeOuterIsland::query()
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'contractMaster',
                function ($query) {

                    $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->whereHas(
                            'currentHistory',
                            function ($historyQuery) {

                                $historyQuery
                                    ->where(
                                        'is_active',
                                        true
                                    );
                            }
                        );
                }
            )
            ->with([
                'contractMaster.currentHistory',
            ])
            ->get();

    /*
    |--------------------------------------------------------------------------
    | GENERATE
    |--------------------------------------------------------------------------
    */

    $created = 0;

    DB::transaction(
        function () use (
            $employees,
            $periodStart,
            $periodEnd,
            $holidays,
            &$created
        ) {

            foreach ($employees as $employee) {

                $employeeId =
                    $employee->id_employee_outer_island;

                /*
                |--------------------------------------------------------------------------
                | CURRENT CONTRACT
                |--------------------------------------------------------------------------
                */

                $contractMaster =
                    $employee->contractMaster;

                $history =
                    $contractMaster?->currentHistory;

                /*
                |--------------------------------------------------------------------------
                | SAFETY CHECK
                |--------------------------------------------------------------------------
                */

                if (
                    !$contractMaster ||
                    !$contractMaster->is_active ||
                    !$history ||
                    !$history->is_active
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | START DATE
                |--------------------------------------------------------------------------
                */

                if (!$history->start_date) {
                    continue;
                }

                $contractStart =
                    Carbon::parse(
                        $history->start_date
                    )->startOfDay();

                /*
                |--------------------------------------------------------------------------
                | END DATE
                |--------------------------------------------------------------------------
                |
                | NULL = kontrak tidak mempunyai batas akhir.
                |
                */

                $contractEnd =
                    $history->end_date
                        ? Carbon::parse(
                            $history->end_date
                        )->startOfDay()
                        : null;

                /*
                |--------------------------------------------------------------------------
                | CONTRACT DATE RANGE
                |--------------------------------------------------------------------------
                |
                | Periode payroll harus bersinggungan dengan periode kontrak.
                |
                */

                /*
                |--------------------------------------------------------------------------
                | BELUM MASUK KONTRAK
                |--------------------------------------------------------------------------
                |
                | Contoh:
                |
                | Generate Oktober
                | Start Contract November
                |
                | → Oktober tidak digenerate.
                |
                */

                if (
                    $periodEnd->lt(
                        $contractStart
                    )
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CONTRACT SUDAH HABIS
                |--------------------------------------------------------------------------
                |
                | end_date dianggap sudah expired pada tanggal tersebut.
                |
                | Contoh:
                |
                | end_date = 25 September
                |
                | Generate Oktober:
                | → tidak generate.
                |
                */

                if (
                    $contractEnd &&
                    $periodStart->gte(
                        $contractEnd
                    )
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | GENERATE DAILY
                |--------------------------------------------------------------------------
                */

                for (
                    $date = $periodStart->copy();
                    $date->lte($periodEnd);
                    $date->addDay()
                ) {

                    $dateString =
                        $date->format('Y-m-d');

                    /*
                    |--------------------------------------------------------------------------
                    | SEBELUM START DATE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $date->lt(
                            $contractStart
                        )
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | END DATE
                    |--------------------------------------------------------------------------
                    |
                    | end_date sendiri tidak digenerate.
                    |
                    */

                    if (
                        $contractEnd &&
                        $date->gte(
                            $contractEnd
                        )
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | JANGAN DUPLIKASI
                    |--------------------------------------------------------------------------
                    */

                    $exists =
                        AttendanceRecordOuterIsland::query()
                            ->where(
                                'employee_outer_island_id',
                                $employeeId
                            )
                            ->where(
                                'attendance_date',
                                $dateString
                            )
                            ->exists();

                    if ($exists) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DEFAULT STATUS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $holidays->has(
                            $dateString
                        )
                    ) {

                        $status = 'HB';

                    } elseif (
                        $date->isSunday()
                    ) {

                        $status = '-';

                    } else {

                        $status = 'H';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE ATTENDANCE
                    |--------------------------------------------------------------------------
                    */

                    AttendanceRecordOuterIsland::create([
                        'employee_outer_island_id' =>
                            $employeeId,

                        'attendance_date' =>
                            $dateString,

                        'status' =>
                            $status,

                        'source' =>
                            'generated',
                    ]);

                    $created++;
                }
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    return redirect()
        ->route(
            'payrolls.outer_island.create',
            ['period' => $period]
        )
        ->with(
            'success',
            "Absensi periode {$period} berhasil digenerate. "
            . "{$created} record baru dibuat."
        );
}
    /*
    |--------------------------------------------------------------------------
    | UPDATE CUTOFF
    |--------------------------------------------------------------------------
    */

    public function updateCutoffDay(
        Request $request
    ) {
        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],

                'cutoff_day' => [
                    'required',
                    'integer',
                    'min:' . self::MIN_CUTOFF_DAY,
                    'max:' . self::MAX_CUTOFF_DAY,
                ],
            ]);

        $period =
            $validated['period'];

        $cutoffDay =
            (int) $validated['cutoff_day'];

        $locked =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->where(
                    'is_locked',
                    true
                )
                ->exists();

        if ($locked) {
            return redirect()
                ->route(
                    'payrolls.outer_island.create',
                    ['period' => $period]
                )
                ->with(
                    'error',
                    "Periode {$period} sudah di-lock."
                );
        }

        DB::transaction(
            function () use (
                $period,
                $cutoffDay
            ) {

                CompanySetting::updateOrCreate(
                    [
                        'key' =>
                            'attendance_cutoff_day',
                    ],
                    [
                        'value' =>
                            $cutoffDay,
                    ]
                );

                PayrollOuterIsland::where(
                    'period_month',
                    $period
                )->update([
                    'cutoff_day' =>
                        $cutoffDay,
                ]);
            }
        );

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $period]
            )
            ->with(
                'success',
                "Cutoff periode {$period} berhasil diubah menjadi tanggal {$cutoffDay}."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE PAYROLL + ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ) {
        /*
        |--------------------------------------------------------------------------
        | AUTHORIZATION
        |--------------------------------------------------------------------------
        */

        if (!$this->isFinanceRole()) {
            abort(
                403,
                'Anda tidak memiliki akses untuk menghitung payroll.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],

                'cutoff_day' => [
                    'required',
                    'integer',
                    'min:' . self::MIN_CUTOFF_DAY,
                    'max:' . self::MAX_CUTOFF_DAY,
                ],

                'payrolls' => [
                    'nullable',
                    'array',
                    'max:5000',
                ],

                'payrolls.*' => [
                    'nullable',
                    'array',
                ],

                'payrolls.*.overtime_hours' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:744',
                ],

                'payrolls.*.incentive' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100000000',
                ],

                'payrolls.*.cash_advance' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100000000',
                ],

                'payrolls.*.other_deductions' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100000000',
                ],

                'attendance' => [
                    'nullable',
                    'array',
                    'max:5000',
                ],

                'attendance.*' => [
                    'nullable',
                    'array',
                    'max:31',
                ],

                'attendance.*.*' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
            ]);

        $period =
            $validated['period'];

        $cutoffDay =
            (int) $validated['cutoff_day'];

        /*
        |--------------------------------------------------------------------------
        | LOCK CHECK
        |--------------------------------------------------------------------------
        */

        $isLocked = PayrollOuterIsland::query()
            ->where('period_month', $period)
            ->where('is_locked', true)
            ->exists();

        /*
        |--------------------------------------------------------------------------
        | LOCKED PERIOD = ONLY SAVE POST-CUTOFF ATTENDANCE
        |--------------------------------------------------------------------------
        |
        | Setelah payroll di-Close:
        |
        | - tanggal <= cutoff tetap terkunci
        | - tanggal > cutoff tetap boleh diedit
        | - payroll periode yang sudah Close TIDAK dihitung ulang
        | - attendance setelah cutoff menjadi gantungan untuk periode berikutnya
        |
        | Jika periode berikutnya sudah locked, sisa tanggal periode ini
        | juga tidak boleh diubah karena gantungan sudah ikut terkunci.
        |--------------------------------------------------------------------------
        */

        if ($isLocked) {

            $nextPeriod =
                Carbon::createFromFormat(
                    'Y-m',
                    $period
                )
                    ->addMonth()
                    ->format('Y-m');

            $isNextPeriodLocked =
                PayrollOuterIsland::query()
                    ->where(
                        'period_month',
                        $nextPeriod
                    )
                    ->where(
                        'is_locked',
                        true
                    )
                    ->exists();

            if ($isNextPeriodLocked) {
                return redirect()
                    ->route(
                        'payrolls.outer_island.create',
                        ['period' => $period]
                    )
                    ->with(
                        'error',
                        "Periode {$period} dan periode {$nextPeriod} sudah di-lock. "
                        . "Attendance setelah cutoff tidak dapat diubah lagi."
                    );
            }

            DB::transaction(
                function () use (
                    $validated,
                    $period,
                    $cutoffDay
                ) {

                    $attendanceInput =
                        $validated['attendance'] ?? [];

                    if (!is_array($attendanceInput)) {
                        $attendanceInput = [];
                    }

                    foreach (
                        $attendanceInput as $employeeId => $dates
                    ) {

                        if (!is_array($dates)) {
                            continue;
                        }

                        $employee =
                            EmployeeOuterIsland::query()
                                ->with(
                                    'contractMaster.currentHistory'
                                )
                                ->find(
                                    (int) $employeeId
                                );

                        if (!$employee || !$employee->is_active) {
                            continue;
                        }

                        $contract =
                            $employee
                                ->contractMaster
                                ?->currentHistory;

                        if (
                            !$contract
                            ||
                            !$contract->is_active
                        ) {
                            continue;
                        }

                        $contractStart =
                            $contract->start_date
                                ? Carbon::parse(
                                    $contract->start_date
                                )->startOfDay()
                                : null;

                        $contractEnd =
                            $contract->end_date
                                ? Carbon::parse(
                                    $contract->end_date
                                )->startOfDay()
                                : null;

                        foreach (
                            $dates as $date => $status
                        ) {

                            try {
                                $attendanceDate =
                                    Carbon::createFromFormat(
                                        'Y-m-d',
                                        (string) $date
                                    )->startOfDay();
                            } catch (
                                \Throwable $e
                            ) {
                                continue;
                            }

                            if (
                                $attendanceDate->format('Y-m-d')
                                !==
                                (string) $date
                            ) {
                                continue;
                            }

                            if (
                                $attendanceDate->format('Y-m')
                                !==
                                $period
                            ) {
                                continue;
                            }

                            /*
                            | HANYA tanggal setelah cutoff.
                            | Tanggal <= cutoff tidak boleh disentuh
                            | walaupun request dimanipulasi.
                            */
                            if (
                                $attendanceDate->day
                                <=
                                $cutoffDay
                            ) {
                                continue;
                            }

                            /*
                            | Hormati periode kontrak.
                            */
                            if (
                                $contractStart
                                &&
                                $attendanceDate->lt(
                                    $contractStart
                                )
                            ) {
                                continue;
                            }

                            if (
                                $contractEnd
                                &&
                                $attendanceDate->gte(
                                    $contractEnd
                                )
                            ) {
                                continue;
                            }

                            $status =
                                strtoupper(
                                    trim(
                                        (string) $status
                                    )
                                );

                            /*
                            | EMPTY = DELETE attendance
                            | hanya untuk tanggal > cutoff.
                            */
                            if ($status === '') {

                                AttendanceRecordOuterIsland::query()
                                    ->where(
                                        'employee_outer_island_id',
                                        (int) $employeeId
                                    )
                                    ->where(
                                        'attendance_date',
                                        $attendanceDate->format(
                                            'Y-m-d'
                                        )
                                    )
                                    ->delete();

                                continue;
                            }

                            if (
                                !in_array(
                                    $status,
                                    self::ATTENDANCE_STATUSES,
                                    true
                                )
                            ) {
                                continue;
                            }

                            AttendanceRecordOuterIsland::updateOrCreate(
                                [
                                    'employee_outer_island_id' =>
                                        (int) $employeeId,

                                    'attendance_date' =>
                                        $attendanceDate->format(
                                            'Y-m-d'
                                        ),
                                ],
                                [
                                    'status' =>
                                        $status,

                                    'source' =>
                                        'manual',
                                ]
                            );
                        }
                    }
                }
            );

            return redirect()
                ->route(
                    'payrolls.outer_island.create',
                    ['period' => $period]
                )
                ->with(
                    'success',
                    "Attendance setelah cutoff periode {$period} berhasil disimpan. "
                    . "Payroll periode {$period} tetap terkunci dan tidak dihitung ulang."
                );
        }

        /*
        |--------------------------------------------------------------------------
        | DATE RANGE
        |--------------------------------------------------------------------------
        */

        try {

            $start =
                Carbon::createFromFormat(
                    'Y-m',
                    $period
                )->startOfMonth();

            $end =
                $start
                    ->copy()
                    ->endOfMonth();

        } catch (\Throwable $e) {

            return redirect()
                ->route(
                    'payrolls.outer_island.create',
                    ['period' => $period]
                )
                ->withInput()
                ->with(
                    'error',
                    'Periode payroll tidak valid.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | SETTINGS
        |--------------------------------------------------------------------------
        */

        $standardWorkDays =
            self::DEFAULT_STANDARD_WORK_DAYS;

        $tkEmployeeRate =
            (float) (
                CompanySetting::where(
                    'key',
                    'bpjs_tk_employee_rate'
                )->value('value')
                ?? 2.0
            ) / 100;

        $ksEmployeeRate =
            (float) (
                CompanySetting::where(
                    'key',
                    'bpjs_ks_employee_rate'
                )->value('value')
                ?? 1.0
            ) / 100;

        $ksMaxCap =
            (float) (
                CompanySetting::where(
                    'key',
                    'bpjs_ks_max_cap'
                )->value('value')
                ?? 12000000
            );

        /*
        |--------------------------------------------------------------------------
        | SETTINGS SANITY
        |--------------------------------------------------------------------------
        */

        if (
            $tkEmployeeRate < 0
            ||
            $tkEmployeeRate > 1
        ) {
            abort(
                500,
                'Konfigurasi BPJS TK tidak valid.'
            );
        }

        if (
            $ksEmployeeRate < 0
            ||
            $ksEmployeeRate > 1
        ) {
            abort(
                500,
                'Konfigurasi BPJS Kesehatan tidak valid.'
            );
        }

        if (
            $ksMaxCap < 0
            ||
            $ksMaxCap > 1000000000
        ) {
            abort(
                500,
                'Konfigurasi batas BPJS Kesehatan tidak valid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees =
            EmployeeOuterIsland::query()
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'contractMaster.currentHistory',
                ])
                ->orderBy(
                    'id_employee_outer_island',
                    'asc'
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | CONTRACT DATE IS THE GENERATION FILTER
        |--------------------------------------------------------------------------
        |
        | Jangan memakai status kontrak berdasarkan HARI INI di sini.
        | Generate payroll harus mengikuti periode yang dipilih.
        |
        | Contoh:
        |
        | September 2026 -> kontrak mulai Oktober 2026
        | -> September tidak dibuat.
        |
        | Oktober 2026 -> kontrak mulai 15 Oktober 2026
        | -> 1-14 Oktober kosong, 15 Oktober dst dibuat.
        |
        | Oktober 2026 -> kontrak berakhir 25 Oktober 2026
        | -> 1-24 Oktober dibuat, 25 Oktober dst tidak dibuat.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $employees,
                $validated,
                $period,
                $cutoffDay,
                $standardWorkDays,
                $start,
                $end,
                $tkEmployeeRate,
                $ksEmployeeRate,
                $ksMaxCap
            ) {

                foreach ($employees as $employee) {

                    $employeeId =
                        $employee->id_employee_outer_island;

                    /*
                    |--------------------------------------------------------------------------
                    | CURRENT CONTRACT
                    |--------------------------------------------------------------------------
                    */

                    $contract =
                        $this->currentContract(
                            $employee
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | PERIOD CONTRACT STATUS
                    |--------------------------------------------------------------------------
                    |
                    | Employee TETAP masuk payroll walaupun kontraknya sudah habis.
                    | Payroll dibuat dengan seluruh nominal = 0 supaya nama employee
                    | tetap tampil di create.blade.php dan di histori payroll.
                    |
                    | end_date sendiri dianggap sudah habis.
                    */

                    $contractStart =
                        $contract?->start_date
                            ? Carbon::parse(
                                $contract->start_date
                            )->startOfDay()
                            : null;

                    $contractEnd =
                        $contract?->end_date
                            ? Carbon::parse(
                                $contract->end_date
                            )->startOfDay()
                            : null;

                    $periodContractInvalid =
                        !$contract
                        ||
                        !$contract->is_active
                        ||
                        ($contractStart && $end->lt($contractStart))
                        ||
                        ($contractEnd && $start->gte($contractEnd));

                    if ($periodContractInvalid) {

                        PayrollOuterIsland::updateOrCreate(
                            [
                                'employee_outer_island_id' =>
                                    $employeeId,

                                'period_month' =>
                                    $period,
                            ],
                            [
                                'cutoff_day' => $cutoffDay,
                                'daily_attendance' => [],
                                'work_days' => 0,
                                'unpaid_leave' => 0,
                                'gantungan_days' => 0,
                                'overtime_hours' => 0,
                                'basic_salary' => 0,
                                'allowance' => 0,
                                'overtime_pay' => 0,
                                'maternity_leave_pay' => 0,
                                'incentive' => 0,
                                'cash_advance' => 0,
                                'other_deductions' => 0,
                                'gantungan_deduction' => 0,
                                'previous_gantungan_deduction' => 0,
                                'bpjs_tk_deduction' => 0,
                                'bpjs_ks_deduction' => 0,
                                'is_bpjs_override' => false,
                                'pph21_deduction' => 0,
                                'gross_salary' => 0,
                                'net_salary' => 0,
                                'status' => 'Draft',
                            ]
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | BASIC SALARY
                    |--------------------------------------------------------------------------
                    */

                    $basicSalary =
                        (float) (
                            $contract->basic_salary
                            ?? 0
                        );

                    if (
                        $basicSalary < 0
                        ||
                        $basicSalary > 1000000000
                    ) {
                        throw new \RuntimeException(
                            "Basic salary employee ID {$employeeId} tidak valid."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ATTENDANCE INPUT
                    |--------------------------------------------------------------------------
                    */

                    $attendanceInput =
                        $validated['attendance'][$employeeId]
                        ?? [];

                    if (!is_array($attendanceInput)) {
                        $attendanceInput = [];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | PAYROLL INPUT
                    |--------------------------------------------------------------------------
                    */

                    $payrollInput =
                        $validated['payrolls'][$employeeId]
                        ?? [];

                    if (!is_array($payrollInput)) {
                        $payrollInput = [];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SAVE ATTENDANCE
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $attendanceInput as $date => $status
                    ) {

                        try {

                            $attendanceDate =
                                Carbon::createFromFormat(
                                    'Y-m-d',
                                    (string) $date
                                );

                        } catch (\Throwable $e) {

                            continue;
                        }

                        if (
                            $attendanceDate->format('Y-m-d')
                            !==
                            (string) $date
                        ) {
                            continue;
                        }

                        if (
                            $attendanceDate->format('Y-m')
                            !==
                            $period
                        ) {
                            continue;
                        }

                        $status =
                            strtoupper(
                                trim(
                                    (string) $status
                                )
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | EMPTY = DELETE
                        |--------------------------------------------------------------------------
                        */

                        if ($status === '') {

                            AttendanceRecordOuterIsland::query()
                                ->where(
                                    'employee_outer_island_id',
                                    $employeeId
                                )
                                ->where(
                                    'attendance_date',
                                    $attendanceDate->format(
                                        'Y-m-d'
                                    )
                                )
                                ->delete();

                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | VALID STATUS
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !in_array(
                                $status,
                                self::ATTENDANCE_STATUSES,
                                true
                            )
                        ) {
                            continue;
                        }

                        AttendanceRecordOuterIsland::updateOrCreate(
                            [
                                'employee_outer_island_id' =>
                                    $employeeId,

                                'attendance_date' =>
                                    $attendanceDate->format(
                                        'Y-m-d'
                                    ),
                            ],
                            [
                                'status' =>
                                    $status,

                                'source' =>
                                    'manual',
                            ]
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | READ ATTENDANCE
                    |--------------------------------------------------------------------------
                    */

                    $attendanceRecords =
                        AttendanceRecordOuterIsland::query()
                            ->where(
                                'employee_outer_island_id',
                                $employeeId
                            )
                            ->whereBetween(
                                'attendance_date',
                                [
                                    $start->toDateString(),
                                    $end->toDateString(),
                                ]
                            )
                            ->get();

                    /*
                    |--------------------------------------------------------------------------
                    | DAILY ATTENDANCE
                    |--------------------------------------------------------------------------
                    */

                    $dailyAttendance =
                        $this->buildEffectiveDailyAttendance(
                            $period,
                            $attendanceRecords
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | SUMMARY
                    |--------------------------------------------------------------------------
                    */

                    $summary =
                        $this->summarizeAttendance(
                            $dailyAttendance,
                            $cutoffDay
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | ALLOWANCE
                    |--------------------------------------------------------------------------
                    */

                    $allowance =
                        $this->calculateAllowance(
                            $contract
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | CURRENT GANTUNGAN
                    |--------------------------------------------------------------------------
                    */

                    $gantunganDays =
                        $this->calculateCurrentGantungan(
                            $attendanceRecords,
                            $cutoffDay
                        );

                    $gantunganDeduction =
                        $standardWorkDays > 0
                            ? (
                                (
                                    $basicSalary
                                    +
                                    $allowance
                                )
                                /
                                $standardWorkDays
                            )
                            *
                            $gantunganDays
                            : 0;

                    /*
                    |--------------------------------------------------------------------------
                    | PREVIOUS PERIOD
                    |--------------------------------------------------------------------------
                    */

                    $previousPeriod =
                        Carbon::createFromFormat(
                            'Y-m',
                            $period
                        )
                            ->subMonth()
                            ->format('Y-m');

                    $previousPayroll =
                        PayrollOuterIsland::query()
                            ->where(
                                'employee_outer_island_id',
                                $employeeId
                            )
                            ->where(
                                'period_month',
                                $previousPeriod
                            )
                            ->first();

                    $previousGantungan =
                        $this->calculatePreviousGantungan(
                            $previousPayroll
                        );

                    $previousGantunganDeduction =
                        (float) (
                            $previousGantungan['deduction']
                            ?? 0
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | BPJS
                    |--------------------------------------------------------------------------
                    */

                    $bpjsTkDeduction = 0;
                    $bpjsKsDeduction = 0;
                    $bpjsCompany = 0;
                    $isBpjsOverride = false;

                    $isBpjsTkActive =
                        $this->toBoolean(
                            $contract->is_bpjstk_active
                        );

                    $isBpjsHealthActive =
                        $this->toBoolean(
                            $contract->is_bpjs_health_active
                        );

                    $useManualBpjs =
                        $this->toBoolean(
                            $contract->use_manual_bpjs
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | MANUAL BPJS
                    |--------------------------------------------------------------------------
                    */

                    if ($useManualBpjs) {

                        $manualTk =
                            (float) (
                                $contract
                                    ->manual_bpjs_tk_employee
                                ?? 0
                            );

                        $manualKs =
                            (float) (
                                $contract
                                    ->manual_bpjs_ks_employee
                                ?? 0
                            );

                        $manualCompany =
                            (float) (
                                $contract
                                    ->manual_bpjs_company
                                ?? 0
                            );

                        if (
                            $manualTk < 0
                            ||
                            $manualTk > 100000000
                        ) {
                            throw new \RuntimeException(
                                "Manual BPJS TK employee ID {$employeeId} tidak valid."
                            );
                        }

                        if (
                            $manualKs < 0
                            ||
                            $manualKs > 100000000
                        ) {
                            throw new \RuntimeException(
                                "Manual BPJS Kesehatan employee ID {$employeeId} tidak valid."
                            );
                        }

                        if (
                            $manualCompany < 0
                            ||
                            $manualCompany > 100000000
                        ) {
                            throw new \RuntimeException(
                                "Manual BPJS Company employee ID {$employeeId} tidak valid."
                            );
                        }

                        $bpjsTkDeduction =
                            $manualTk;

                        $bpjsKsDeduction =
                            $manualKs;

                        $bpjsCompany =
                            $manualCompany;

                        $isBpjsOverride =
                            true;

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | AUTO BPJS TK
                        |--------------------------------------------------------------------------
                        */

                        if ($isBpjsTkActive) {

                            $bpjsTkDeduction =
                                round(
                                    $basicSalary
                                    *
                                    $tkEmployeeRate,
                                    2
                                );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | AUTO BPJS HEALTH
                        |--------------------------------------------------------------------------
                        */

                        if ($isBpjsHealthActive) {

                            $bpjsBase =
                                min(
                                    $basicSalary,
                                    $ksMaxCap
                                );

                            $bpjsKsDeduction =
                                round(
                                    $bpjsBase
                                    *
                                    $ksEmployeeRate,
                                    2
                                );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | FINAL BPJS SANITY
                    |--------------------------------------------------------------------------
                    */

                    $bpjsTkDeduction =
                        round(
                            max(
                                0,
                                $bpjsTkDeduction
                            ),
                            2
                        );

                    $bpjsKsDeduction =
                        round(
                            max(
                                0,
                                $bpjsKsDeduction
                            ),
                            2
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | OVERTIME
                    |--------------------------------------------------------------------------
                    */

                    $overtimeHours =
                        (float) (
                            $payrollInput[
                                'overtime_hours'
                            ]
                            ?? 0
                        );

                    if (
                        $overtimeHours < 0
                        ||
                        $overtimeHours > 744
                    ) {
                        throw new \RuntimeException(
                            "Jumlah overtime employee ID {$employeeId} tidak valid."
                        );
                    }

                    $overtimePay =
                        $overtimeHours
                        *
                        self::DEFAULT_OVERTIME_RATE;

                    /*
                    |--------------------------------------------------------------------------
                    | INCENTIVE
                    |--------------------------------------------------------------------------
                    */

                    $incentive =
                        (float) (
                            $payrollInput[
                                'incentive'
                            ]
                            ?? 0
                        );

                    if (
                        $incentive < 0
                        ||
                        $incentive > 100000000
                    ) {
                        throw new \RuntimeException(
                            "Incentive employee ID {$employeeId} tidak valid."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CASH ADVANCE
                    |--------------------------------------------------------------------------
                    */

                    $cashAdvance =
                        (float) (
                            $payrollInput[
                                'cash_advance'
                            ]
                            ?? 0
                        );

                    if (
                        $cashAdvance < 0
                        ||
                        $cashAdvance > 100000000
                    ) {
                        throw new \RuntimeException(
                            "Cash advance employee ID {$employeeId} tidak valid."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | OTHER DEDUCTIONS
                    |--------------------------------------------------------------------------
                    */

                    $otherDeductions =
                        (float) (
                            $payrollInput[
                                'other_deductions'
                            ]
                            ?? 0
                        );

                    if (
                        $otherDeductions < 0
                        ||
                        $otherDeductions > 100000000
                    ) {
                        throw new \RuntimeException(
                            "Other deductions employee ID {$employeeId} tidak valid."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | GROSS
                    |--------------------------------------------------------------------------
                    */

                    $grossSalary =
                        $basicSalary
                        +
                        $allowance
                        +
                        $overtimePay
                        +
                        $incentive;

                    /*
                    |--------------------------------------------------------------------------
                    | PPH21
                    |--------------------------------------------------------------------------
                    */

                    $pph21Deduction = 0;

                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL DEDUCTIONS
                    |--------------------------------------------------------------------------
                    */

                    $totalDeductions =
                        $bpjsTkDeduction
                        +
                        $bpjsKsDeduction
                        +
                        $pph21Deduction
                        +
                        $cashAdvance
                        +
                        $previousGantunganDeduction
                        +
                        $otherDeductions;

                    /*
                    |--------------------------------------------------------------------------
                    | NET
                    |--------------------------------------------------------------------------
                    */

                    $netSalary =
                        $grossSalary
                        -
                        $totalDeductions;

                    /*
                    |--------------------------------------------------------------------------
                    | FINAL NUMERIC SANITY
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !is_finite($grossSalary)
                        ||
                        !is_finite($netSalary)
                        ||
                        abs($grossSalary) > 10000000000
                        ||
                        abs($netSalary) > 10000000000
                    ) {
                        throw new \RuntimeException(
                            "Hasil perhitungan payroll employee ID {$employeeId} tidak valid."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SAVE PAYROLL
                    |--------------------------------------------------------------------------
                    */

                    PayrollOuterIsland::updateOrCreate(
                        [
                            'employee_outer_island_id' =>
                                $employeeId,

                            'period_month' =>
                                $period,
                        ],
                        [
                            'cutoff_day' =>
                                $cutoffDay,

                            'daily_attendance' =>
                                $dailyAttendance,

                            'work_days' =>
                                $summary['work_days'],

                            'unpaid_leave' =>
                                $summary['unpaid_leave'],

                            'gantungan_days' =>
                                $gantunganDays,

                            'overtime_hours' =>
                                round(
                                    $overtimeHours,
                                    2
                                ),

                            'basic_salary' =>
                                round(
                                    $basicSalary,
                                    2
                                ),

                            'allowance' =>
                                round(
                                    $allowance,
                                    2
                                ),

                            'overtime_pay' =>
                                round(
                                    $overtimePay,
                                    2
                                ),

                            'maternity_leave_pay' =>
                                round(
                                    (float) (
                                        $summary[
                                            'maternity_leave_pay'
                                        ]
                                        ?? 0
                                    ),
                                    2
                                ),

                            'incentive' =>
                                round(
                                    $incentive,
                                    2
                                ),

                            'cash_advance' =>
                                round(
                                    $cashAdvance,
                                    2
                                ),

                            'other_deductions' =>
                                round(
                                    $otherDeductions,
                                    2
                                ),

                            'gantungan_deduction' =>
                                round(
                                    $gantunganDeduction,
                                    2
                                ),

                            'previous_gantungan_deduction' =>
                                round(
                                    $previousGantunganDeduction,
                                    2
                                ),

                            'bpjs_tk_deduction' =>
                                $bpjsTkDeduction,

                            'bpjs_ks_deduction' =>
                                $bpjsKsDeduction,

                            'is_bpjs_override' =>
                                $isBpjsOverride,

                            'pph21_deduction' =>
                                round(
                                    $pph21Deduction,
                                    2
                                ),

                            'gross_salary' =>
                                round(
                                    $grossSalary,
                                    2
                                ),

                            'net_salary' =>
                                round(
                                    $netSalary,
                                    2
                                ),

                            'status' =>
                                'Draft',
                        ]
                    );
                }
            }
        );

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                [
                    'period' =>
                        $period,
                ]
            )
            ->with(
                'success',
                "Payroll Outer Island periode {$period} berhasil dihitung."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD EFFECTIVE ATTENDANCE
    |--------------------------------------------------------------------------
    */

    private function buildEffectiveDailyAttendance(
        string $period,
        $attendanceRecords
    ): array {

        $start =
            Carbon::createFromFormat(
                'Y-m',
                $period
            )->startOfMonth();

        $end =
            $start
                ->copy()
                ->endOfMonth();

        $records =
            $attendanceRecords->keyBy(
                function ($record) {
                    return Carbon::parse(
                        $record->attendance_date
                    )->format('Y-m-d');
                }
            );

        $result = [];

        for (
            $date = $start->copy();
            $date->lte($end);
            $date->addDay()
        ) {

            $dateString =
                $date->format('Y-m-d');

            if (
                $records->has(
                    $dateString
                )
            ) {

                $result[$dateString] =
                    strtoupper(
                        trim(
                            (string) (
                                $records
                                    ->get($dateString)
                                    ->status
                            )
                        )
                    );
            }
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY ATTENDANCE
    |--------------------------------------------------------------------------
    */

    private function summarizeAttendance(
        array $dailyAttendance,
        int $cutoffDay
    ): array {

        $workDays = 0;
        $unpaidLeave = 0;
        $normative = 0;
        $holiday = 0;
        $maternityLeavePay = 0;

        foreach (
            $dailyAttendance as $date => $status
        ) {

            $day =
                Carbon::parse(
                    $date
                )->day;

            switch ($status) {

                case 'H':

                    $workDays += 1;

                    break;

                case 'H0.5':

                    $workDays += 0.5;

                    if (
                        $day <= $cutoffDay
                    ) {
                        $unpaidLeave += 0.5;
                    }

                    break;

                case 'A':
                case 'I':

                    if (
                        $day <= $cutoffDay
                    ) {
                        $unpaidLeave += 1;
                    }

                    break;

                case 'SKD':
                case 'S':
                case 'C':
                case 'CM':

                    $workDays += 1;

                    if (
                        $status === 'CM'
                    ) {
                        $normative += 1;
                    }

                    break;

                case 'M/HB':

                    $workDays += 1;
                    $normative += 1;

                    break;

                case 'HB':

                    $workDays += 1;
                    $holiday += 1;

                    break;

                case '-':
                    break;
            }
        }

        return [
            'work_days' =>
                round(
                    $workDays,
                    1
                ),

            'unpaid_leave' =>
                round(
                    $unpaidLeave,
                    1
                ),

            'normative_days' =>
                round(
                    $normative,
                    1
                ),

            'holiday_days' =>
                round(
                    $holiday,
                    1
                ),

            'maternity_leave_pay' =>
                $maternityLeavePay,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT GANTUNGAN
    |--------------------------------------------------------------------------
    */

    private function calculateCurrentGantungan(
        $attendanceRecords,
        int $cutoffDay
    ): float {

        $days = 0;

        foreach (
            $attendanceRecords as $record
        ) {

            $date =
                Carbon::parse(
                    $record->attendance_date
                );

            if (
                $date->day <= $cutoffDay
            ) {
                continue;
            }

            switch (
                strtoupper(
                    trim(
                        (string) $record->status
                    )
                )
            ) {

                case 'A':
                case 'I':

                    $days += 1;

                    break;

                case 'H0.5':

                    $days += 0.5;

                    break;
            }
        }

        return round(
            $days,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIOUS GANTUNGAN
    |--------------------------------------------------------------------------
    */

    private function calculatePreviousGantungan(
        ?PayrollOuterIsland $previousPayroll
    ): array {

        if (!$previousPayroll) {

            return [
                'days' =>
                    0,

                'deduction' =>
                    0,

                'cutoff_day' =>
                    null,

                'basic_salary' =>
                    0,

                'allowance' =>
                    0,
            ];
        }

        return [
            'days' =>
                round(
                    (float) (
                        $previousPayroll
                            ->gantungan_days
                        ?? 0
                    ),
                    1
                ),

            'deduction' =>
                round(
                    (float) (
                        $previousPayroll
                            ->gantungan_deduction
                        ?? 0
                    ),
                    2
                ),

            'cutoff_day' =>
                $previousPayroll->cutoff_day !== null
                    ? (int) $previousPayroll->cutoff_day
                    : null,

            'basic_salary' =>
                (float) (
                    $previousPayroll
                        ->basic_salary
                    ?? 0
                ),

            'allowance' =>
                (float) (
                    $previousPayroll
                        ->allowance
                    ?? 0
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LOCK CALCULATION
    |--------------------------------------------------------------------------
    */

    public function lockCalculation(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],

                'cutoff_day' => [
                    'required',
                    'integer',
                    'min:' . self::MIN_CUTOFF_DAY,
                    'max:' . self::MAX_CUTOFF_DAY,
                ],
            ]);

        $period =
            $validated['period'];

        $cutoffDay =
            (int) $validated['cutoff_day'];

        $payrollQuery =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                );

        $count =
            $payrollQuery->count();

        if ($count === 0) {

            return redirect()
                ->route(
                    'payrolls.outer_island.create',
                    ['period' => $period]
                )
                ->with(
                    'error',
                    "Tidak ada payroll Outer Island untuk periode {$period}. "
                    . "Simpan/generate payroll terlebih dahulu."
                );
        }

        DB::transaction(
            function () use (
                $period,
                $cutoffDay
            ) {

                $periodStart = Carbon::createFromFormat(
                    'Y-m',
                    $period
                )->startOfMonth();

                $periodEnd = $periodStart->copy()->endOfMonth();

                /*
                |--------------------------------------------------------------
                | FINAL CONTRACT SAFETY
                |--------------------------------------------------------------
                | Jika kontrak berubah setelah payroll dihitung tetapi sebelum
                | lock, payroll employee yang sudah tidak valid untuk periode
                | tersebut dipastikan kembali menjadi Rp0.
                */
                $payrollRows = PayrollOuterIsland::query()
                    ->where('period_month', $period)
                    ->where('is_locked', false)
                    ->get();

                foreach ($payrollRows as $payrollRow) {
                    $employee = EmployeeOuterIsland::query()
                        ->with('contractMaster.currentHistory')
                        ->find($payrollRow->employee_outer_island_id);

                    $contract = $employee?->contractMaster?->currentHistory;

                    $contractStart = $contract?->start_date
                        ? Carbon::parse($contract->start_date)->startOfDay()
                        : null;

                    $contractEnd = $contract?->end_date
                        ? Carbon::parse($contract->end_date)->startOfDay()
                        : null;

                    $invalidForPeriod =
                        !$employee
                        ||
                        !$contract
                        ||
                        !$contract->is_active
                        ||
                        ($contractStart && $periodEnd->lt($contractStart))
                        ||
                        ($contractEnd && $periodStart->gte($contractEnd));

                    if ($invalidForPeriod) {
                        $payrollRow->update([
                            'daily_attendance' => [],
                            'work_days' => 0,
                            'unpaid_leave' => 0,
                            'gantungan_days' => 0,
                            'overtime_hours' => 0,
                            'basic_salary' => 0,
                            'allowance' => 0,
                            'overtime_pay' => 0,
                            'maternity_leave_pay' => 0,
                            'incentive' => 0,
                            'cash_advance' => 0,
                            'other_deductions' => 0,
                            'gantungan_deduction' => 0,
                            'previous_gantungan_deduction' => 0,
                            'bpjs_tk_deduction' => 0,
                            'bpjs_ks_deduction' => 0,
                            'is_bpjs_override' => false,
                            'pph21_deduction' => 0,
                            'gross_salary' => 0,
                            'net_salary' => 0,
                        ]);
                    }
                }

                PayrollOuterIsland::query()
                    ->where(
                        'period_month',
                        $period
                    )
                    ->update([
                        'cutoff_day' =>
                            $cutoffDay,

                        'is_locked' =>
                            true,

                        'locked_at' =>
                            now(),

                        'locked_by' =>
                            Auth::id(),

                        'status' =>
                            'Approved',
                    ]);

                CompanySetting::updateOrCreate(
                    [
                        'key' =>
                            'attendance_cutoff_day',
                    ],
                    [
                        'value' =>
                            $cutoffDay,
                    ]
                );
            }
        );

        $lockedCount =
            PayrollOuterIsland::query()
                ->where(
                    'period_month',
                    $period
                )
                ->where(
                    'is_locked',
                    true
                )
                ->count();

        if ($lockedCount === 0) {

            return redirect()
                ->route(
                    'payrolls.outer_island.create',
                    ['period' => $period]
                )
                ->with(
                    'error',
                    "Periode {$period} gagal dikunci."
                );
        }

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $period]
            )
            ->with(
                'success',
                "Periode {$period} berhasil di-Close. "
                . "{$lockedCount} payroll terkunci."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST UNLOCK
    |--------------------------------------------------------------------------
    */

    public function requestUnlock(
        Request $request
    ) {

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],

                'unlock_reason' => [
                    'required',
                    'string',
                    'max:1000',
                ],
            ]);

        $period =
            $validated['period'];

        PayrollOuterIsland::query()
            ->where(
                'period_month',
                $period
            )
            ->update([
                'unlock_requested' =>
                    true,

                'unlock_reason' =>
                    $validated['unlock_reason'],

                'requested_by' =>
                    Auth::id(),
            ]);

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $period]
            )
            ->with(
                'success',
                'Permintaan unlock berhasil dikirim.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | UNLOCK
    |--------------------------------------------------------------------------
    */

    public function unlockCalculation(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],
            ]);

        PayrollOuterIsland::query()
            ->where(
                'period_month',
                $validated['period']
            )
            ->update([
                'is_locked' =>
                    false,

                'unlock_requested' =>
                    false,

                'unlock_reason' =>
                    null,

                'requested_by' =>
                    null,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'status' =>
                    'Draft',
            ]);

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $validated['period']]
            )
            ->with(
                'success',
                "Periode {$validated['period']} berhasil di-unlock."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT UNLOCK
    |--------------------------------------------------------------------------
    */

    public function rejectUnlock(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],
            ]);

        PayrollOuterIsland::query()
            ->where(
                'period_month',
                $validated['period']
            )
            ->update([
                'unlock_requested' =>
                    false,

                'unlock_reason' =>
                    null,

                'requested_by' =>
                    null,
            ]);

        return redirect()
            ->route(
                'payrolls.outer_island.create',
                ['period' => $validated['period']]
            )
            ->with(
                'success',
                'Permintaan unlock ditolak.'
            );
    }
    /**
     * ============================================================
     * PRINT PDF SLIP GAJI OUTER ISLAND
     * ============================================================
     */
    public function printPdf(
        Request $request,
        string $uuid
    ) {
        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        */

        $period = $request->get(
            'period',
            now()->format('Y-m')
        );

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            abort(
                422,
                'Periode payroll tidak valid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE BERDASARKAN UUID
        |--------------------------------------------------------------------------
        */

        $employee = EmployeeOuterIsland::query()
            ->with([
                'contractMaster.currentHistory',
            ])
            ->where(
                'uuid',
                $uuid
            )
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | PAYROLL
        |--------------------------------------------------------------------------
        */

        $payroll = PayrollOuterIsland::query()
            ->where(
                'employee_outer_island_id',
                $employee->id_employee_outer_island
            )
            ->where(
                'period_month',
                $period
            )
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | CURRENT CONTRACT
        |--------------------------------------------------------------------------
        */

        $contract = $employee
            ->contractMaster
            ?->currentHistory;

        /*
        |--------------------------------------------------------------------------
        | GENERATE PDF
        |--------------------------------------------------------------------------
        */

        $pdf = Pdf::loadView(
            'payrolls.outer_island.pdf_slip',
            [
                'employee' => $employee,
                'payroll'  => $payroll,
                'contract' => $contract,
                'period'   => $period,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | PAPER
        |--------------------------------------------------------------------------
        */

        $pdf->setPaper(
            'A4',
            'portrait'
        );

        /*
        |--------------------------------------------------------------------------
        | FILE NAME
        |--------------------------------------------------------------------------
        */

        $safeName = preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '_',
            $employee->full_name_outer
        );

        /*
        |--------------------------------------------------------------------------
        | STREAM PDF
        |--------------------------------------------------------------------------
        */

        return $pdf->stream(
            'Slip_Gaji_Outer_Island_'
            . $safeName
            . '_'
            . $period
            . '.pdf'
        );
    }
}