<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $period;
    protected static $employeeAttendanceData = [];

    public function __construct($period)
    {
        $this->period = $period;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $cleanRow = [];
            foreach ($row as $key => $val) {
                $cleanKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key)));
                $cleanRow[$cleanKey] = is_string($val) ? trim($val) : $val;
            }

            $nik = strtoupper(trim((string) ($cleanRow['nik'] ?? $cleanRow['nikktp'] ?? $cleanRow['id'] ?? '')));
            if (empty($nik)) continue;

            $rawDate = $cleanRow['tanggal'] ?? $cleanRow['date'] ?? null;
            if (!$rawDate) continue;

            try {
                $dateStr = str_replace('/', '-', (string)$rawDate);
                if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateStr)) {
                    $dateObj = Carbon::createFromFormat('d-m-Y', $dateStr);
                } else {
                    $dateObj = Carbon::parse($dateStr);
                }

                $dayNumber = (int) $dateObj->format('j');

                if (!isset(self::$employeeAttendanceData[$nik])) {
                    self::$employeeAttendanceData[$nik] = [
                        'daily' => [],
                        'overtime_hours' => 0
                    ];
                }

                self::$employeeAttendanceData[$nik]['daily'][$dayNumber] = 'H';
            } catch (\Exception $e) {
                Log::error("Import Exception parsing tanggal ({$rawDate}): " . $e->getMessage());
                continue;
            }
        }
    }

    public static function saveSummaryToDatabase($period)
    {
        $daysInMonth = Carbon::parse($period . '-01')->daysInMonth;
        Log::info(">>> MULAI SAVE TO DB. Periode: {$period}, Total NIK ZIP: " . count(self::$employeeAttendanceData));

        foreach (self::$employeeAttendanceData as $nik => $data) {
            try {
                // 1. Cari Karyawan
                $employee = Employee::whereRaw('LOWER(TRIM(nik_ktp)) = ?', [strtolower($nik)])
                    ->orWhereRaw('LOWER(TRIM(no_kk)) = ?', [strtolower($nik)])
                    ->first();

                if (!$employee) {
                    Log::warning(">>> Employee NIK {$nik} TIDAK DITEMUKAN di DB!");
                    continue;
                }

                // Ambil ID Karyawan secara presisi
                $empId = $employee->id_employee ?? $employee->id;
                Log::info(">>> Ketemu Employee ID: {$empId} ({$employee->full_name})");

                // 2. Ambil Payroll Lama (Jika Ada)
                $existingPayroll = Payroll::where('employee_id', $empId)
                    ->where('period_month', $period)
                    ->first();

                $existingDaily = [];
                if ($existingPayroll && $existingPayroll->daily_attendance) {
                    $existingDaily = is_array($existingPayroll->daily_attendance)
                        ? $existingPayroll->daily_attendance
                        : (json_decode($existingPayroll->daily_attendance, true) ?? []);
                }

                // 3. Timpa Log Baru
                foreach ($data['daily'] as $dayNum => $status) {
                    $existingDaily[(string)$dayNum] = $status;
                }

                // 4. Hitung Unpaid
                $unpaidCount = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $status = $existingDaily[(string)$d] ?? $existingDaily[$d] ?? '';
                    if ($status === 'A') $unpaidCount += 1;
                    elseif ($status === 'H0.5') $unpaidCount += 0.5;
                }

                // 5. Eksekusi SIMPAN KE DB dengan Query Builder Langsung (Anti Failure)
                $contract = $employee->activeContract;
                $basicSalary = $contract->basic_salary ?? 0;
                $allowance = $contract->allowance ?? 0;

                $payrollData = [
                    'employee_id'      => $empId,
                    'period_month'     => $period,
                    'daily_attendance' => json_encode($existingDaily),
                    'unpaid_leave'     => $unpaidCount,
                    'overtime_hours'   => $data['overtime_hours'] ?? 0,
                    'work_days'        => $existingPayroll->work_days ?? 26,
                    'basic_salary'     => $existingPayroll->basic_salary ?? $basicSalary,
                    'allowance'        => $existingPayroll->allowance ?? $allowance,
                    'overtime_pay'     => $existingPayroll->overtime_pay ?? 0,
                    'gross_salary'     => $existingPayroll->gross_salary ?? ($basicSalary + $allowance),
                    'net_salary'       => $existingPayroll->net_salary ?? ($basicSalary + $allowance),
                    'status'           => $existingPayroll->status ?? 'Draft',
                    'updated_at'       => now(),
                ];

                if ($existingPayroll) {
                    DB::table('payrolls')->where('id_payroll', $existingPayroll->id_payroll)->update($payrollData);
                    Log::info(">>> UPDATE BERHASIL untuk Employee ID {$empId}");
                } else {
                    $payrollData['created_at'] = now();
                    DB::table('payrolls')->insert($payrollData);
                    Log::info(">>> INSERT BERHASIL untuk Employee ID {$empId}");
                }

            } catch (\Exception $e) {
                Log::error(">>> GAGAL SIMPAN DB Karyawan NIK {$nik}: " . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }

        self::$employeeAttendanceData = [];
    }
}