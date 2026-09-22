<?php

namespace App\Http\Controllers;

use App\Imports\AttendanceImport;
use App\Mail\SalarySlipMail;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use ZipArchive;

class PayrollController extends Controller
{
    private const FINANCE_ROLES = [
        'manager_keuangan',
        'super_admin',
    ];

    private const HEAD_HRD_ROLE = 'kepala_hrd';
    private const HRD_ROLE = 'hrd';

    private const DEFAULT_CUTOFF_DAY = 26;
    private const MIN_CUTOFF_DAY = 20;
    private const MAX_CUTOFF_DAY = 28;

    private const DEFAULT_STANDARD_WORK_DAYS = 26;
    private const DEFAULT_OVERTIME_RATE = 20000;

    // ============================================================
    // ROLE HELPERS
    // ============================================================

    private function userRole(): string
    {
        return (string) (Auth::user()->role ?? '');
    }

    private function isFinanceRole(): bool
    {
        return in_array(
            $this->userRole(),
            self::FINANCE_ROLES,
            true
        );
    }

    private function canManageCutoff(): bool
    {
        return $this->isFinanceRole();
    }

    private function canEditFinancial(): bool
    {
        return $this->isFinanceRole();
    }

    private function canViewFinancial(Employee $employee): bool
    {
        if ($this->isFinanceRole()) {
            return true;
        }

        if ($this->userRole() !== self::HEAD_HRD_ROLE) {
            return false;
        }

        $level = $employee->activeContract?->level;

        return $level !== null
            && (int) $level <= 13;
    }

    private function canViewFinancialFromLevel(?int $level): bool
    {
        if ($this->isFinanceRole()) {
            return true;
        }

        return $this->userRole() === self::HEAD_HRD_ROLE
            && $level !== null
            && (int) $level <= 13;
    }

    // ============================================================
    // MASTER SETTINGS
    // ============================================================

    private function standardWorkDays(): float
    {
        return (float) CompanySetting::get(
            'standard_work_days',
            self::DEFAULT_STANDARD_WORK_DAYS
        );
    }

    private function overtimeRate(): float
    {
        return (float) CompanySetting::get(
            'overtime_hour_rate',
            self::DEFAULT_OVERTIME_RATE
        );
    }

    private function defaultCutoffDay(): int
    {
        return (int) CompanySetting::get(
            'attendance_cutoff_day',
            self::DEFAULT_CUTOFF_DAY
        );
    }

    private function getPeriodCutoffDay(string $period): int
    {
        $periodCutoff = Payroll::where(
            'period_month',
            $period
        )
            ->whereNotNull('cutoff_day')
            ->value('cutoff_day');

        if ($periodCutoff !== null) {
            return (int) $periodCutoff;
        }

        return $this->defaultCutoffDay();
    }

    // ============================================================
    // INDEX
    // ============================================================

    public function index(Request $request)
    {
        $period = $request->get(
            'period',
            date('Y-m')
        );

        $request->validate([
            'period' => [
                'nullable',
                'date_format:Y-m',
            ],
        ]);

        $employees = Employee::with('activeContract')
            ->where('is_active', true)
            ->get();

        $payrollCollection = Payroll::with([
            'employee.activeContract',
        ])
            ->where(
                'period_month',
                $period
            )
            ->get();

        $payrollByEmployee =
            $payrollCollection->keyBy(
                'employee_id'
            );

        $payrolls = $employees
            ->map(function ($employee) use (
                $payrollByEmployee
            ) {
                return $payrollByEmployee->get(
                    $employee->id_employee
                );
            })
            ->filter()
            ->values();

        $payrollsByDepartment =
            $payrolls->groupBy(function ($payroll) {
                return $payroll->employee?->activeContract?->department
                    ?? 'Tanpa Department';
            });

        return view(
            'payrolls.local.index',
            compact(
                'payrolls',
                'payrollsByDepartment',
                'period'
            )
        );
    }

    // ============================================================
    // CREATE
    // ============================================================

    public function create(Request $request)
    {
        $request->validate([
            'period' => [
                'nullable',
                'date_format:Y-m',
            ],
        ]);

        $period = $request->get(
            'period',
            date('Y-m')
        );

        $cutoffDay =
            $this->getPeriodCutoffDay($period);

        $savedCutoffDay = $cutoffDay;

        $isLocked = Payroll::where(
            'period_month',
            $period
        )
            ->where('is_locked', true)
            ->exists();

        $nextPeriod = Carbon::createFromFormat(
            'Y-m-d',
            $period . '-01'
        )
            ->addMonth()
            ->format('Y-m');

        $isNextPeriodLocked =
            Payroll::where(
                'period_month',
                $nextPeriod
            )
                ->where('is_locked', true)
                ->exists();

        // ========================================================
        // HOLIDAY
        // ========================================================

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereBetween(
                'holiday_date',
                [
                    $period . '-01',
                    Carbon::createFromFormat(
                        'Y-m-d',
                        $period . '-01'
                    )
                        ->endOfMonth()
                        ->format('Y-m-d'),
                ]
            )
            ->orderBy('holiday_date')
            ->get();

        // ========================================================
        // EMPLOYEES
        // ========================================================

        $employees = Employee::with([

            'activeContract',

            'payrolls' => function ($q) use ($period) {

                $q->where(
                    'period_month',
                    $period
                )
                    ->select([
                        'id_payroll',
                        'employee_id',
                        'period_month',
                        'cutoff_day',
                        'daily_attendance',
                        'overtime_hours',
                        'incentive',
                        'cash_advance',
                        'other_deductions',
                        'gantungan_days',
                        'gantungan_deduction',
                        'previous_gantungan_deduction',
                        'bpjs_tk_deduction',
                        'bpjs_ks_deduction',
                        'is_bpjs_override',
                        'maternity_leave_pay',
                        'basic_salary',
                        'allowance',
                        'overtime_pay',
                        'unpaid_leave',
                        'work_days',
                        'pph21_deduction',
                        'gross_salary',
                        'net_salary',
                        'status',
                        'is_locked',
                    ]);
            },

            /*
             * AttendanceRecord adalah DATA ASLI ABSENSI.
             *
             * Payroll snapshot tidak digunakan sebagai sumber
             * absensi.
             */
            'attendanceRecords' => function ($q) use ($period) {

                $startDate = Carbon::createFromFormat(
                    'Y-m-d',
                    $period . '-01'
                )->startOfMonth();

                $endDate =
                    $startDate->copy()->endOfMonth();

                $q->whereBetween(
                    'attendance_date',
                    [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ]
                )
                    ->orderBy('attendance_date');
            },

        ])
            ->where('is_active', true)
            ->get();

        return view(
            'payrolls.local.create',
            compact(
                'employees',
                'period',
                'isLocked',
                'isNextPeriodLocked',
                'cutoffDay',
                'savedCutoffDay',
                'holidays'
            )
        );
    }

