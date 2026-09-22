<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillAttendanceRecords extends Command
{
    protected $signature = 'attendance:backfill';

    protected $description = 'Memindahkan data payrolls.daily_attendance ke attendance_records tanpa menghapus data lama';

    public function handle(): int
    {
        $this->info('==============================================');
        $this->info(' BACKFILL ATTENDANCE RECORDS');
        $this->info('==============================================');

        $totalPayroll = Payroll::whereNotNull('daily_attendance')->count();

        $this->info("Payroll yang memiliki daily_attendance: {$totalPayroll}");

        if ($totalPayroll === 0) {
            $this->warn('Tidak ada data daily_attendance yang perlu dipindahkan.');
            return self::SUCCESS;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        Payroll::whereNotNull('daily_attendance')
            ->orderBy('id_payroll')
            ->chunkById(100, function ($payrolls) use (
                &$inserted,
                &$updated,
                &$skipped
            ) {
                foreach ($payrolls as $payroll) {

                    $period = $payroll->period_month;
                    $employeeId = $payroll->employee_id;

                    if (!$period || !$employeeId) {
                        $skipped++;
                        continue;
                    }

                    /*
                     * daily_attendance sudah di-cast sebagai array
                     * pada model Payroll.
                     */
                    $dailyAttendance = $payroll->daily_attendance;

                    if (is_string($dailyAttendance)) {
                        $dailyAttendance = json_decode(
                            $dailyAttendance,
                            true
                        );
                    }

                    if (!is_array($dailyAttendance)) {
                        $skipped++;
                        continue;
                    }

                    $periodDate = Carbon::createFromFormat(
                        'Y-m-d',
                        $period . '-01'
                    );

                    $daysInMonth = $periodDate->daysInMonth;

                    foreach ($dailyAttendance as $dayKey => $status) {

                        /*
                         * Support dua format lama:
                         *
                         * Format 1:
                         * "1" => "H"
                         *
                         * Format 2:
                         * "2026-07-01" => "H"
                         */

                        $attendanceDate = null;

                        /*
                         * Jika key berupa tanggal lengkap.
                         */
                        if (
                            is_string($dayKey) &&
                            preg_match(
                                '/^\d{4}-\d{2}-\d{2}$/',
                                $dayKey
                            )
                        ) {
                            try {
                                $attendanceDate = Carbon::createFromFormat(
                                    'Y-m-d',
                                    $dayKey
                                );
                            } catch (\Throwable $e) {
                                $attendanceDate = null;
                            }
                        }

                        /*
                         * Jika key berupa nomor hari:
                         * 1, 2, 3 ... 31
                         */
                        if (!$attendanceDate && is_numeric($dayKey)) {

                            $day = (int) $dayKey;

                            if ($day >= 1 && $day <= $daysInMonth) {
                                $attendanceDate = $periodDate->copy()
                                    ->day($day);
                            }
                        }

                        if (!$attendanceDate) {
                            $skipped++;
                            continue;
                        }

                        /*
                         * Pastikan tanggal benar-benar berada
                         * pada periode payroll.
                         */
                        if (
                            $attendanceDate->format('Y-m') !== $period
                        ) {
                            $skipped++;
                            continue;
                        }

                        $normalizedStatus = strtoupper(
                            trim((string) $status)
                        );

                        /*
                         * Status yang memang dikenal oleh sistem.
                         *
                         * '-' = tidak ada absensi / hari libur.
                         * Kita tidak perlu menyimpan '-' sebagai record.
                         */
                        $allowedStatuses = [
                            'H',
                            'H0.5',
                            'A',
                            'I',
                            'SKD',
                            'C',
                            'CM',
                        ];

                        if (
                            $normalizedStatus === '' ||
                            $normalizedStatus === '-'
                        ) {
                            continue;
                        }

                        if (
                            !in_array(
                                $normalizedStatus,
                                $allowedStatuses,
                                true
                            )
                        ) {
                            $skipped++;
                            continue;
                        }

                        /*
                         * INSERT atau UPDATE.
                         *
                         * Karena ada unique:
                         * employee_id + attendance_date
                         *
                         * maka record tidak akan dobel.
                         */
                        $record = AttendanceRecord::updateOrCreate(
                            [
                                'employee_id' => $employeeId,
                                'attendance_date' => $attendanceDate->format(
                                    'Y-m-d'
                                ),
                            ],
                            [
                                'status' => $normalizedStatus,
                                'source' => 'legacy_payroll',
                            ]
                        );

                        if ($record->wasRecentlyCreated) {
                            $inserted++;
                        } else {
                            $updated++;
                        }
                    }

                    $this->line(
                        "Payroll #{$payroll->id_payroll} | " .
                        "Employee #{$employeeId} | " .
                        "Period {$period} → selesai"
                    );
                }
            }, 'id_payroll');

        /*
         * Ringkasan.
         */
        $this->newLine();

        $this->info('==============================================');
        $this->info(' BACKFILL SELESAI');
        $this->info('==============================================');

        $this->info("Record baru    : {$inserted}");
        $this->info("Record update  : {$updated}");
        $this->warn("Record dilewati: {$skipped}");

        $this->newLine();

        $totalRecords = AttendanceRecord::count();

        $this->info(
            "Total attendance_records sekarang: {$totalRecords}"
        );

        Log::info('Attendance backfill selesai', [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_records' => $totalRecords,
        ]);

        return self::SUCCESS;
    }
}