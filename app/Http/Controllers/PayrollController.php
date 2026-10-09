<?php

namespace App\Http\Controllers;

use App\Imports\AttendanceImport;
use App\Exports\PayrollLocalExport;
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

    private const HEAD_HRD_ROLES = [
        'head_hrd',
        'kepala_hrd',
    ];

    private const HRD_ROLE = 'hrd';

    private const ATTENDANCE_ACCESS_ROLES = [
        'manager_keuangan',
        'super_admin',
        'head_hrd',
        'kepala_hrd',
        'hrd',
    ];

    private const DEFAULT_CUTOFF_DAY = 26;
    private const MIN_CUTOFF_DAY = 20;
    private const MAX_CUTOFF_DAY = 28;

    private const DEFAULT_STANDARD_WORK_DAYS = 26;
    private const DEFAULT_OVERTIME_RATE = 20000;

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
    ];

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

    private function isHeadHrdRole(): bool
    {
        return in_array(
            $this->userRole(),
            self::HEAD_HRD_ROLES,
            true
        );
    }

    private function canImportAttendance(): bool
    {
        return in_array(
            $this->userRole(),
            self::ATTENDANCE_ACCESS_ROLES,
            true
        );
    }

    private function canExportExcel(): bool
    {
        return in_array(
            $this->userRole(),
            self::ATTENDANCE_ACCESS_ROLES,
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

        if (!$this->isHeadHrdRole()) {
            return false;
        }

        $level = $employee->contract?->currentHistory?->level;

        /*
         * Head HRD:
         * Level 1-13  = data finansial terlihat
         * Level 14+    = data finansial disembunyikan
         */
        return $level !== null
            && (int) $level <= 13;
    }

    private function canViewFinancialFromLevel(?int $level): bool
    {
        if ($this->isFinanceRole()) {
            return true;
        }

        return $this->isHeadHrdRole()
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

    private function calculateAllowance($contract): float
    {
        if (!$contract) {
            return 0.0;
        }

        $basicSalary =
            (float) ($contract->basic_salary ?? 0);

        $level =
            (int) ($contract->level ?? 0);

        if ($basicSalary <= 0 || $level <= 0) {
            return 0.0;
        }

        return round(
            $basicSalary * ($level * 0.02),
            2
        );
    }

    private function normalizeCutoffDay(mixed $value): int
    {
        $cutoffDay = (int) $value;

        if (
            $cutoffDay < self::MIN_CUTOFF_DAY
            ||
            $cutoffDay > self::MAX_CUTOFF_DAY
        ) {
            return self::DEFAULT_CUTOFF_DAY;
        }

        return $cutoffDay;
    }

    private function defaultCutoffDay(): int
    {
        return $this->normalizeCutoffDay(
            CompanySetting::get(
                'attendance_cutoff_day',
                self::DEFAULT_CUTOFF_DAY
            )
        );
    }

    private function getPeriodCutoffDay(string $period): int
    {
        /*
         * PRIORITAS CUT-OFF:
         *
         * 1. cutoff_day yang sudah tersimpan pada payroll periode
         * 2. CompanySetting khusus periode: attendance_cutoff_day:{period}
         * 3. CompanySetting global attendance_cutoff_day sebagai default
         *
         * Dengan pola ini setiap periode benar-benar independen:
         *
         * Juli     = 26
         * Agustus  = 24
         * September = 27
         *
         * Selama nilainya berada pada 20-28.
         */
        $periodCutoff =
            Payroll::query()
                ->where(
                    'period_month',
                    $period
                )
                ->whereBetween(
                    'cutoff_day',
                    [
                        self::MIN_CUTOFF_DAY,
                        self::MAX_CUTOFF_DAY,
                    ]
                )
                ->orderByDesc('id_payroll')
                ->value('cutoff_day');

        if ($periodCutoff !== null) {
            return $this->normalizeCutoffDay(
                $periodCutoff
            );
        }

        $periodSettingKey =
            'attendance_cutoff_day:' . $period;

        $periodSetting =
            CompanySetting::get(
                $periodSettingKey,
                null
            );

        if ($periodSetting !== null) {
            return $this->normalizeCutoffDay(
                $periodSetting
            );
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

        $employees = Employee::with('contract.currentHistory')
            ->where('is_active', true)
            ->get();

        $payrollCollection = Payroll::with([
            'employee.contract.currentHistory',
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
                return $payroll->employee?->contract?->currentHistory?->department
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

        $previousPeriod = Carbon::createFromFormat(
            'Y-m-d',
            $period . '-01'
        )
            ->subMonth()
            ->format('Y-m');

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

            'contract.currentHistory',

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


        // ========================================================
        // PREVIOUS PAYROLL / GANTUNGAN PREVIEW
        // ========================================================

        $previousPayrolls = Payroll::query()
            ->where(
                'period_month',
                $previousPeriod
            )
            ->whereIn(
                'employee_id',
                $employees->pluck('id_employee')
            )
            ->get([
                'id_payroll',
                'employee_id',
                'period_month',
                'cutoff_day',
                'basic_salary',
                'allowance',
                'gantungan_days',
                'gantungan_deduction',
                'is_locked',
            ])
            ->keyBy(
                'employee_id'
            );

        // ========================================================
        // PREVIOUS GANTUNGAN PREVIEW
        // ========================================================
        //
        // Preview harus selalu mewakili gantungan bulan sebelumnya.
        // Payroll snapshot dipakai terlebih dahulu. Bila snapshot
        // masih 0/null tetapi attendance bulan sebelumnya memiliki
        // A/I/H0.5 setelah cutoff, helper akan menghitung fallback
        // dari attendance agar data lama tetap dapat dipakai.
        // ========================================================

        $previousGantunganPreview = collect();

        $previewStandardWorkDays =
            $this->standardWorkDays();

        foreach ($employees as $employee) {

            $previousPayroll =
                $previousPayrolls->get(
                    $employee->id_employee
                );

            $previousGantunganPreview->put(
                $employee->id_employee,
                $this->calculatePreviousGantungan(
                    $employee,
                    $previousPeriod,
                    $previewStandardWorkDays,
                    $previousPayroll
                )
            );
        }

        return view(
            'payrolls.local.create',
            compact(
                'employees',
                'period',
                'isLocked',
                'isNextPeriodLocked',
                'cutoffDay',
                'savedCutoffDay',
                'holidays',
                'previousPayrolls',
                'previousGantunganPreview',
                'previousPeriod'
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

        DB::transaction(function () use (
            $period,
            $cutoffDay
        ) {
            /*
             * Simpan cutoff sebagai cutoff PERIODE pada payroll
             * yang sudah ada.
             */
            Payroll::where(
                'period_month',
                $period
            )
                ->update([
                    'cutoff_day' => $cutoffDay,
                ]);

            /*
             * Simpan juga konfigurasi cutoff khusus periode.
             *
             * Ini membuat perubahan cutoff Agustus tidak mengubah
             * cutoff Juli maupun default periode lain.
             */
            CompanySetting::updateOrCreate(
                [
                    'key' =>
                        'attendance_cutoff_day:' . $period,
                ],
                [
                    'value' =>
                        $cutoffDay,
                ]
            );
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
                "Cut-off periode {$period} diset ke tanggal {$cutoffDay}."
            );
    }

    // ============================================================
    // IMPORT ABSENSI
    // ============================================================

    public function import(Request $request)
    {
        abort_unless(
            $this->canImportAttendance(),
            403
        );

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

        /*
         * Cut-off selalu FLEXIBLE per periode: 20-28.
         *
         * Urutan sumber:
         * 1. Payroll period yang sudah tersimpan
         * 2. Setting khusus period
         * 3. Setting global sebagai default
         *
         * Finance tetap dapat menggantinya saat periode belum lock
         * melalui cutoff_day_submit.
         */
        $cutoffDay =
            $this->getPeriodCutoffDay(
                $period
            );

        if (
            !$existingPeriodLocked
            &&
            $this->canManageCutoff()
            &&
            array_key_exists(
                'cutoff_day_submit',
                $validated
            )
            &&
            $validated['cutoff_day_submit'] !== null
        ) {
            $cutoffDay =
                $this->normalizeCutoffDay(
                    $validated['cutoff_day_submit']
                );
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
                'contract.currentHistory'
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
        // LOCKED PERIOD = ATTENDANCE AFTER CUTOFF ONLY
        // ========================================================
        //
        // Payroll yang sudah lock tidak dihitung ulang.
        // Yang masih boleh diubah hanya attendance > cutoff.
        // Attendance <= cutoff tetap permanen.
        // Jika periode berikutnya sudah lock, gantungan juga ikut lock.
        //
        // ========================================================

        if ($existingPeriodLocked) {

            $nextPeriod =
                Carbon::createFromFormat(
                    'Y-m',
                    $period
                )
                    ->addMonth()
                    ->format('Y-m');

            $isNextPeriodLocked =
                Payroll::query()
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
                        'payrolls.local.create',
                        ['period' => $period]
                    )
                    ->with(
                        'error',
                        "Periode {$period} dan periode {$nextPeriod} sudah di-lock. "
                        . "Attendance setelah cutoff tidak dapat diubah lagi."
                    );
            }

            DB::transaction(function () use (
                $validated,
                $employees,
                $period,
                $cutoffDay
            ) {

                foreach (
                    $validated['payrolls'] as $empId => $data
                ) {

                    $empId = (int) $empId;

                    $employee =
                        $employees->get($empId);

                    if (
                        !$employee
                        ||
                        !$employee->contract?->currentHistory
                        ||
                        !$employee->contract?->currentHistory?->is_active
                    ) {
                        continue;
                    }

                    $submittedDaily =
                        $data['daily_attendance']
                        ?? [];

                    if (!is_array($submittedDaily)) {
                        continue;
                    }

                    foreach (
                        $submittedDaily as $dateKey => $status
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

                        if ($day <= $cutoffDay) {
                            continue;
                        }

                        $normalizedStatus =
                            $this->normalizeAttendanceStatus(
                                $status
                            );

                        $dateString =
                            $date->format('Y-m-d');

                        if ($normalizedStatus === 'HB') {

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

                        if ($normalizedStatus === '') {

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
                }

                // ====================================================
                // UPDATE SNAPSHOT GANTUNGAN SETELAH ATTENDANCE DISIMPAN
                // ====================================================
                //
                // Periode yang sudah lock tetap boleh menerima attendance
                // setelah cutoff. Attendance tersebut harus menjadi
                // gantungan untuk bulan berikutnya. Yang tidak boleh
                // berubah adalah perhitungan gaji periode yang sudah lock.
                //
                // Contoh:
                // Juli lock 01-26
                // 27 Juli = A
                // 28 Juli = I
                //
                // Payroll Juli:
                //   gantungan_days = 2
                //   gantungan_deduction = nominal 2 hari Juli
                //
                // Agustus kemudian membaca dua field tersebut sebagai
                // previous gantungan.
                // ====================================================

                $standardWorkDays =
                    $this->standardWorkDays();

                foreach (
                    $validated['payrolls'] as $empId => $data
                ) {

                    $empId = (int) $empId;

                    $payroll =
                        Payroll::query()
                            ->where('employee_id', $empId)
                            ->where('period_month', $period)
                            ->first();

                    if (!$payroll) {
                        continue;
                    }

                    $attendanceRecords =
                        $this->loadAttendanceRecords(
                            $empId,
                            $period
                        );

                    $gantunganDays =
                        $this->calculateCurrentGantungan(
                            $attendanceRecords,
                            $cutoffDay
                        );

                    $basicSalary =
                        (float) ($payroll->basic_salary ?? 0);

                    $allowance =
                        (float) ($payroll->allowance ?? 0);

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

                    $payroll->update([
                        'gantungan_days' =>
                            round($gantunganDays, 1),

                        'gantungan_deduction' =>
                            round($gantunganDeduction, 2),
                    ]);
                }
            });

            return redirect()
                ->route(
                    'payrolls.local.create',
                    ['period' => $period]
                )
                ->with(
                    'success',
                    "Attendance setelah cutoff periode {$period} berhasil disimpan. "
                    . "Payroll periode {$period} tetap terkunci dan tidak dihitung ulang."
                );
        }

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
                    'gantungan_days',
                    'gantungan_deduction',
                    'cutoff_day',
                    'is_locked',
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
            $periodDate,
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
                    !$employee->contract?->currentHistory
                ) {
                    continue;
                }

                $contract =
                    $employee->contract?->currentHistory;

                $existing =
                    $existingPayrolls->get(
                        $empId
                    );

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

                $periodContractInvalid =
                    !$contract->is_active
                    ||
                    ($contractStart && $periodDate->copy()->endOfMonth()->lt($contractStart))
                    ||
                    ($contractEnd && $periodDate->copy()->startOfMonth()->gte($contractEnd));

                if ($periodContractInvalid) {

                    Payroll::updateOrCreate(
                        [
                            'employee_id' =>
                                $empId,

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
                    $this->calculateAllowance(
                        $contract
                    );

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

                    /*
                     * BPJS employee contribution base:
                     * upah sebulan = gaji pokok + tunjangan tetap.
                     *
                     * Contract payroll menggunakan Tunj. Jabatan sebagai
                     * tunjangan tetap bulanan, sehingga basis BPJS memakai
                     * basic salary + allowance.
                     */
                    $bpjsWageBase =
                        max(
                            0,
                            $basicSalary
                            +
                            $allowance
                        );

                    $bpjsTkDeduction =
                        (
                            $contract
                                ->is_bpjstk_active
                            ?? false
                        )
                            ? (
                                $bpjsWageBase
                                *
                                $tkRate
                            )
                            : 0;

                    $basisBpjsKs =
                        min(
                            $bpjsWageBase,
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
                //
                // Jan-Nov / setiap masa pajak selain masa pajak terakhir:
                // Gross x TER category dari Contract (A/B/C).
                //
                // Masa pajak terakhir:
                // hitung PPh21 setahun/bagian tahun dengan tarif Pasal 17,
                // lalu kurangi seluruh PPh21 masa sebelumnya.
                // ====================================================

                $terCategory =
                    $this->resolveTerCategory(
                        $contract
                    );

                if (
                    $this->isPph21FinalPeriod(
                        $period,
                        $contract
                    )
                ) {
                    $pph21Deduction =
                        $this->calculateFinalPph21(
                            $employee,
                            $contract,
                            $period,
                            $grossSalary,
                            $bpjsTkDeduction
                        );
                } else {
                    $pph21Rate =
                        $this->calculateTerRateByCategory(
                            $terCategory,
                            $grossSalary
                        );

                    $pph21Deduction =
                        $grossSalary
                        *
                        $pph21Rate;
                }

                $pph21Deduction =
                    round(
                        $pph21Deduction,
                        2
                    );

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

        // ========================================================
        // PRIORITAS 1: SNAPSHOT PAYROLL BULAN SEBELUMNYA
        // ========================================================
        //
        // Bila snapshot sudah memiliki nilai gantungan, gunakan
        // snapshot tersebut. Ini menjaga nominal historis agar tidak
        // berubah ketika gaji bulan berikutnya berubah.
        // ========================================================

        $snapshotDays =
            (float) (
                $previousPayroll?->gantungan_days
                ?? 0
            );

        $snapshotDeduction =
            (float) (
                $previousPayroll?->gantungan_deduction
                ?? 0
            );

        if (
            $previousPayroll
            && (
                $snapshotDays > 0
                ||
                $snapshotDeduction > 0
            )
        ) {
            return [
                'days' => round(
                    $snapshotDays,
                    1
                ),
                'deduction' => round(
                    $snapshotDeduction,
                    2
                ),
                'cutoff_day' =>
                    $previousPayroll->cutoff_day
                    ?? $this->getPeriodCutoffDay(
                        $previousPeriod
                    ),
                'basic_salary' =>
                    (float) (
                        $previousPayroll->basic_salary
                        ?? 0
                    ),
                'allowance' =>
                    (float) (
                        $previousPayroll->allowance
                        ?? 0
                    ),
            ];
        }

        // ========================================================
        // FALLBACK: ATTENDANCE BULAN SEBELUMNYA
        // ========================================================
        //
        // Ini penting untuk data historis yang terlanjur disimpan
        // sebelum snapshot gantungan diperbarui. Jadi Agustus tetap
        // bisa menemukan A/I/H0.5 Juli setelah cutoff.
        // ========================================================

        $previousCutoffDay =
            $previousPayroll?->cutoff_day
            ?? $this->getPeriodCutoffDay(
                $previousPeriod
            );

        $records =
            $this->loadAttendanceRecords(
                (int) $employee->id_employee,
                $previousPeriod
            );

        $gantunganDays =
            $this->calculateCurrentGantungan(
                $records,
                (int) $previousCutoffDay
            );

        // Tidak ada gantungan.
        if ($gantunganDays <= 0) {
            return [
                'days' => 0.0,
                'deduction' => 0.0,
                'cutoff_day' =>
                    $previousCutoffDay,
                'basic_salary' =>
                    (float) (
                        $previousPayroll?->basic_salary
                        ??
                        $employee->contract?->currentHistory?->basic_salary
                        ?? 0
                    ),
                'allowance' =>
                    (float) (
                        $previousPayroll?->allowance
                        ??
                        $employee->contract?->currentHistory?->allowance
                        ?? 0
                    ),
            ];
        }

        // ========================================================
        // SALARY BASIS BULAN SEBELUMNYA
        // ========================================================
        //
        // Prioritas:
        // 1. Payroll bulan sebelumnya
        // 2. Contract saat ini sebagai fallback terakhir
        // ========================================================

        $basicSalary =
            (float) (
                $previousPayroll?->basic_salary
                ??
                $employee->contract?->currentHistory?->basic_salary
                ?? 0
            );

        $allowance =
            (float) (
                $previousPayroll?->allowance
                ??
                $employee->contract?->currentHistory?->allowance
                ?? 0
            );

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
                    'attendance_cutoff_day:' . $period,
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
    
    // ============================================================
    // PDF / EMAIL
    // ============================================================

    public function printPdf(
        string $uuid,
        ?string $period = null
    ) {

        $query =
            Payroll::with([
                'employee.contract.currentHistory',
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
                'employee.contract.currentHistory',
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
            self::ATTENDANCE_STATUSES,
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

        $maternityLeaveDays = 0.0;

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
                case 'S':
                case 'C':
                case 'CM':
                case 'M/HB':

                    $paidAbsenceDays += 1;

                    if ($status === 'CM') {
                        $maternityLeaveDays += 1;
                    }

                    if (
                        $status === 'CM'
                        ||
                        $status === 'M/HB'
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

            'maternity_leave_days' =>
                round(
                    $maternityLeaveDays,
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
    // PPH 21 / TER
    // ============================================================

    /**
     * Resolve TER category from the employee contract.
     *
     * Contract.ter_category is the primary source of truth because
     * the company explicitly maintains TER A/B/C on Contract.
     *
     * Fallback to PTKP is retained for legacy records where
     * ter_category is still NULL.
     */
    private function resolveTerCategory($contract): string
    {
        $category =
            strtoupper(
                trim(
                    (string) (
                        $contract->ter_category
                        ?? ''
                    )
                )
            );

        if (
            in_array(
                $category,
                ['A', 'B', 'C'],
                true
            )
        ) {
            return $category;
        }

        return $this->deriveTerCategoryFromPtkp(
            $contract->ptkp_status
                ?? 'TK0'
        );
    }

    /**
     * Fallback mapping for legacy records.
     *
     * Official mapping:
     * A = TK/0, TK/1, K/0
     * B = TK/2, TK/3, K/1, K/2
     * C = K/3
     */
    private function deriveTerCategoryFromPtkp(
        mixed $ptkp
    ): string {
        $raw =
            strtoupper(
                trim(
                    (string) (
                        $ptkp
                        ?? ''
                    )
                )
            );

        $map = [
            'TK/0' => 'A',
            'TK0'  => 'A',

            'TK/1' => 'A',
            'TK1'  => 'A',
            'TK01' => 'A',

            'K/0'  => 'A',
            'K0'   => 'A',
            'K01'  => 'A',

            'TK/2' => 'B',
            'TK2'  => 'B',
            'TK02' => 'B',

            'TK/3' => 'B',
            'TK3'  => 'B',
            'TK03' => 'B',

            'K/1'  => 'B',
            'K1'   => 'B',
            'K02'  => 'B',

            'K/2'  => 'B',
            'K2'   => 'B',
            'K03'  => 'B',

            'K/3'  => 'C',
            'K3'   => 'C',
            'K04'  => 'C',
        ];

        return $map[$raw] ?? 'A';
    }

    /**
     * PTKP annual values used in the final/annual PPh21 calculation.
     *
     * The payroll system stores the normalized PTKP code on Contract.
     */
    private function ptkpAnnualValue(
        mixed $ptkp
    ): float {
        $raw =
            strtoupper(
                trim(
                    (string) (
                        $ptkp
                        ?? ''
                    )
                )
            );

        $map = [
            'TK/0' => 54000000,
            'TK0'  => 54000000,

            'TK/1' => 58500000,
            'TK1'  => 58500000,
            'TK01' => 58500000,

            'TK/2' => 63000000,
            'TK2'  => 63000000,
            'TK02' => 63000000,

            'TK/3' => 67500000,
            'TK3'  => 67500000,
            'TK03' => 67500000,

            'TK/4' => 72000000,
            'TK4'  => 72000000,
            'TK04' => 72000000,

            'K/0'  => 58500000,
            'K0'   => 58500000,
            'K01'  => 58500000,

            'K/1'  => 63000000,
            'K1'   => 63000000,
            'K02'  => 63000000,

            'K/2'  => 67500000,
            'K2'   => 67500000,
            'K03'  => 67500000,

            'K/3'  => 72000000,
            'K3'   => 72000000,
            'K04'  => 72000000,
        ];

        return (float) (
            $map[$raw]
            ?? 54000000
        );
    }

    /**
     * True when the current payroll is the employee's final tax month.
     *
     * December is always the final tax month. An actual exit_date within
     * the current month is also treated as the final tax month.
     *
     * We intentionally do not use end_date as the final-tax trigger because
     * contract periods may roll into another contract without ending the
     * employee's tax obligation.
     */
    private function isPph21FinalPeriod(
        string $period,
        $contract
    ): bool {
        try {
            $month =
                (int) Carbon::createFromFormat(
                    'Y-m',
                    $period
                )->format('m');
        } catch (Throwable) {
            $month = 0;
        }

        if ($month === 12) {
            return true;
        }

        $exitDate = $contract->exit_date ?? null;

        if (!$exitDate) {
            return false;
        }

        try {
            return Carbon::parse($exitDate)
                ->format('Y-m') === $period;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Progressive PPh21 under Article 17.
     *
     * PKP is already rounded down to full thousands before this method
     * is called.
     */
    private function calculateProgressivePph21(
        float $pkp
    ): float {
        $pkp = max(0, floor($pkp / 1000) * 1000);

        if ($pkp <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $remaining = $pkp;

        $brackets = [
            [
                'limit' => 60000000,
                'rate'  => 0.05,
            ],
            [
                'limit' => 190000000,
                'rate'  => 0.15,
            ],
            [
                'limit' => 250000000,
                'rate'  => 0.25,
            ],
            [
                'limit' => 4500000000,
                'rate'  => 0.30,
            ],
            [
                'limit' => null,
                'rate'  => 0.35,
            ],
        ];

        foreach ($brackets as $bracket) {
            if (
                $remaining <= 0
            ) {
                break;
            }

            $limit = $bracket['limit'];

            if ($limit === null) {
                $tax +=
                    $remaining
                    *
                    $bracket['rate'];

                $remaining = 0;
                break;
            }

            $taxable =
                min(
                    $remaining,
                    $limit
                );

            $tax +=
                $taxable
                *
                $bracket['rate'];

            $remaining -= $taxable;
        }

        return round(
            $tax,
            2
        );
    }

    /**
     * Final-month PPh21.
     *
     * For a full-year employee (December after a January start),
     * actual annual gross and permitted deductions are used.
     *
     * For an employee whose tax obligation starts/ends inside the year,
     * net income is annualized and the resulting annual tax is prorated
     * to the number of months in the part-year period.
     */
    private function calculateFinalPph21(
        Employee $employee,
        $contract,
        string $period,
        float $currentGrossSalary,
        float $currentBpjsTkDeduction
    ): float {
        $periodDate =
            Carbon::createFromFormat(
                'Y-m',
                $period
            );

        $year =
            (int) $periodDate->format('Y');

        $yearStart =
            $year . '-01';

        $previousPayrolls =
            Payroll::query()
                ->where(
                    'employee_id',
                    $employee->id_employee
                )
                ->where(
                    'period_month',
                    '>=',
                    $yearStart
                )
                ->where(
                    'period_month',
                    '<',
                    $period
                )
                ->orderBy(
                    'period_month'
                )
                ->get([
                    'period_month',
                    'gross_salary',
                    'bpjs_tk_deduction',
                    'pph21_deduction',
                ]);

        $grossBeforeCurrent = 0.0;
        $retirementBeforeCurrent = 0.0;
        $pph21BeforeCurrent = 0.0;

        foreach ($previousPayrolls as $row) {
            $grossBeforeCurrent +=
                (float) (
                    $row->gross_salary
                    ?? 0
                );

            $retirementBeforeCurrent +=
                (float) (
                    $row->bpjs_tk_deduction
                    ?? 0
                );

            $pph21BeforeCurrent +=
                (float) (
                    $row->pph21_deduction
                    ?? 0
                );
        }

        $actualGross =
            $grossBeforeCurrent
            +
            max(
                0,
                $currentGrossSalary
            );

        /*
         * PMK 168/2023 allows deductions for contributions related
         * to pension/old-age programs paid by the employee through
         * the employer. In this system bpjs_tk_deduction is the
         * employee-side BPJS TK deduction field.
         */
        $actualRetirement =
            max(
                0,
                $retirementBeforeCurrent
                +
                max(
                    0,
                    $currentBpjsTkDeduction
                )
            );

        /*
         * Determine the number of months in the relevant tax period.
         *
         * Full-year:
         *   Jan-Dec = 12 months.
         *
         * Part-year:
         *   start month through the current final month.
         */
        $startDate =
            $contract->start_date
                ? Carbon::parse(
                    $contract->start_date
                )
                : $periodDate->copy()->startOfYear();

        $taxStart =
            $startDate->year < $year
                ? $periodDate->copy()->startOfYear()
                : Carbon::create(
                    $year,
                    (int) $startDate->format('m'),
                    1
                );

        $finalMonthDate =
            $periodDate->copy()->startOfMonth();

        if ($taxStart->gt($finalMonthDate)) {
            $taxStart =
                $finalMonthDate->copy();
        }

        $monthsInPartYear =
            (
                (
                    $taxStart->year
                    * 12
                )
                +
                $taxStart->month
            )
            -
            (
                (
                    $finalMonthDate->year
                    * 12
                )
                +
                $finalMonthDate->month
            );

        $monthsInPartYear =
            abs(
                $monthsInPartYear
            ) + 1;

        $monthsInPartYear =
            max(
                1,
                min(
                    12,
                    $monthsInPartYear
                )
            );

        $isFullYear =
            $monthsInPartYear === 12
            &&
            $taxStart->month === 1;

        if ($isFullYear) {
            $annualGross = $actualGross;
            $annualRetirement = $actualRetirement;
            $annualJobExpense =
                min(
                    $annualGross * 0.05,
                    6000000
                );
            $annualNet =
                max(
                    0,
                    $annualGross
                    -
                    $annualJobExpense
                    -
                    $annualRetirement
                );

            $ptkp =
                $this->ptkpAnnualValue(
                    $contract->ptkp_status
                        ?? 'TK0'
                );

            $pkp =
                max(
                    0,
                    floor(
                        max(
                            0,
                            $annualNet
                            -
                            $ptkp
                        )
                        / 1000
                    )
                    * 1000
                );

            $annualTax =
                $this->calculateProgressivePph21(
                    $pkp
                );

            return round(
                $annualTax
                -
                $pph21BeforeCurrent,
                2
            );
        }

        /*
         * Part-year employee:
         * annualize net income and prorate annual tax back to the
         * number of months in the part-year tax obligation.
         */
        $annualizedGross =
            $actualGross
            *
            (
                12
                /
                $monthsInPartYear
            );

        $annualizedRetirement =
            $actualRetirement
            *
            (
                12
                /
                $monthsInPartYear
            );

        $annualJobExpense =
            min(
                $annualizedGross * 0.05,
                6000000
            );

        $annualizedNet =
            max(
                0,
                $annualizedGross
                -
                $annualJobExpense
                -
                $annualizedRetirement
            );

        $ptkp =
            $this->ptkpAnnualValue(
                $contract->ptkp_status
                    ?? 'TK0'
            );

        $pkp =
            max(
                0,
                floor(
                    max(
                        0,
                        $annualizedNet
                        -
                        $ptkp
                    )
                    / 1000
                )
                * 1000
            );

        $annualTax =
            $this->calculateProgressivePph21(
                $pkp
            );

        $partYearTax =
            $annualTax
            *
            (
                $monthsInPartYear
                /
                12
            );

        return round(
            $partYearTax
            -
            $pph21BeforeCurrent,
            2
        );
    }

    private function terCategories(): array
    {
        return [
            'TER_A' => [
                'code' => 'A',
                'ptkp' => 'TK/0, TK/1, K/0',
                'description' =>
                    'Tidak kawin tanggungan 0-1 atau kawin tanpa tanggungan',
                'brackets' => [
                    ['max' => 5400000,    'rate' => 0.00],
                    ['max' => 5650000,    'rate' => 0.25],
                    ['max' => 5950000,    'rate' => 0.50],
                    ['max' => 6300000,    'rate' => 0.75],
                    ['max' => 6750000,    'rate' => 1.00],
                    ['max' => 7500000,    'rate' => 1.25],
                    ['max' => 8550000,    'rate' => 1.50],
                    ['max' => 9650000,    'rate' => 1.75],
                    ['max' => 10050000,   'rate' => 2.00],
                    ['max' => 10350000,   'rate' => 2.25],
                    ['max' => 10700000,   'rate' => 2.50],
                    ['max' => 11050000,   'rate' => 3.00],
                    ['max' => 11600000,   'rate' => 3.50],
                    ['max' => 12500000,   'rate' => 4.00],
                    ['max' => 13750000,   'rate' => 5.00],
                    ['max' => 15100000,   'rate' => 6.00],
                    ['max' => 16950000,   'rate' => 7.00],
                    ['max' => 19750000,   'rate' => 8.00],
                    ['max' => 24150000,   'rate' => 9.00],
                    ['max' => 26450000,   'rate' => 10.00],
                    ['max' => 28000000,   'rate' => 11.00],
                    ['max' => 30050000,   'rate' => 12.00],
                    ['max' => 32400000,   'rate' => 13.00],
                    ['max' => 35400000,   'rate' => 14.00],
                    ['max' => 39100000,   'rate' => 15.00],
                    ['max' => 43850000,   'rate' => 16.00],
                    ['max' => 47800000,   'rate' => 17.00],
                    ['max' => 51400000,   'rate' => 18.00],
                    ['max' => 56300000,   'rate' => 19.00],
                    ['max' => 62200000,   'rate' => 20.00],
                    ['max' => 68600000,   'rate' => 21.00],
                    ['max' => 77500000,   'rate' => 22.00],
                    ['max' => 89000000,   'rate' => 23.00],
                    ['max' => 103000000,  'rate' => 24.00],
                    ['max' => 125000000,  'rate' => 25.00],
                    ['max' => 157000000,  'rate' => 26.00],
                    ['max' => 206000000,  'rate' => 27.00],
                    ['max' => 337000000,  'rate' => 28.00],
                    ['max' => 454000000,  'rate' => 29.00],
                    ['max' => 550000000,  'rate' => 30.00],
                    ['max' => 695000000,  'rate' => 31.00],
                    ['max' => 910000000,  'rate' => 32.00],
                    ['max' => 1400000000, 'rate' => 33.00],
                    ['max' => 'Seterusnya', 'rate' => 34.00],
                ],
            ],

            'TER_B' => [
                'code' => 'B',
                'ptkp' => 'TK/2, TK/3, K/1, K/2',
                'description' =>
                    'Tidak kawin tanggungan 2-3 atau kawin tanggungan 1-2',
                'brackets' => [
                    ['max' => 6200000,     'rate' => 0.00],
                    ['max' => 6500000,     'rate' => 0.25],
                    ['max' => 6850000,     'rate' => 0.50],
                    ['max' => 7300000,     'rate' => 0.75],
                    ['max' => 9200000,     'rate' => 1.00],
                    ['max' => 10750000,    'rate' => 1.50],
                    ['max' => 11250000,    'rate' => 2.00],
                    ['max' => 11600000,    'rate' => 2.50],
                    ['max' => 12600000,    'rate' => 3.00],
                    ['max' => 13600000,    'rate' => 4.00],
                    ['max' => 14950000,    'rate' => 5.00],
                    ['max' => 16400000,    'rate' => 6.00],
                    ['max' => 18450000,    'rate' => 7.00],
                    ['max' => 21850000,    'rate' => 8.00],
                    ['max' => 26000000,    'rate' => 9.00],
                    ['max' => 27700000,    'rate' => 10.00],
                    ['max' => 29350000,    'rate' => 11.00],
                    ['max' => 31450000,    'rate' => 12.00],
                    ['max' => 33950000,    'rate' => 13.00],
                    ['max' => 37100000,    'rate' => 14.00],
                    ['max' => 41100000,    'rate' => 15.00],
                    ['max' => 45800000,    'rate' => 16.00],
                    ['max' => 49500000,    'rate' => 17.00],
                    ['max' => 53800000,    'rate' => 18.00],
                    ['max' => 58500000,    'rate' => 19.00],
                    ['max' => 64000000,    'rate' => 20.00],
                    ['max' => 71000000,    'rate' => 21.00],
                    ['max' => 80000000,    'rate' => 22.00],
                    ['max' => 93000000,    'rate' => 23.00],
                    ['max' => 109000000,   'rate' => 24.00],
                    ['max' => 129000000,   'rate' => 25.00],
                    ['max' => 163000000,   'rate' => 26.00],
                    ['max' => 211000000,   'rate' => 27.00],
                    ['max' => 374000000,   'rate' => 28.00],
                    ['max' => 459000000,   'rate' => 29.00],
                    ['max' => 555000000,   'rate' => 30.00],
                    ['max' => 704000000,   'rate' => 31.00],
                    ['max' => 957000000,   'rate' => 32.00],
                    ['max' => 1405000000,  'rate' => 33.00],
                    ['max' => 'Seterusnya', 'rate' => 34.00],
                ],
            ],

            'TER_C' => [
                'code' => 'C',
                'ptkp' => 'K/3',
                'description' =>
                    'Kawin tanggungan 3',
                'brackets' => [
                    ['max' => 6600000,    'rate' => 0.00],
                    ['max' => 6950000,    'rate' => 0.25],
                    ['max' => 7350000,    'rate' => 0.50],
                    ['max' => 7800000,    'rate' => 0.75],
                    ['max' => 8850000,    'rate' => 1.00],
                    ['max' => 9800000,    'rate' => 1.25],
                    ['max' => 10950000,   'rate' => 1.50],
                    ['max' => 11200000,   'rate' => 1.75],
                    ['max' => 12050000,   'rate' => 2.00],
                    ['max' => 12950000,   'rate' => 3.00],
                    ['max' => 14150000,   'rate' => 4.00],
                    ['max' => 15550000,   'rate' => 5.00],
                    ['max' => 17050000,   'rate' => 6.00],
                    ['max' => 19500000,   'rate' => 7.00],
                    ['max' => 22700000,   'rate' => 8.00],
                    ['max' => 26600000,   'rate' => 9.00],
                    ['max' => 28100000,   'rate' => 10.00],
                    ['max' => 30100000,   'rate' => 11.00],
                    ['max' => 32600000,   'rate' => 12.00],
                    ['max' => 35400000,   'rate' => 13.00],
                    ['max' => 38900000,   'rate' => 14.00],
                    ['max' => 43000000,   'rate' => 15.00],
                    ['max' => 47400000,   'rate' => 16.00],
                    ['max' => 51200000,   'rate' => 17.00],
                    ['max' => 55800000,   'rate' => 18.00],
                    ['max' => 60400000,   'rate' => 19.00],
                    ['max' => 66700000,   'rate' => 20.00],
                    ['max' => 74500000,   'rate' => 21.00],
                    ['max' => 83200000,   'rate' => 22.00],
                    ['max' => 95600000,   'rate' => 23.00],
                    ['max' => 110000000,  'rate' => 24.00],
                    ['max' => 134000000,  'rate' => 25.00],
                    ['max' => 169000000,  'rate' => 26.00],
                    ['max' => 221000000,  'rate' => 27.00],
                    ['max' => 390000000,  'rate' => 28.00],
                    ['max' => 463000000,  'rate' => 29.00],
                    ['max' => 561000000,  'rate' => 30.00],
                    ['max' => 709000000,  'rate' => 31.00],
                    ['max' => 965000000,  'rate' => 32.00],
                    ['max' => 1419000000, 'rate' => 33.00],
                    ['max' => 'Seterusnya', 'rate' => 34.00],
                ],
            ],
        ];
    }

    private function calculateTerRateByCategory(
        string $category,
        float $gross
    ): float {
        $category =
            strtoupper(
                trim(
                    $category
                )
            );

        $terKey =
            match ($category) {
                'B' => 'TER_B',
                'C' => 'TER_C',
                default => 'TER_A',
            };

        $brackets =
            $this->terCategories()[
                $terKey
            ]['brackets']
            ?? [];

        $gross = max(0, $gross);

        foreach ($brackets as $bracket) {
            if (
                $bracket['max'] === 'Seterusnya'
                ||
                $gross <= (float) $bracket['max']
            ) {
                return (
                    (float) $bracket['rate']
                ) / 100;
            }
        }

        return 0.0;
    }

    public function exportExcel(Request $request)
    {
        abort_unless(
            $this->canExportExcel(),
            403
        );

        $validated = $request->validate([
            'period' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $period = $validated['period'];

        /*
         * Export mengikuti halaman Input Absensi:
         * semua employee aktif ikut diexport, walaupun payroll periode
         * tersebut belum pernah disimpan.
         */
        $employeeCount = Employee::query()
            ->where('is_active', true)
            ->count();

        if ($employeeCount === 0) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Tidak ada karyawan aktif untuk diexport.'
                );
        }

        return Excel::download(
            new PayrollLocalExport(
                $period,
                $this->userRole()
            ),
            "Rekap_Payroll_Local_{$period}.xlsx"
        );
    }

}