    // ============================================================
    // UPDATE CUTOFF
    // ============================================================

    public function updateCutoffDay(
        Request $request
    ) {
        abort_unless(
            $this->canManageCutoff(),
            403
        );

        $validated = $request->validate([
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

        $isLocked = Payroll::where(
            'period_month',
            $period
        )
            ->where('is_locked', true)
            ->exists();

        if ($isLocked) {
            return redirect()
                ->route(
                    'payrolls.local.create',
                    [
                        'period' => $period,
                    ]
                )
                ->with(
                    'error',
                    'Cut-off periode yang sudah terkunci tidak dapat diubah.'
                );
        }

        CompanySetting::updateOrCreate(
            ['key' => 'attendance_cutoff_day'],
            ['value' => $cutoffDay]
        );

        Payroll::where(
            'period_month',
            $period
        )
            ->update([
                'cutoff_day' => $cutoffDay,
            ]);

        return redirect()
            ->route(
                'payrolls.local.create',
                [
                    'period' => $period,
                ]
            )
            ->with(
                'success',
                "Cut-off periode {$period} diset ke tanggal {$cutoffDay}."
            );
    }

    // ============================================================
    // IMPORT ABSENSI
    // ============================================================

    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:zip,xlsx,xls,csv',
                'max:20480',
            ],

            'period_month' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $period =
            $request->input('period_month');

        $file =
            $request->file('file');

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
            );

        $folderName = null;

        try {

            if ($extension === 'zip') {

                $zip = new ZipArchive();

                $status =
                    $zip->open(
                        $file->getRealPath()
                    );

                if ($status !== true) {
                    return redirect()
                        ->back()
                        ->with(
                            'error',
                            'Gagal membuka file ZIP.'
                        );
                }

                $maxUncompressedBytes =
                    200 * 1024 * 1024;

                $totalUncompressedBytes = 0;

                for (
                    $i = 0;
                    $i < $zip->numFiles;
                    $i++
                ) {

                    $stat =
                        $zip->statIndex($i);

                    $name =
                        (string) (
                            $stat['name'] ?? ''
                        );

                    $size =
                        (int) (
                            $stat['size'] ?? 0
                        );

                    if (
                        str_contains(
                            $name,
                            '../'
                        )
                        ||
                        str_contains(
                            $name,
                            '..\\'
                        )
                        ||
                        str_starts_with(
                            $name,
                            '/'
                        )
                        ||
                        preg_match(
                            '/^[A-Za-z]:[\\\\\/]/',
                            $name
                        )
                    ) {
                        $zip->close();

                        return redirect()
                            ->back()
                            ->with(
                                'error',
                                'ZIP ditolak karena path file tidak aman.'
                            );
                    }

                    $totalUncompressedBytes +=
                        $size;

                    if (
                        $totalUncompressedBytes
                        > $maxUncompressedBytes
                    ) {
                        $zip->close();

                        return redirect()
                            ->back()
                            ->with(
                                'error',
                                'Ukuran hasil ekstraksi ZIP terlalu besar.'
                            );
                    }
                }

                $folderName =
                    'temp_absensi_'
                    . now()->format('Ymd_His')
                    . '_'
                    . Str::random(12);

                $extractPath =
                    storage_path(
                        'app/' . $folderName
                    );

                if (!is_dir($extractPath)) {
                    mkdir(
                        $extractPath,
                        0750,
                        true
                    );
                }

                if (
                    !$zip->extractTo(
                        $extractPath
                    )
                ) {
                    $zip->close();

                    return redirect()
                        ->back()
                        ->with(
                            'error',
                            'Gagal mengekstrak file ZIP.'
                        );
                }

                $zip->close();

                $extractedFiles = [];

                $iterator =
                    new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator(
                            $extractPath,
                            \FilesystemIterator::SKIP_DOTS
                        )
                    );

                foreach (
                    $iterator as $entry
                ) {

                    if (!$entry->isFile()) {
                        continue;
                    }

                    $ext =
                        strtolower(
                            $entry->getExtension()
                        );

                    if (
                        in_array(
                            $ext,
                            [
                                'xls',
                                'xlsx',
                                'csv',
                            ],
                            true
                        )
                    ) {
                        $extractedFiles[] =
                            $entry->getPathname();
                    }
                }

                if (empty($extractedFiles)) {
                    return redirect()
                        ->back()
                        ->with(
                            'error',
                            'Tidak ditemukan file Excel/CSV di dalam archive ZIP.'
                        );
                }

                foreach (
                    $extractedFiles as $filePath
                ) {
                    Excel::import(
                        new AttendanceImport(
                            $period
                        ),
                        $filePath
                    );
                }

                AttendanceImport::saveSummaryToDatabase(
                    $period
                );

                return redirect()
                    ->back()
                    ->with(
                        'success',
                        'File ZIP Absensi ('
                        . count($extractedFiles)
                        . ' log harian) berhasil diproses!'
                    );
            }

            Excel::import(
                new AttendanceImport($period),
                $file
            );

            AttendanceImport::saveSummaryToDatabase(
                $period
            );

