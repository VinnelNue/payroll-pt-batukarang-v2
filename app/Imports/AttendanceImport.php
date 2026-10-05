<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\ContractHistoryLocal;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $period;

    /**
     * Satu proses import dapat membaca beberapa file
     * (misalnya ZIP), sehingga data ditampung sementara.
     */
    protected static array $globalAttendanceBuffer = [];

    public function __construct($period)
    {
        $this->period = $period ?? date('Y-m');
    }

    /**
     * Membaca file fingerprint saja.
     *
     * Tidak menghitung payroll.
     * Tidak menghitung BPJS.
     * Tidak mengubah payroll.daily_attendance.
     */
    public function collection(Collection $rows)
    {
        Log::info(
            "=== MEMBACA FILE ABSENSI (Target Periode: {$this->period}) ==="
        );

        foreach ($rows as $index => $row) {
            try {
                $cleanRow = [];

                foreach ($row as $key => $val) {
                    $cleanKey = strtolower(
                        trim(
                            preg_replace(
                                '/[^a-zA-Z0-9_]/',
                                '',
                                (string) $key
                            )
                        )
                    );

                    $cleanRow[$cleanKey] = is_string($val)
                        ? trim($val)
                        : $val;
                }

                $pinMesin = trim(
                    (string) ($cleanRow['pin'] ?? '')
                );

                $nikMesin = strtoupper(
                    trim(
                        (string) ($cleanRow['nik'] ?? '')
                    )
                );

                $namaKaryawan = trim(
                    (string) (
                        $cleanRow['namakaryawan']
                        ?? $cleanRow['nama_karyawan']
                        ?? $cleanRow['nama']
                        ?? ''
                    )
                );

                $rawDate =
                    $cleanRow['tanggal']
                    ?? $cleanRow['date']
                    ?? null;

                if (
                    (empty($nikMesin) && empty($pinMesin))
                    || empty($rawDate)
                ) {
                    continue;
                }

                $dateObj = null;

                if (is_numeric($rawDate)) {
                    $dateObj = Carbon::instance(
                        \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            $rawDate
                        )
                    );
                } else {
                    $dateStr = str_replace(
                        ['/', '.'],
                        '-',
                        (string) $rawDate
                    );

                    if (
                        preg_match(
                            '/^\d{1,2}-\d{1,2}-\d{4}$/',
                            $dateStr
                        )
                    ) {
                        $dateObj = Carbon::createFromFormat(
                            'd-m-Y',
                            $dateStr
                        );
                    } else {
                        $dateObj = Carbon::parse($dateStr);
                    }
                }

                $targetDatabasePeriod = $dateObj->format('Y-m');
                $dateString = $dateObj->format('Y-m-d');

                $identityKey = $nikMesin ?: $pinMesin;

                $mapKey =
                    $targetDatabasePeriod
                    . '|'
                    . $identityKey
                    . '|'
                    . strtolower($namaKaryawan);

                self::$globalAttendanceBuffer[$targetDatabasePeriod] ??= [];

                self::$globalAttendanceBuffer[$targetDatabasePeriod][$mapKey] ??= [
                    'period'        => $targetDatabasePeriod,
                    'nik_mesin'     => $nikMesin,
                    'pin_mesin'     => $pinMesin,
                    'nama_karyawan' => $namaKaryawan,
                    'daily'         => [],
                ];

                /*
                 * Setiap log fingerprint = Hadir.
                 * Duplikasi tanggal akan tetap menjadi satu record H.
                 */
                self::$globalAttendanceBuffer
                    [$targetDatabasePeriod]
                    [$mapKey]
                    ['daily'][$dateString] = 'H';

            } catch (\Throwable $e) {
                Log::error(
                    "Gagal membaca baris absensi index {$index}: "
                    . $e->getMessage(),
                    [
                        'row' => $row->toArray(),
                    ]
                );
            }
        }
    }

    /**
     * Simpan buffer fingerprint ke attendance_records.
     *
     * Local:
     * - sumber attendance tetap attendance_records
     * - tidak membuat payroll
     * - tidak mengubah payroll.daily_attendance
     *
     * Contract History digunakan berdasarkan tanggal attendance,
     * bukan berdasarkan current history saja.
     */
    public static function saveSummaryToDatabase($defaultPeriod)
    {
        Log::info(
            "=== MULAI MENYIMPAN BUFFER ABSENSI KE attendance_records ==="
        );

        if (empty(self::$globalAttendanceBuffer)) {
            Log::warning(
                "Buffer absensi kosong, tidak ada data yang disimpan."
            );

            return [
                'inserted' => 0,
                'updated' => 0,
                'skipped_locked' => 0,
                'unmatched' => 0,
            ];
        }

        $inserted = 0;
        $updated = 0;
        $skippedLocked = 0;
        $unmatched = 0;

        foreach (self::$globalAttendanceBuffer as $periodKey => $employeesData) {
            $targetPeriod = $periodKey ?? $defaultPeriod;

            Log::info(
                "Memproses periode {$targetPeriod} ("
                . count($employeesData)
                . " karyawan)"
            );

            foreach ($employeesData as $data) {
                $nikMesin = trim(
                    (string) ($data['nik_mesin'] ?? '')
                );

                $pinMesin = trim(
                    (string) ($data['pin_mesin'] ?? '')
                );

                $namaKaryawan = trim(
                    (string) ($data['nama_karyawan'] ?? '')
                );

                $employee = null;
                $history = null;

                /*
                 * ==========================================================
                 * 1. CARI CONTRACT HISTORY BERDASARKAN NIK / PIN + TANGGAL
                 * ==========================================================
                 *
                 * Jangan menggunakan currentHistory saja.
                 *
                 * Contoh:
                 * History 1 : 01-01-2026 s/d 25-09-2026
                 * History 2 : 26-09-2026 s/d 25-12-2026
                 *
                 * Absensi 20-09 harus masuk History 1,
                 * walaupun saat import Current History sudah History 2.
                 *
                 * end_date bersifat exclusive:
                 * tanggal end_date sendiri sudah dianggap selesai.
                 */
                foreach ($data['daily'] as $dateString => $status) {
                    try {
                        $attendanceDate = Carbon::parse($dateString);
                    } catch (\Throwable) {
                        continue;
                    }

                    $historyQuery = ContractHistoryLocal::query()
                        ->with('contract.employee')
                        ->where(function ($query) use ($nikMesin, $pinMesin) {
                            if (!empty($nikMesin)) {
                                $query->orWhere(
                                    'nik_fingerprint',
                                    $nikMesin
                                );
                            }

                            if (!empty($pinMesin)) {
                                $query->orWhere(
                                    'fingerprint_pin',
                                    $pinMesin
                                );
                            }
                        })
                        ->whereDate(
                            'start_date',
                            '<=',
                            $attendanceDate->format('Y-m-d')
                        )
                        ->where(function ($query) use ($attendanceDate) {
                            $query
                                ->whereNull('end_date')
                                ->orWhereDate(
                                    'end_date',
                                    '>',
                                    $attendanceDate->format('Y-m-d')
                                );
                        })
                        ->orderByDesc('start_date');

                    $history = $historyQuery->first();

                    if ($history) {
                        $employee = $history->contract?->employee;
                        break;
                    }
                }

                /*
                 * ==========================================================
                 * 2. FALLBACK CARI EMPLOYEE BERDASARKAN NAMA
                 * ==========================================================
                 */
                if (!$employee && !empty($namaKaryawan)) {
                    $employee = Employee::query()
                        ->whereRaw(
                            'LOWER(TRIM(full_name)) = ?',
                            [strtolower($namaKaryawan)]
                        )
                        ->first();

                    if (!$employee) {
                        $employee = Employee::query()
                            ->whereRaw(
                                'LOWER(TRIM(full_name)) LIKE ?',
                                [
                                    '%'
                                    . strtolower($namaKaryawan)
                                    . '%',
                                ]
                            )
                            ->first();
                    }

                    if ($employee) {
                        foreach ($data['daily'] as $dateString => $status) {
                            try {
                                $attendanceDate = Carbon::parse($dateString);
                            } catch (\Throwable) {
                                continue;
                            }

                            $history = $employee->contract?->histories()
                                ->whereDate(
                                    'start_date',
                                    '<=',
                                    $attendanceDate->format('Y-m-d')
                                )
                                ->where(function ($query) use ($attendanceDate) {
                                    $query
                                        ->whereNull('end_date')
                                        ->orWhereDate(
                                            'end_date',
                                            '>',
                                            $attendanceDate->format('Y-m-d')
                                        );
                                })
                                ->orderByDesc('start_date')
                                ->first();

                            if ($history) {
                                break;
                            }
                        }
                    }
                }

                /*
                 * Tidak boleh menyimpan absensi kalau employee/history
                 * tidak berhasil ditemukan.
                 */
                if (!$employee || !$history) {
                    $unmatched++;

                    Log::warning(
                        "Karyawan absensi / Contract History tidak ditemukan.",
                        [
                            'nik' => $nikMesin,
                            'pin' => $pinMesin,
                            'nama' => $namaKaryawan,
                            'period' => $targetPeriod,
                        ]
                    );

                    continue;
                }

                /*
                 * ==========================================================
                 * 3. AUTO-BIND NIK / PIN KE HISTORY YANG BERLAKU
                 * ==========================================================
                 *
                 * Jadi binding juga mengikuti period attendance.
                 */
                $updateData = [];

                if (
                    !empty($nikMesin)
                    && empty($history->nik_fingerprint)
                ) {
                    $updateData['nik_fingerprint'] = $nikMesin;
                }

                if (
                    !empty($pinMesin)
                    && empty($history->fingerprint_pin)
                ) {
                    $updateData['fingerprint_pin'] = $pinMesin;
                }

                if (!empty($updateData)) {
                    $history->update($updateData);
                    $history->refresh();
                }

                $empId = $employee->id_employee;

                /*
                 * ==========================================================
                 * 4. CEK PAYROLL LOCK
                 * ==========================================================
                 *
                 * Attendance <= cutoff:
                 *   locked -> tidak boleh diubah
                 *
                 * Attendance > cutoff:
                 *   masih boleh masuk / update sebagai gantungan.
                 */
                $payroll = \App\Models\Payroll::query()
                    ->where('employee_id', $empId)
                    ->where('period_month', $targetPeriod)
                    ->first();

                $isLocked = (bool) ($payroll?->is_locked ?? false);

                $cutoffDay = (int) (
                    $payroll?->cutoff_day ?? 26
                );

                /*
                 * ==========================================================
                 * 5. SIMPAN SETIAP TANGGAL
                 * ==========================================================
                 */
                foreach ($data['daily'] as $dateString => $status) {
                    try {
                        $attendanceDate = Carbon::parse($dateString);

                        if (
                            $attendanceDate->format('Y-m')
                            !== $targetPeriod
                        ) {
                            Log::warning(
                                "Tanggal attendance di luar periode.",
                                [
                                    'employee_id' => $empId,
                                    'date' => $dateString,
                                    'period' => $targetPeriod,
                                ]
                            );

                            continue;
                        }

                        /*
                         * Pastikan tanggal tersebut memang masih berada
                         * dalam salah satu Contract History.
                         */
                        $dateHistory = ContractHistoryLocal::query()
                            ->where('id_contract_history', $history->id_contract_history)
                            ->whereDate(
                                'start_date',
                                '<=',
                                $dateString
                            )
                            ->where(function ($query) use ($dateString) {
                                $query
                                    ->whereNull('end_date')
                                    ->orWhereDate(
                                        'end_date',
                                        '>',
                                        $dateString
                                    );
                            })
                            ->first();

                        if (!$dateHistory) {
                            Log::warning(
                                "Attendance berada di luar Contract History.",
                                [
                                    'employee_id' => $empId,
                                    'date' => $dateString,
                                    'period' => $targetPeriod,
                                ]
                            );

                            continue;
                        }

                        $dayNumber = (int) $attendanceDate->format('j');

                        if (
                            $isLocked
                            && $dayNumber <= $cutoffDay
                        ) {
                            $skippedLocked++;

                            Log::info(
                                "Attendance dikunci, tanggal dilewati.",
                                [
                                    'employee_id' => $empId,
                                    'date' => $dateString,
                                    'cutoff' => $cutoffDay,
                                ]
                            );

                            continue;
                        }

                        $existingRecord = AttendanceRecord::query()
                            ->where('employee_id', $empId)
                            ->whereDate(
                                'attendance_date',
                                $dateString
                            )
                            ->first();

                        AttendanceRecord::updateOrCreate(
                            [
                                'employee_id' => $empId,
                                'attendance_date' => $dateString,
                            ],
                            [
                                'status' => $status ?: 'H',
                                'source' => 'fingerprint',
                            ]
                        );

                        if ($existingRecord) {
                            $updated++;
                        } else {
                            $inserted++;
                        }

                    } catch (\Throwable $e) {
                        Log::error(
                            "Gagal menyimpan attendance.",
                            [
                                'employee_id' => $empId,
                                'date' => $dateString,
                                'error' => $e->getMessage(),
                            ]
                        );
                    }
                }
            }
        }

        self::$globalAttendanceBuffer = [];

        Log::info(
            "=== SELESAI MENYIMPAN attendance_records ===",
            [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped_locked' => $skippedLocked,
                'unmatched' => $unmatched,
            ]
        );

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped_locked' => $skippedLocked,
            'unmatched' => $unmatched,
        ];
    }
}
