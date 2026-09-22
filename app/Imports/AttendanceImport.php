<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $period;

    /**
     * Buffer global digunakan karena 1 proses import
     * dapat membaca beberapa file (misalnya ZIP).
     */
    protected static $globalAttendanceBuffer = [];

    public function __construct($period)
    {
        $this->period = $period ?? date('Y-m');
    }

    /**
     * Membaca setiap baris Excel / CSV.
     *
     * Tugas method ini HANYA:
     * - membaca PIN / NIK / nama
     * - membaca tanggal
     * - menyimpan hasil fingerprint ke buffer
     *
     * Tidak menghitung payroll.
     * Tidak menghitung BPJS.
     * Tidak mengubah payrolls.daily_attendance.
     */
    public function collection(Collection $rows)
    {
        Log::info(
            "=== MEMBACA FILE ABSENSI " .
            "(Target Periode: {$this->period}) ==="
        );

        foreach ($rows as $index => $row) {

            try {

                /*
                 * ==========================================================
                 * 1. CLEAN HEADER
                 * ==========================================================
                 */
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


                /*
                 * ==========================================================
                 * 2. AMBIL IDENTITAS KARYAWAN
                 * ==========================================================
                 */
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


                /*
                 * ==========================================================
                 * 3. AMBIL TANGGAL
                 * ==========================================================
                 */
                $rawDate =
                    $cleanRow['tanggal']
                    ?? $cleanRow['date']
                    ?? null;

                /*
                 * Minimal harus punya:
                 * PIN atau NIK
                 *
                 * dan tanggal.
                 */
                if (
                    (empty($nikMesin) && empty($pinMesin))
                    || empty($rawDate)
                ) {
                    continue;
                }


                /*
                 * ==========================================================
                 * 4. PARSING TANGGAL
                 * ==========================================================
                 */
                if (is_numeric($rawDate)) {

                    $dateObj =
                        \PhpOffice\PhpSpreadsheet\Shared\Date
                            ::excelToDateTimeObject($rawDate);

                    $dateObj = Carbon::instance($dateObj);

                } else {

                    $dateStr = str_replace(
                        ['/', '.'],
                        '-',
                        (string) $rawDate
                    );

                    /*
                     * Format DD-MM-YYYY
                     */
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


                /*
                 * ==========================================================
                 * 5. TENTUKAN PERIODE DARI TANGGAL ASLI
                 * ==========================================================
                 *
                 * Kita tidak memaksa semua data masuk ke $this->period.
                 *
                 * Contoh:
                 *
                 * File berisi 2026-08-31
                 * → masuk periode 2026-08
                 *
                 * File berisi 2026-09-01
                 * → masuk periode 2026-09
                 */
                $targetDatabasePeriod =
                    $dateObj->format('Y-m');

                $dayNumber =
                    (int) $dateObj->format('j');

                $dateString =
                    $dateObj->format('Y-m-d');


                /*
                 * ==========================================================
                 * 6. BUFFER KEY
                 * ==========================================================
                 *
                 * Gunakan NIK jika ada.
                 * Kalau NIK kosong, gunakan PIN.
                 *
                 * Nama ikut dimasukkan untuk membantu mapping.
                 */
                $identityKey =
                    $nikMesin ?: $pinMesin;

                $mapKey =
                    $targetDatabasePeriod
                    . '|'
                    . $identityKey
                    . '|'
                    . strtolower($namaKaryawan);


                if (
                    !isset(
                        self::$globalAttendanceBuffer[
                            $targetDatabasePeriod
                        ]
                    )
                ) {

                    self::$globalAttendanceBuffer[
                        $targetDatabasePeriod
                    ] = [];
                }


                if (
                    !isset(
                        self::$globalAttendanceBuffer[
                            $targetDatabasePeriod
                        ][$mapKey]
                    )
                ) {

                    self::$globalAttendanceBuffer[
                        $targetDatabasePeriod
                    ][$mapKey] = [

                        'period' =>
                            $targetDatabasePeriod,

                        'nik_mesin' =>
                            $nikMesin,

                        'pin_mesin' =>
                            $pinMesin,

                        'nama_karyawan' =>
                            $namaKaryawan,

                        'daily' => [],
                    ];
                }


                /*
                 * ==========================================================
                 * 7. SIMPAN HASIL FINGERPRINT KE BUFFER
                 * ==========================================================
                 *
                 * Setiap baris fingerprint dianggap HADIR.
                 *
                 * Jika tanggal yang sama muncul beberapa kali,
                 * tetap hanya menjadi satu tanggal H.
                 */
                self::$globalAttendanceBuffer[
                    $targetDatabasePeriod
                ][$mapKey]['daily'][$dateString] = 'H';

            } catch (\Throwable $e) {

                Log::error(
                    "Gagal membaca baris absensi index {$index}: "
                    . $e->getMessage(),
                    [
                        'row' => $row->toArray(),
                    ]
                );

                continue;
            }
        }
    }


    /**
     * Menyimpan seluruh buffer ke attendance_records.
     *
     * PENTING:
     *
     * Method ini TIDAK:
     * - membuat payroll
     * - menghitung BPJS
     * - menghitung Alpha
     * - mengubah daily_attendance
     *
     * Semua data harian menjadi source of truth
     * di attendance_records.
     */
    public static function saveSummaryToDatabase($defaultPeriod)
    {
        Log::info(
            "=== MULAI MENYIMPAN BUFFER ABSENSI "
            . "KE attendance_records ==="
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


        /*
         * Statistik proses.
         */
        $inserted = 0;
        $updated = 0;
        $skippedLocked = 0;
        $unmatched = 0;


        foreach (
            self::$globalAttendanceBuffer
            as $periodKey => $employeesData
        ) {

            $targetPeriod =
                $periodKey ?? $defaultPeriod;

            Log::info(
                "Memproses periode {$targetPeriod} "
                . "("
                . count($employeesData)
                . " karyawan)"
            );


            foreach ($employeesData as $data) {

                $nikMesin =
                    trim(
                        (string) ($data['nik_mesin'] ?? '')
                    );

                $pinMesin =
                    trim(
                        (string) ($data['pin_mesin'] ?? '')
                    );

                $namaKaryawan =
                    trim(
                        (string) ($data['nama_karyawan'] ?? '')
                    );


                $employee = null;
                $contract = null;


                /*
                 * ==========================================================
                 * 1. CARI EMPLOYEE BERDASARKAN NIK / PIN
                 * ==========================================================
                 */
                if (
                    !empty($nikMesin)
                    || !empty($pinMesin)
                ) {

                    $contract =
                        EmployeeContract::query()
                            ->where('is_active', true)
                            ->where(function ($query) use (
                                $nikMesin,
                                $pinMesin
                            ) {

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
                            ->first();

                    if ($contract) {

                        $employee =
                            $contract->employee;
                    }
                }


                /*
                 * ==========================================================
                 * 2. FALLBACK CARI BERDASARKAN NAMA
                 * ==========================================================
                 */
                if (
                    !$employee
                    && !empty($namaKaryawan)
                ) {

                    $employee =
                        Employee::query()
                            ->whereRaw(
                                'LOWER(TRIM(full_name)) = ?',
                                [
                                    strtolower(
                                        $namaKaryawan
                                    )
                                ]
                            )
                            ->first();


                    /*
                     * Jika exact match tidak ketemu,
                     * coba partial match.
                     */
                    if (!$employee) {

                        $employee =
                            Employee::query()
                                ->whereRaw(
                                    'LOWER(TRIM(full_name)) LIKE ?',
                                    [
                                        '%'
                                        . strtolower(
                                            $namaKaryawan
                                        )
                                        . '%'
                                    ]
                                )
                                ->first();
                    }


                    if ($employee) {

                        $contract =
                            $employee->activeContract;
                    }
                }


                /*
                 * Tidak boleh memasukkan attendance
                 * kalau employee tidak berhasil ditemukan.
                 */
                if (!$employee || !$contract) {

                    $unmatched++;

                    Log::warning(
                        "Karyawan absensi tidak ditemukan.",
                        [
                            'nik' =>
                                $nikMesin,

                            'pin' =>
                                $pinMesin,

                            'nama' =>
                                $namaKaryawan,

                            'period' =>
                                $targetPeriod,
                        ]
                    );

                    continue;
                }


                /*
                 * ==========================================================
                 * 3. AUTO-BIND NIK / PIN
                 * ==========================================================
                 *
                 * Jika contract belum punya identitas mesin,
                 * kita isi dari file fingerprint.
                 */
                $updateData = [];


                if (
                    !empty($nikMesin)
                    && empty($contract->nik_fingerprint)
                ) {

                    $updateData[
                        'nik_fingerprint'
                    ] = $nikMesin;
                }


                if (
                    !empty($pinMesin)
                    && empty($contract->fingerprint_pin)
                ) {

                    $updateData[
                        'fingerprint_pin'
                    ] = $pinMesin;
                }


                if (!empty($updateData)) {

                    $contract->update(
                        $updateData
                    );

                    /*
                     * Refresh supaya relasi/data
                     * yang digunakan setelah update tetap terbaru.
                     */
                    $contract->refresh();
                }


                $empId =
                    $employee->id_employee;


                /*
                 * ==========================================================
                 * 4. CEK PAYROLL LOCK
                 * ==========================================================
                 *
                 * Aturan:
                 *
                 * cutoff = 26
                 *
                 * tanggal <= 26
                 * → tidak boleh diubah jika payroll locked.
                 *
                 * tanggal > 26
                 * → masih boleh masuk sebagai gantungan.
                 *
                 * CATATAN:
                 *
                 * Kita hanya menggunakan Payroll untuk
                 * mengetahui status LOCK.
                 *
                 * Attendance tetap disimpan di
                 * attendance_records.
                 */
                $payroll =
                    Payroll::query()
                        ->where(
                            'employee_id',
                            $empId
                        )
                        ->where(
                            'period_month',
                            $targetPeriod
                        )
                        ->first();


                $isLocked =
                    (bool) ($payroll?->is_locked ?? false);


                $cutoffDay =
                    (int) (
                        $payroll?->cutoff_day
                        ?? 26
                    );


                /*
                 * ==========================================================
                 * 5. SIMPAN SETIAP TANGGAL
                 * ==========================================================
                 */
                foreach (
                    $data['daily']
                    as $dateString => $status
                ) {

                    try {

                        $attendanceDate =
                            Carbon::parse(
                                $dateString
                            );

                        $dayNumber =
                            (int) $attendanceDate
                                ->format('j');


                        /*
                         * Pastikan tanggal memang berada
                         * di periode yang sedang diproses.
                         */
                        if (
                            $attendanceDate->format('Y-m')
                            !== $targetPeriod
                        ) {

                            Log::warning(
                                "Tanggal attendance "
                                . "di luar periode.",
                                [
                                    'employee_id' =>
                                        $empId,

                                    'date' =>
                                        $dateString,

                                    'period' =>
                                        $targetPeriod,
                                ]
                            );

                            continue;
                        }


                        /*
                         * ==================================================
                         * LOCK PROTECTION
                         * ==================================================
                         *
                         * Payroll locked:
                         *
                         * <= cutoff
                         * → SKIP
                         *
                         * > cutoff
                         * → BOLEH masuk/update.
                         */
                        if (
                            $isLocked
                            && $dayNumber <= $cutoffDay
                        ) {

                            $skippedLocked++;

                            Log::info(
                                "Attendance dikunci, "
                                . "tanggal dilewati.",
                                [
                                    'employee_id' =>
                                        $empId,

                                    'date' =>
                                        $dateString,

                                    'cutoff' =>
                                        $cutoffDay,
                                ]
                            );

                            continue;
                        }


                        /*
                         * ==================================================
                         * UPDATE / INSERT
                         * ==================================================
                         *
                         * Unique key:
                         *
                         * employee_id + attendance_date
                         *
                         * Jadi:
                         *
                         * Import ulang tanggal 9
                         * → update tanggal 9
                         *
                         * Tidak menyentuh tanggal 1-8.
                         */
                        $existingRecord =
                            AttendanceRecord::query()
                                ->where(
                                    'employee_id',
                                    $empId
                                )
                                ->whereDate(
                                    'attendance_date',
                                    $dateString
                                )
                                ->first();


                        AttendanceRecord::updateOrCreate(
                            [
                                'employee_id' =>
                                    $empId,

                                'attendance_date' =>
                                    $dateString,
                            ],
                            [
                                'status' =>
                                    $status ?: 'H',

                                'source' =>
                                    'fingerprint',
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
                                'employee_id' =>
                                    $empId,

                                'date' =>
                                    $dateString,

                                'error' =>
                                    $e->getMessage(),
                            ]
                        );

                        continue;
                    }
                }
            }
        }


        /*
         * ==============================================================
         * 6. BERSIHKAN BUFFER
         * ==============================================================
         */
        self::$globalAttendanceBuffer = [];


        Log::info(
            "=== SELESAI MENYIMPAN attendance_records ===",
            [
                'inserted' =>
                    $inserted,

                'updated' =>
                    $updated,

                'skipped_locked' =>
                    $skippedLocked,

                'unmatched' =>
                    $unmatched,
            ]
        );


        return [
            'inserted' =>
                $inserted,

            'updated' =>
                $updated,

            'skipped_locked' =>
                $skippedLocked,

            'unmatched' =>
                $unmatched,
        ];
    }
}