            return redirect()
                ->back()
                ->with(
                    'success',
                    'File absensi berhasil diimpor!'
                );

        } catch (Throwable $e) {

            report($e);

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Gagal memproses file absensi: '
                    . $e->getMessage()
                );

        } finally {

            if ($folderName) {
                Storage::deleteDirectory(
                    $folderName
                );
            }
        }
    }

    // ============================================================
    // STORE / HITUNG REKAP & PAYROLL
    // ============================================================

    public function store(Request $request)
    {
        $validated = $request->validate([

            'period_month' => [
                'required',
                'date_format:Y-m',
            ],

            'payrolls' => [
                'required',
                'array',
            ],

            'cutoff_day_submit' => [
                'nullable',
                'integer',
                'min:' . self::MIN_CUTOFF_DAY,
                'max:' . self::MAX_CUTOFF_DAY,
            ],

            'payrolls.*.daily_attendance' => [
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
                'string',
                'max:30',
            ],

            'payrolls.*.cash_advance' => [
                'nullable',
                'string',
                'max:30',
            ],

            'payrolls.*.other_deductions' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $period =
            $validated['period_month'];

        // ========================================================
        // LOCK
        // ========================================================

        $existingPeriodLocked =
            Payroll::where(
                'period_month',
                $period
            )
                ->where('is_locked', true)
                ->exists();

        $cutoffDay =
            $this->getPeriodCutoffDay(
                $period
            );

        if (
            !$existingPeriodLocked
            &&
            $this->canManageCutoff()
            &&
            !empty(
                $validated['cutoff_day_submit']
            )
        ) {
            $cutoffDay =
                (int) $validated[
                    'cutoff_day_submit'
                ];
        }

        // ========================================================
        // EMPLOYEE
        // ========================================================

        $employeeIds =
            array_map(
                'intval',
                array_keys(
                    $validated['payrolls']
                )
            );

        $employees =
            Employee::with(
                'activeContract'
            )
                ->whereIn(
                    'id_employee',
                    $employeeIds
                )
                ->where(
                    'is_active',
                    true
                )
                ->get()
                ->keyBy(
                    'id_employee'
                );

        // ========================================================
        // EXISTING PAYROLL
        // ========================================================

        $existingPayrolls =
            Payroll::where(
                'period_month',
                $period
            )
                ->whereIn(
                    'employee_id',
                    $employeeIds
                )
                ->get()
                ->keyBy(
                    'employee_id'
                );

        // ========================================================
        // PERIOD
        // ========================================================

        $periodDate =
            Carbon::createFromFormat(
                'Y-m-d',
                $period . '-01'
            );

        $previousPeriod =
            $periodDate
                ->copy()
                ->subMonth()
                ->format('Y-m');

        $nextPeriod =
            $periodDate
                ->copy()
                ->addMonth()
                ->format('Y-m');

        // ========================================================
        // SETTINGS
        // ========================================================

        $tkRate =
            (float) CompanySetting::get(
                'bpjs_tk_employee_rate',
                2.0
            ) / 100;

        $ksRate =
            (float) CompanySetting::get(
                'bpjs_ks_employee_rate',
                1.0
            ) / 100;

        $ksCap =
            (float) CompanySetting::get(
                'bpjs_ks_max_cap',
                12000000
            );

        $standardWorkDays =
            $this->standardWorkDays();

        $overtimeRate =
            $this->overtimeRate();

        $canEditFinancial =
            $this->canEditFinancial();

        // ========================================================
        // HOLIDAY MAP
        // ========================================================

        $currentHolidayMap =
            $this->getHolidayMap(
                $period
            );

        // ========================================================
        // PREVIOUS PAYROLL
        //
        // Hanya salary basis periode sebelumnya.
        //
        // BUKAN sumber gantungan.
        // ========================================================

        $previousPayrolls =
            Payroll::where(
                'period_month',
                $previousPeriod
            )
                ->whereIn(
                    'employee_id',
                    $employeeIds
                )
                ->get([
                    'employee_id',
                    'basic_salary',
                    'allowance',
                ])
                ->keyBy(
                    'employee_id'
                );

        // ========================================================
        // TRANSACTION
        // ========================================================

        DB::transaction(function () use (
            $validated,
            $employees,
            $existingPayrolls,
            $previousPayrolls,
            $period,
            $previousPeriod,
            $cutoffDay,
            $existingPeriodLocked,
            $tkRate,
            $ksRate,
            $ksCap,
            $standardWorkDays,
            $overtimeRate,
            $canEditFinancial,
            $currentHolidayMap
        ) {

            foreach (
                $validated['payrolls']
                as $empId => $data
            ) {

                /*
                 * Pastikan key employee selalu integer.
                 */
                $empId = (int) $empId;

                $employee =
                    $employees->get(
                        $empId
                    );

                if (
                    !$employee
                    ||
                    !$employee->activeContract
                ) {
                    continue;
                }

                $contract =
                    $employee->activeContract;

                $existing =
                    $existingPayrolls->get(
                        $empId
                    );

                // ====================================================
                // LOCKED PERIOD WITHOUT PAYROLL
                // ====================================================

                if (
                    $existingPeriodLocked
                    &&
                    !$existing
                ) {
                    continue;
                }

                // ====================================================
                // SUBMITTED ATTENDANCE
                // ====================================================
                //
                // Bagian ini hanya untuk menerima perubahan manual
                // dari form.
                //
                // Setelah disimpan, controller WAJIB reload ulang
                // AttendanceRecord dari database.
                //
                // ====================================================

                $submittedDaily =
                    $data['daily_attendance']
                    ?? [];

                $submittedDaily =
                    is_array($submittedDaily)
                        ? $submittedDaily
                        : [];

                // ====================================================
                // LOAD ATTENDANCE RECORDS AWAL
                // ====================================================

                $attendanceRecords =
                    $this->loadAttendanceRecords(
                        $empId,
                        $period
                    );

                // ====================================================
                // SIMPAN MANUAL ATTENDANCE
                // ====================================================

                foreach (
                    $submittedDaily
                    as $dateKey => $status
                ) {

                    $date =
                        $this->normalizeAttendanceDate(
                            $period,
                            $dateKey
                        );

                    if (!$date) {
                        continue;
                    }

                    $day =
                        (int) $date->format('d');

                    // =================================================
                    // LOCK RULE
                    //
                    // <= cutoff tidak boleh diubah.
                    // > cutoff masih boleh diubah.
                    // =================================================

                    if (
                        $existingPeriodLocked
                        &&
                        $day <= $cutoffDay
                    ) {
                        continue;
                    }

                    $normalizedStatus =
                        $this->normalizeAttendanceStatus(
                            $status
                        );

                    $dateString =
                        $date->format('Y-m-d');

                    // =================================================
                    // HB
                    //
                    // HB tidak disimpan ke attendance_records.
                    // =================================================

                    if (
                        $normalizedStatus === 'HB'
                    ) {

                        AttendanceRecord::where(
                            'employee_id',
                            $empId
                        )
                            ->where(
                                'attendance_date',
                                $dateString
                            )
                            ->delete();

                        continue;
                    }

                    // =================================================
                    // KOSONG / -
                    //
                    // Hapus explicit attendance.
                    // =================================================

                    if (
                        $normalizedStatus === ''
                    ) {

                        AttendanceRecord::where(
                            'employee_id',
                            $empId
                        )
                            ->where(
                                'attendance_date',
                                $dateString
                            )
                            ->delete();

                        continue;
                    }

                    // =================================================
                    // SIMPAN EXPLICIT ATTENDANCE
                    // =================================================

                    AttendanceRecord::updateOrCreate(
                        [
                            'employee_id' =>
                                $empId,

                            'attendance_date' =>
                                $dateString,
                        ],
                        [
                            'status' =>
                                $normalizedStatus,

                            'source' =>
                                'manual',
                        ]
                    );
                }

                // ====================================================
                // PENTING:
                //
                // RELOAD DARI DATABASE
                //
                // Jangan gunakan collection lama.
                //
                // Ini membuat AttendanceRecord menjadi source of truth.
                // ====================================================

                $attendanceRecords =
                    $this->loadAttendanceRecords(
                        $empId,
                        $period
                    );

                // ====================================================
                // EFFECTIVE DAILY ATTENDANCE
                // ====================================================
                //
                // AttendanceRecord
                //      ↓
                // Holiday
                //      ↓
                // Sunday
                //      ↓
                // -
                //
                // ====================================================

                $dailyAttendance =
                    $this->buildEffectiveDailyAttendance(
                        $attendanceRecords,
                        $period,
                        $currentHolidayMap
                    );

                // ====================================================
                // SUMMARY
                // ====================================================
                //
                // Summary hanya untuk:
                // - work days
                // - unpaid <= cutoff
                // - normative
                // - holiday
                //
                // Gantungan current dihitung LANGSUNG dari
                // AttendanceRecord agar tidak bergantung pada snapshot.
                // ====================================================

                $summary =
                    $this->summarizeAttendance(
                        $dailyAttendance,
                        $period,
                        $cutoffDay
                    );

                // ====================================================
                // SALARY
                // ====================================================

                $basicSalary =
                    (float) $contract->basic_salary;

                $allowance =
                    (float) $contract->allowance;

                // ====================================================
                // CURRENT UNPAID
                // ====================================================

                $unpaidLeave =
                    $summary[
                        'current_unpaid_days'
                    ];

                // ====================================================
                // CURRENT GANTUNGAN
                //
                // SOURCE:
                // attendance_records
                //
                // BUKAN:
                // daily_attendance snapshot
                // ====================================================

                $gantunganDays =
                    $this->calculateCurrentGantungan(
                        $attendanceRecords,
                        $cutoffDay
                    );

                // ====================================================
                // POTONGAN ABSEN CURRENT
                //
                // Hanya A/I/H0.5 <= cutoff.
                // ====================================================

                $basicSalaryDeduction =
                    $standardWorkDays > 0
                        ? (
                            (
                                $basicSalary
                                /
                                $standardWorkDays
                            )
                            *
                            $unpaidLeave
                        )
                        : 0;

                $allowanceDeduction =
                    $standardWorkDays > 0
                        ? (
                            (
                                $allowance
                                /
                                $standardWorkDays
                            )
                            *
                            $unpaidLeave
                        )
                        : 0;

                $netBasicSalary =
                    max(
                        0,
                        $basicSalary
                        -
                        $basicSalaryDeduction
                    );

                $netAllowance =
                    max(
                        0,
                        $allowance
                        -
                        $allowanceDeduction
                    );

                // ====================================================
                // NOMINAL CURRENT GANTUNGAN
                //
                // Disimpan sekarang.
                //
                // TIDAK dipotong sekarang.
                // ====================================================

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

                // ====================================================
                // PREVIOUS GANTUNGAN
                //
                // LANGSUNG dari attendance_records bulan sebelumnya.
                //
                // Jadi:
                //
                // Juli gantungan
                //      ↓
                // Agustus dipotong
                //
                // September tidak mengambil Juli lagi.
                // ====================================================

                $previousPayroll =
                    $previousPayrolls->get(
                        $empId
                    );

                $previousGantungan =
                    $this->calculatePreviousGantungan(
                        $employee,
                        $previousPeriod,
                        $standardWorkDays,
                        $previousPayroll
                    );

                $previousGantunganDeduction =
                    $previousGantungan[
                        'deduction'
                    ];

                // ====================================================
                // OVERTIME
                // ====================================================

                $overtimeHours =
                    $existingPeriodLocked
                        ? (float) (
                            $existing?->overtime_hours
                            ?? 0
                        )
                        : (float) (
                            $data['overtime_hours']
                            ?? $existing?->overtime_hours
                            ?? 0
                        );

                $overtimePay =
                    $overtimeHours
                    *
                    $overtimeRate;

                // ====================================================
                // MATERNITY
                // ====================================================

                $maternityLeavePay =
                    (float) (
                        $existing
                            ?->maternity_leave_pay
                        ?? 0
                    );

                // ====================================================
                // FINANCIAL VARIABLES
                // ====================================================

                if ($canEditFinancial) {

                    $incentive =
                        $this->cleanMoney(
                            $data['incentive']
                            ?? $existing?->incentive
                            ?? 0
                        );

                    $cashAdvance =
                        $this->cleanMoney(
                            $data['cash_advance']
                            ?? $existing?->cash_advance
                            ?? 0
                        );

                    $otherDeductions =
                        $this->cleanMoney(
                            $data['other_deductions']
                            ?? $existing?->other_deductions
                            ?? 0
                        );

                } else {

                    $incentive =
                        (float) (
                            $existing?->incentive
                            ?? 0
                        );

                    $cashAdvance =
                        (float) (
                            $existing?->cash_advance
                            ?? 0
                        );

                    $otherDeductions =
                        (float) (
                            $existing?->other_deductions
                            ?? 0
                        );
                }

                // ====================================================
                // GROSS SALARY
                // ====================================================

                $grossSalary =
                    $netBasicSalary
                    +
                    $netAllowance
                    +
                    $overtimePay
                    +
                    $maternityLeavePay
                    +
                    $incentive;

                // ====================================================
                // BPJS
                // ====================================================

                $isBpjsOverride =
                    (bool) (
                        $contract
                            ->use_manual_bpjs
                        ?? false
                    );

                if ($isBpjsOverride) {

                    $bpjsTkDeduction =
                        (float) (
                            $contract
                                ->manual_bpjs_tk_employee
                            ?? 0
                        );

                    $bpjsKsDeduction =
                        (float) (
                            $contract
                                ->manual_bpjs_ks_employee
                            ?? 0
                        );

                } else {

                    $bpjsTkDeduction =
                        (
                            $contract
                                ->is_bpjstk_active
                            ?? false
                        )
                            ? (
                                $basicSalary
                                *
                                $tkRate
                            )
                            : 0;

                    $basisBpjsKs =
                        min(
                            $basicSalary,
                            $ksCap
                        );

                    $bpjsKsDeduction =
                        (
                            $contract
                                ->is_bpjs_health_active
                            ?? false
                        )
                            ? (
                                $basisBpjsKs
                                *
                                $ksRate
                            )
                            : 0;
                }

                // ====================================================
                // PPH 21
                // ====================================================

                $pph21Rate =
                    $this->calculateTerRate(
                        $contract->ptkp_status
                            ?? 'TK/0',
                        $grossSalary
                    );

                $pph21Deduction =
                    $grossSalary
                    *
                    $pph21Rate;

                // ====================================================
                // TOTAL DEDUCTIONS
                // ====================================================
                //
                // Current gantungan TIDAK masuk.
                //
                // Previous gantungan MASUK.
                // ====================================================

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

                // ====================================================
                // NET SALARY
                // ====================================================

                $netSalary =
                    max(
                        0,
                        $grossSalary
                        -
                        $totalDeductions
                    );

                // ====================================================
                // PAYROLL PAYLOAD
                // ====================================================

                $payload = [

                    'cutoff_day' =>
                        $cutoffDay,

                    /*
                     * Snapshot saja.
                     *
                     * Perhitungan berikutnya TIDAK menggunakan ini.
                     */
                    'daily_attendance' =>
                        $dailyAttendance,

                    'work_days' =>
                        $summary[
                            'total_days'
                        ],

                    'unpaid_leave' =>
                        $unpaidLeave,

                    'gantungan_days' =>
                        $gantunganDays,

                    'overtime_hours' =>
                        $overtimeHours,

                    'basic_salary' =>
                        $basicSalary,

                    'allowance' =>
                        $allowance,

                    'overtime_pay' =>
                        $overtimePay,

                    'maternity_leave_pay' =>
                        $maternityLeavePay,

                    'incentive' =>
                        $incentive,

                    'cash_advance' =>
                        $cashAdvance,

                    'other_deductions' =>
                        $otherDeductions,

                    /*
                     * Gantungan periode sekarang.
                     * Akan dikonsumsi periode berikutnya.
                     */
                    'gantungan_deduction' =>
                        round(
                            $gantunganDeduction,
                            2
                        ),

                    /*
                     * Gantungan periode sebelumnya.
                     * Dipotong sekarang.
                     */
                    'previous_gantungan_deduction' =>
                        round(
                            $previousGantunganDeduction,
                            2
                        ),

                    'bpjs_tk_deduction' =>
                        round(
                            $bpjsTkDeduction,
                            2
                        ),

                    'bpjs_ks_deduction' =>
                        round(
                            $bpjsKsDeduction,
                            2
                        ),

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
                        $existingPeriodLocked
                            ? (
                                $existing?->status
                                ?? 'Approved'
                            )
                            : (
                                $existing?->status === 'Paid'
                                    ? 'Paid'
                                    : 'Draft'
                            ),
                ];

                // ====================================================
                // SIMPAN PAYROLL
                // ====================================================

                Payroll::updateOrCreate(
                    [
                        'employee_id' =>
                            $empId,

                        'period_month' =>
                            $period,
                    ],
                    $payload
                );
            }
        });

        return redirect()
            ->route(
                'payrolls.local.create',
                [
                    'period' => $period,
                ]
            )
            ->with(
                'success',
                "Absensi & rekap payroll periode {$period} berhasil diproses. "
                . "Gantungan periode {$period} akan dipotong pada {$nextPeriod}; "
                . "gantungan dari {$previousPeriod} dipotong pada {$period}."
            );
    }

    // ============================================================
    // LOAD ATTENDANCE RECORDS
    // ============================================================
    //
    // INI SEKARANG MENJADI SOURCE OF TRUTH.
    //
    // Jangan membaca payrolls.daily_attendance untuk perhitungan.
    //
    // ============================================================

    private function loadAttendanceRecords(
        int $employeeId,
        string $period
    ) {
        $startDate =
            Carbon::createFromFormat(
                'Y-m-d',
                $period . '-01'
            )->startOfMonth();

        $endDate =
            $startDate->copy()->endOfMonth();

        return AttendanceRecord::query()
            ->where(
                'employee_id',
                $employeeId
            )
            ->whereBetween(
                'attendance_date',
                [
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d'),
                ]
            )
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(function ($record) {

                return Carbon::parse(
                    $record->attendance_date
                )->format('Y-m-d');
            });
    }

    // ============================================================
    // HITUNG GANTUNGAN CURRENT
    // ============================================================
    //
    // SOURCE:
    // attendance_records
    //
    // A     > cutoff = 1
    // I     > cutoff = 1
    // H0.5  > cutoff = 0.5
    //
    // HB tidak pernah menjadi gantungan.
    //
    // ============================================================

    private function calculateCurrentGantungan(
        $attendanceRecords,
        int $cutoffDay
    ): float {

        $gantunganDays = 0.0;

        foreach (
            $attendanceRecords as $record
        ) {

            $status =
                $this->normalizeAttendanceStatus(
                    $record->status
                );

            if (
                !in_array(
                    $status,
                    [
                        'A',
                        'I',
                        'H0.5',
                    ],
                    true
                )
            ) {
                continue;
            }

            $date =
                $record->attendance_date instanceof Carbon
                    ? $record->attendance_date
                    : Carbon::parse(
                        $record->attendance_date
                    );

            /*
             * <= cutoff = periode berjalan.
             * > cutoff = gantungan.
             */
            if (
                (int) $date->format('d')
                <=
                $cutoffDay
            ) {
                continue;
            }

            if ($status === 'H0.5') {
                $gantunganDays += 0.5;
            } else {
                $gantunganDays += 1.0;
            }
        }

        return round(
            $gantunganDays,
            1
        );
    }

    // ============================================================
    // HOLIDAY MAP
    // ============================================================

    private function getHolidayMap(
        string $period
    ): array {

        $start =
            Carbon::createFromFormat(
                'Y-m-d',
                $period . '-01'
            )
                ->startOfMonth();

        $end =
            $start->copy()
                ->endOfMonth();

        $holidays =
            Holiday::query()
                ->where(
                    'is_active',
                    true
                )
                ->whereBetween(
                    'holiday_date',
                    [
                        $start->format('Y-m-d'),
                        $end->format('Y-m-d'),
                    ]
                )
                ->get();

        $map = [];

        foreach ($holidays as $holiday) {

            $date =
                Carbon::parse(
                    $holiday->holiday_date
                )
                    ->format('Y-m-d');

            $map[$date] = [
                'name' =>
                    $holiday->name,

                'type' =>
                    $holiday->type,
            ];
        }

        return $map;
    }

    // ============================================================
    // BUILD EFFECTIVE ATTENDANCE
    // ============================================================
    //
    // PRIORITY:
    //
    // 1. AttendanceRecord
    // 2. Holiday => HB
    // 3. Sunday => -
    // 4. Normal day => -
    //
    // AttendanceRecord selalu mengalahkan Holiday.
    //
    // Jadi kalau hari libur tetapi ada explicit A/I/H/C,
    // explicit attendance yang digunakan.
    //
    // ============================================================

    private function buildEffectiveDailyAttendance(
        $attendanceRecords,
        string $period,
        array $holidayMap
    ): array {

        $dailyAttendance = [];

        $start =
            Carbon::createFromFormat(
                'Y-m-d',
                $period . '-01'
            )
                ->startOfMonth();

        $end =
            $start->copy()
                ->endOfMonth();

        for (
            $date = $start->copy();
            $date->lte($end);
            $date->addDay()
        ) {

            $dateString =
                $date->format('Y-m-d');

            // ====================================================
            // EXPLICIT ATTENDANCE
            // ====================================================

            if (
                $attendanceRecords->has(
                    $dateString
                )
            ) {

                $record =
                    $attendanceRecords->get(
                        $dateString
                    );

                $status =
                    $this->normalizeAttendanceStatus(
                        $record->status
                    );

                /*
                 * Kalau record valid, record menang.
                 */
                if ($status !== '') {

                    $dailyAttendance[
                        $dateString
                    ] = $status;

                    continue;
                }

                /*
                 * Kalau record tidak valid,
                 * jangan membuat hari hilang.
                 *
                 * Lanjut ke holiday / Sunday / '-'.
                 */
            }

            // ====================================================
            // HOLIDAY
            // ====================================================

            if (
                array_key_exists(
                    $dateString,
                    $holidayMap
                )
            ) {

                $dailyAttendance[
                    $dateString
                ] = 'HB';

                continue;
            }

            // ====================================================
            // SUNDAY
            // ====================================================

            if (
                $date->isSunday()
            ) {

                $dailyAttendance[
                    $dateString
                ] = '-';

                continue;
            }

            // ====================================================
            // NORMAL DAY WITHOUT RECORD
            // ====================================================

            $dailyAttendance[
                $dateString
            ] = '-';
        }

        return $dailyAttendance;
    }

    // ============================================================
    // HITUNG GANTUNGAN PERIODE SEBELUMNYA
    // ============================================================
    //
    // SUMBER UTAMA:
    // attendance_records
    //
    // Hanya periode SEBELUMNYA yang dikonsumsi.
    //
    // Contoh:
    //
    // Juli:
    // 4 hari gantungan
    //
    // Agustus:
    // previous_gantungan = 4 hari Juli
    //
    // September:
    // TIDAK mengambil Juli lagi.
    //
    // September hanya mengambil gantungan Agustus.
    //
    // ============================================================

    private function calculatePreviousGantungan(
        Employee $employee,
        string $previousPeriod,
        float $standardWorkDays,
        ?Payroll $previousPayroll
    ): array {

        $previousCutoffDay =
            $this->getPeriodCutoffDay(
                $previousPeriod
            );

        // ========================================================
        // LOAD ATTENDANCE RECORDS LANGSUNG DARI DB
        // ========================================================

        $records =
            $this->loadAttendanceRecords(
                (int) $employee->id_employee,
                $previousPeriod
            );

        // ========================================================
        // HITUNG GANTUNGAN
        // ========================================================

        $gantunganDays =
            $this->calculateCurrentGantungan(
                $records,
                $previousCutoffDay
            );

        // ========================================================
        // SALARY BASIS PERIODE SEBELUMNYA
        // ========================================================
        //
        // Prioritas:
        //
        // 1. Payroll periode sebelumnya
        // 2. Active contract
        //
        // Supaya kenaikan gaji bulan sekarang tidak mengubah
        // nominal gantungan bulan sebelumnya.
        //
        // ========================================================

        $basicSalary =
            (float) (
                $previousPayroll?->basic_salary
                ??
                $employee
                    ->activeContract
                    ?->basic_salary
                ??
                0
            );

        $allowance =
            (float) (
                $previousPayroll?->allowance
                ??
                $employee
                    ->activeContract
                    ?->allowance
                ??
                0
            );

        // ========================================================
        // DAILY RATE
        // ========================================================

        $dailyRate =
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
                : 0;

        // ========================================================
        // DEDUCTION
        // ========================================================

        $deduction =
            $dailyRate
            *
            $gantunganDays;

        return [

            'days' =>
                round(
                    $gantunganDays,
                    1
                ),

            'deduction' =>
                round(
                    $deduction,
                    2
                ),

            'cutoff_day' =>
                $previousCutoffDay,

            'basic_salary' =>
                $basicSalary,

            'allowance' =>
                $allowance,
        ];
    }

    // ============================================================
    // LOCK / UNLOCK
    // ============================================================

    public function lockCalculation(
        Request $request
    ) {

        abort_unless(
            $this->canManageCutoff(),
            403
        );

        $validated = $request->validate([

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

        $count =
            Payroll::where(
                'period_month',
                $period
            )
                ->count();

        if ($count === 0) {

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Tidak ada payroll untuk periode ini. Simpan absensi terlebih dahulu sebelum Close.'
                );
        }

        Payroll::where(
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

        return redirect()
            ->back()
            ->with(
                'success',
                "Absensi periode {$period} berhasil di-Close. "
                . "Tanggal 01-{$cutoffDay} terkunci; "
                . "tanggal setelah cutoff tetap dapat diisi "
                . "dan menjadi gantungan ke bulan berikutnya."
            );
    }

    public function requestUnlock(
        Request $request
    ) {

        $request->validate([
            'period' => [
                'required',
                'date_format:Y-m',
            ],

            'reason' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $period =
            $request->input('period');

        Payroll::where(
            'period_month',
            $period
        )
            ->update([

                'unlock_requested' =>
                    true,

                'unlock_reason' =>
                    $request->input('reason'),

                'requested_by' =>
                    Auth::id(),
            ]);

        return redirect()
            ->back()
            ->with(
                'success',
                'Pengajuan buka kunci berhasil dikirim ke Manager Keuangan.'
            );
    }

    public function unlockCalculation(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $request->validate([
            'period' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $period =
            $request->input('period');

        Payroll::where(
            'period_month',
            $period
        )
            ->update([

                'is_locked' =>
                    false,

                'locked_at' =>
                    null,

                'locked_by' =>
                    null,

                'unlock_requested' =>
                    false,

                'unlock_reason' =>
                    null,

                'requested_by' =>
                    null,

                'status' =>
                    'Draft',
            ]);

        return redirect()
            ->back()
            ->with(
                'success',
                "Kuncian Payroll periode {$period} berhasil dibuka kembali."
            );
    }

    public function rejectUnlock(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $request->validate([
            'period' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $period =
            $request->input('period');

        Payroll::where(
            'period_month',
            $period
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
            ->back()
            ->with(
                'info',
                "Permohonan buka kunci periode {$period} ditolak."
            );
    }

    // ============================================================
    // EXPORT BCA
    // ============================================================

    public function exportBca(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $request->validate([
            'period' => [
                'nullable',
                'date_format:Y-m',
            ],
        ]);

        $period =
            $request->input(
                'period',
                date('Y-m')
            );

        $payrolls =
            Payroll::with('employee')
                ->where(
                    'period_month',
                    $period
                )
                ->whereIn(
                    'status',
                    [
                        'Approved',
                        'Paid',
                    ]
                )
                ->get();

        $missingAccounts =
            $payrolls->filter(
                fn ($p) =>
                    empty(
                        $p->employee
                            ?->bank_account_number
                    )
            );

        if (
            $missingAccounts->isNotEmpty()
        ) {

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Export BCA dibatalkan. Masih ada karyawan tanpa nomor rekening.'
                );
        }

        $fileName =
            "Payroll_BCA_{$period}.csv";

        $headers = [

            'Content-Type' =>
                'text/csv',

            'Content-Disposition' =>
                "attachment; filename={$fileName}",

            'Pragma' =>
                'no-cache',

            'Cache-Control' =>
                'must-revalidate, post-check=0, pre-check=0',

            'Expires' =>
                '0',
        ];

        $callback =
            function () use ($payrolls) {

                $file =
                    fopen(
                        'php://output',
                        'w'
                    );

                fputcsv(
                    $file,
                    [
                        'No Rekening',
                        'Nominal Transfer',
                        'Nama Pemilik Rekening',
                        'Keterangan',
                    ]
                );

                foreach (
                    $payrolls as $payroll
                ) {

                    fputcsv(
                        $file,
                        [
                            $payroll
                                ->employee
                                ->bank_account_number,

                            $payroll
                                ->net_salary
                                ?? 0,

                            $payroll
                                ->employee
                                ->full_name
                                ?? 'Karyawan',

                            'Gaji '
                            . $payroll
                                ->period_month,
                        ]
                    );
                }

                fclose($file);
            };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    // ============================================================
    // PDF / EMAIL
    // ============================================================

    public function printPdf(
        string $uuid,
        ?string $period = null
    ) {

        $query =
            Payroll::with([
                'employee.activeContract',
            ])
                ->whereHas(
                    'employee',
                    function ($query) use ($uuid) {

                        $query->where(
                            'uuid',
                            $uuid
                        );
                    }
                );

        if ($period) {

            $query->where(
                'period_month',
                $period
            );

        } else {

            $query->latest(
                'period_month'
            );
        }

        $payroll =
            $query->firstOrFail();

        $pdf =
            Pdf::loadView(
                'payrolls.local.pdf_slip',
                compact('payroll')
            )
                ->setPaper(
                    'a4',
                    'portrait'
                );

        return $pdf->stream(
            'Slip_Gaji_'
            . $payroll->employee->full_name
            . '_'
            . $payroll->period_month
            . '.pdf'
        );
    }

    public function sendEmail(
        string $uuid,
        ?string $period = null
    ) {

        $query =
            Payroll::with([
                'employee.activeContract',
            ])
                ->whereHas(
                    'employee',
                    function ($query) use ($uuid) {

                        $query->where(
                            'uuid',
                            $uuid
                        );
                    }
                );

        if ($period) {

            $query->where(
                'period_month',
                $period
            );

        } else {

            $query->latest(
                'period_month'
            );
        }

        $payroll =
            $query->firstOrFail();

        $emailDestination =
            $payroll
                ->employee
                ->email;

        if (!$emailDestination) {

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Email karyawan '
                    . $payroll->employee->full_name
                    . ' belum diisi di Master Karyawan.'
                );
        }

        $pdfBinary =
            Pdf::loadView(
                'payrolls.local.pdf_slip',
                compact('payroll')
            )
                ->setPaper(
                    'a4',
                    'portrait'
                )
                ->output();

        Mail::to(
            $emailDestination
        )->send(
            new SalarySlipMail(
                $payroll,
                $pdfBinary
            )
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Slip Gaji berhasil dikirim ke email: '
                . $emailDestination
            );
    }

    // ============================================================
    // MASTER BPJS / TER
    // ============================================================

    public function taxBpjsMaster()
    {
        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $bpjsSettings = [

            'bpjs_tk_rate' =>
                CompanySetting::get(
                    'bpjs_tk_employee_rate',
                    2.0
                ),

            'bpjs_ks_rate' =>
                CompanySetting::get(
                    'bpjs_ks_employee_rate',
                    1.0
                ),

            'bpjs_ks_cap' =>
                CompanySetting::get(
                    'bpjs_ks_max_cap',
                    12000000
                ),

            'standard_work_days' =>
                CompanySetting::get(
                    'standard_work_days',
                    self::DEFAULT_STANDARD_WORK_DAYS
                ),

            'overtime_hour_rate' =>
                CompanySetting::get(
                    'overtime_hour_rate',
                    self::DEFAULT_OVERTIME_RATE
                ),

            'attendance_cutoff_day' =>
                CompanySetting::get(
                    'attendance_cutoff_day',
                    self::DEFAULT_CUTOFF_DAY
                ),
        ];

        $terCategories =
            $this->terCategories();

        return view(
            'payrolls.local.tax_bpjs_master',
            compact(
                'terCategories',
                'bpjsSettings'
            )
        );
    }

    public function updateBpjsSetting(
        Request $request
    ) {

        abort_unless(
            $this->isFinanceRole(),
            403
        );

        $request->validate([

            'bpjs_tk_employee_rate' =>
                'required|numeric|min:0|max:100',

            'bpjs_ks_employee_rate' =>
                'required|numeric|min:0|max:100',

            'bpjs_ks_max_cap' =>
                'required|numeric|min:0',

            'standard_work_days' =>
                'required|numeric|min:1|max:31',

            'overtime_hour_rate' =>
                'required|numeric|min:0',
        ]);

        CompanySetting::updateOrCreate(
            [
                'key' =>
                    'bpjs_tk_employee_rate',
            ],
            [
                'value' =>
                    $request
                        ->bpjs_tk_employee_rate,
            ]
        );

        CompanySetting::updateOrCreate(
            [
                'key' =>
                    'bpjs_ks_employee_rate',
            ],
            [
                'value' =>
                    $request
                        ->bpjs_ks_employee_rate,
            ]
        );

        CompanySetting::updateOrCreate(
            [
                'key' =>
                    'bpjs_ks_max_cap',
            ],
            [
                'value' =>
                    $request
                        ->bpjs_ks_max_cap,
            ]
        );

        CompanySetting::updateOrCreate(
            [
                'key' =>
                    'standard_work_days',
            ],
            [
                'value' =>
                    $request
                        ->standard_work_days,
            ]
        );

        CompanySetting::updateOrCreate(
            [
                'key' =>
                    'overtime_hour_rate',
            ],
            [
                'value' =>
                    $request
                        ->overtime_hour_rate,
            ]
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Parameter BPJS dan payroll berhasil diperbarui.'
            );
    }

    // ============================================================
    // ATTENDANCE HELPERS
    // ============================================================

    private function normalizeAttendanceDate(
        string $period,
        $dateKey
    ): ?Carbon {

        try {

            $date =
                Carbon::parse(
                    (string) $dateKey
                );

        } catch (Throwable) {

            return null;
        }

        if (
            $date->format('Y-m')
            !==
            $period
        ) {
            return null;
        }

        return $date->startOfDay();
    }

    private function normalizeAttendanceStatus(
        $status
    ): string {

        $status =
            strtoupper(
                trim(
                    (string) $status
                )
            );

        return in_array(
            $status,
            [
                'H',
                'H0.5',
                'A',
                'I',
                'SKD',
                'C',
                'CM',
                'HB',
            ],
            true
        )
            ? $status
            : '';
    }

    // ============================================================
    // SUMMARY ATTENDANCE
    // ============================================================

    private function summarizeAttendance(
        array $dailyAttendance,
        string $period,
        int $cutoffDay
    ): array {

        $presentDays = 0.0;

        $unpaidAbsenceDays = 0.0;

        $paidAbsenceDays = 0.0;

        $normativeDays = 0.0;

        $currentUnpaidDays = 0.0;

        $holidayDays = 0.0;

        $start =
            Carbon::createFromFormat(
                'Y-m-d',
                $period . '-01'
            )
                ->startOfMonth();

        $end =
            $start->copy()
                ->endOfMonth();

        foreach (
            $dailyAttendance as
            $dateKey => $status
        ) {

            try {

                $date =
                    Carbon::parse(
                        $dateKey
                    );

            } catch (Throwable) {

                continue;
            }

            if (
                $date->lt($start)
                ||
                $date->gt($end)
            ) {
                continue;
            }

            $status =
                $this->normalizeAttendanceStatus(
                    $status
                );

            if ($status === '') {
                continue;
            }

            switch ($status) {

                case 'H':

                    $presentDays += 1;

                    break;

                case 'H0.5':

                    $presentDays += 0.5;

                    $unpaidAbsenceDays += 0.5;

                    if (
                        (int) $date->format('d')
                        <=
                        $cutoffDay
                    ) {
                        $currentUnpaidDays += 0.5;
                    }

                    break;

                case 'A':
                case 'I':

                    $unpaidAbsenceDays += 1;

                    if (
                        (int) $date->format('d')
                        <=
                        $cutoffDay
                    ) {
                        $currentUnpaidDays += 1;
                    }

                    break;

                case 'SKD':
                case 'C':
                case 'CM':

                    $paidAbsenceDays += 1;

                    if (
                        $status === 'CM'
                    ) {
                        $normativeDays += 1;
                    }

                    break;

                case 'HB':

                    $paidAbsenceDays += 1;

                    $holidayDays += 1;

                    break;
            }
        }

        return [

            'present_days' =>
                round(
                    $presentDays,
                    1
                ),

            'unpaid_absence_days' =>
                round(
                    $unpaidAbsenceDays,
                    1
                ),

            'paid_absence_days' =>
                round(
                    $paidAbsenceDays,
                    1
                ),

            'total_days' =>
                round(
                    $presentDays
                    +
                    $unpaidAbsenceDays
                    +
                    $paidAbsenceDays,
                    1
                ),

            'normative_days' =>
                round(
                    $normativeDays,
                    1
                ),

            'holiday_days' =>
                round(
                    $holidayDays,
                    1
                ),

            'current_unpaid_days' =>
                round(
                    $currentUnpaidDays,
                    1
                ),

            /*
             * Sengaja tidak menggunakan gantungan_days
             * dari summary sebagai sumber payroll.
             *
             * Current gantungan dihitung langsung dari
             * AttendanceRecord melalui calculateCurrentGantungan().
             */
            'gantungan_days' => 0.0,
        ];
    }

    // ============================================================
    // MONEY
    // ============================================================

    private function cleanMoney(
        $value
    ): float {

        if (
            $value === null
            ||
            $value === ''
        ) {
            return 0.0;
        }

        $value =
            str_replace(
                [
                    'Rp',
                    'rp',
                    ' ',
                ],
                '',
                (string) $value
            );

        $value =
            preg_replace(
                '/[^0-9,.-]/',
                '',
                $value
            )
            ?? '';

        if (
            str_contains(
                $value,
                ','
            )
            &&
            str_contains(
                $value,
                '.'
            )
        ) {

            $value =
                str_replace(
                    '.',
                    '',
                    $value
                );

            $value =
                str_replace(
                    ',',
                    '.',
                    $value
                );

        } elseif (
            str_contains(
                $value,
                '.'
            )
        ) {

            $value =
                str_replace(
                    '.',
                    '',
                    $value
                );

        } elseif (
            str_contains(
                $value,
                ','
            )
        ) {

            $value =
                str_replace(
                    ',',
                    '.',
                    $value
                );
        }

        return is_numeric($value)
            ? (float) $value
            : 0.0;
    }

    // ============================================================
    // TER
    // ============================================================

    private function terCategories(): array
    {
        return [

            'TER_A' => [

                'ptkp' =>
                    'TK/0, TK/1, K/0',

                'description' =>
                    'Tidak Kawin Tanggungan 0-1, atau Kawin Tanggungan 0',

                'brackets' => [

                    [
                        'max' =>
                            5400000,
                        'rate' =>
                            0.00,
                    ],

                    [
                        'max' =>
                            5650000,
                        'rate' =>
                            0.25,
                    ],

                    [
                        'max' =>
                            5950000,
                        'rate' =>
                            0.50,
                    ],

                    [
                        'max' =>
                            6300000,
                        'rate' =>
                            0.75,
                    ],

                    [
                        'max' =>
                            6750000,
                        'rate' =>
                            1.25,
                    ],

                    [
                        'max' =>
                            7500000,
                        'rate' =>
                            1.75,
                    ],

                    [
                        'max' =>
                            'Seterusnya',
                        'rate' =>
                            2.50,
                    ],
                ],
            ],

            'TER_B' => [

                'ptkp' =>
                    'TK/2, TK/3, K/1, K/2',

                'description' =>
                    'Tidak Kawin Tanggungan 2-3, atau Kawin Tanggungan 1-2',

                'brackets' => [

                    [
                        'max' =>
                            6200000,
                        'rate' =>
                            0.00,
                    ],

                    [
                        'max' =>
                            6500000,
                        'rate' =>
                            0.25,
                    ],

                    [
                        'max' =>
                            7000000,
                        'rate' =>
                            0.50,
                    ],

                    [
                        'max' =>
                            'Seterusnya',
                        'rate' =>
                            1.50,
                    ],
                ],
            ],

            'TER_C' => [

                'ptkp' =>
                    'K/3',

                'description' =>
                    'Kawin Tanggungan 3',

                'brackets' => [

                    [
                        'max' =>
                            6600000,
                        'rate' =>
                            0.00,
                    ],

                    [
                        'max' =>
                            'Seterusnya',
                        'rate' =>
                            1.25,
                    ],
                ],
            ],
        ];
    }

    private function calculateTerRate(
        string $ptkp,
        float $gross
    ): float {

        $category =
            match ($ptkp) {

                'TK/0',
                'TK/1',
                'K/0'
                    => 'TER_A',

                'TK/2',
                'TK/3',
                'K/1',
                'K/2'
                    => 'TER_B',

                'K/3'
                    => 'TER_C',

                default
                    => 'TER_A',
            };

        $brackets =
            $this->terCategories()[
                $category
            ]['brackets']
            ?? [];

        foreach (
            $brackets as $bracket
        ) {

            if (
                $bracket['max']
                ===
                'Seterusnya'
                ||
                $gross
                <=
                (float) $bracket['max']
            ) {

                return (
                    (float)
                    $bracket['rate']
                ) / 100;
            }
        }

        return 0.0;
    }
}