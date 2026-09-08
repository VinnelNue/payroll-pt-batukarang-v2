<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $period;
    protected static $globalAttendanceBuffer = [];

    public function __construct($period)
    {
        $this->period = $period ?? date('Y-m');
    }

    public function collection(Collection $rows)
    {
        Log::info("=== MEMBACA FILE EXCEL/CSV DALAM ZIP (Target Periode: {$this->period}) ===");

        foreach ($rows as $index => $row) {
            $cleanRow = [];
            foreach ($row as $key => $val) {
                $cleanKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key)));
                $cleanRow[$cleanKey] = is_string($val) ? trim($val) : $val;
            }

            $pinMesin = trim((string) ($cleanRow['pin'] ?? ''));
            $nikMesin = strtoupper(trim((string) ($cleanRow['nik'] ?? '')));
            $namaKaryawan = trim((string) (
                $cleanRow['namakaryawan'] ?? 
                $cleanRow['nama_karyawan'] ?? 
                $cleanRow['nama'] ?? ''
            ));

            $rawDate = $cleanRow['tanggal'] ?? $cleanRow['date'] ?? null;

            if ((empty($nikMesin) && empty($pinMesin)) || empty($rawDate)) {
                continue;
            }

            try {
                if (is_numeric($rawDate)) {
                    $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate);
                } else {
                    $dateStr = str_replace(['/', '.'], '-', (string)$rawDate);
                    if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateStr)) {
                        $dateObj = Carbon::createFromFormat('d-m-Y', $dateStr);
                    } else {
                        $dateObj = Carbon::parse($dateStr);
                    }
                }

                // Ambil tahun-bulan asli dari baris Excel agar tidak salah kamar
                $targetDatabasePeriod = $dateObj->format('Y-m');
                $dayNumber = (int) $dateObj->format('j');
                
                $mapKey = $targetDatabasePeriod . '|' . ($nikMesin ?: $pinMesin) . '|' . strtolower($namaKaryawan);

                if (!isset(self::$globalAttendanceBuffer[$targetDatabasePeriod])) {
                    self::$globalAttendanceBuffer[$targetDatabasePeriod] = [];
                }

                if (!isset(self::$globalAttendanceBuffer[$targetDatabasePeriod][$mapKey])) {
                    self::$globalAttendanceBuffer[$targetDatabasePeriod][$mapKey] = [
                        'period'        => $targetDatabasePeriod,
                        'nik_mesin'     => $nikMesin,
                        'pin_mesin'     => $pinMesin,
                        'nama_karyawan' => $namaKaryawan,
                        'daily'         => [],
                    ];
                }

                // Masukkan status hadir ke memori (meskipun baris ganda, akan ditimpa dengan 'H' di tanggal yang sama)
                self::$globalAttendanceBuffer[$targetDatabasePeriod][$mapKey]['daily'][$dayNumber] = 'H';

            } catch (\Exception $e) {
                Log::error("Gagal parsing tanggal: " . $e->getMessage());
            }
        }
    }

    public static function saveSummaryToDatabase($defaultPeriod)
    {
        Log::info("=== MULAI MENYIMPAN SEMUA BUFFER ABSENSI KE DATABASE ===");

        if (empty(self::$globalAttendanceBuffer)) {
            Log::warning("Buffer absensi kosong, tidak ada data yang disimpan.");
            return;
        }

        foreach (self::$globalAttendanceBuffer as $periodKey => $employeesData) {
            $targetPeriod = $periodKey ?? $defaultPeriod;
            $daysInMonth = Carbon::parse($targetPeriod . '-01')->daysInMonth;
            
            Log::info("Memproses penyimpanan untuk Periode: {$targetPeriod} (" . count($employeesData) . " karyawan)");

            foreach ($employeesData as $data) {
                $nikMesin = $data['nik_mesin'];
                $pinMesin = $data['pin_mesin'];
                $namaKaryawan = trim($data['nama_karyawan']);

                $employee = null;
                $contract = null;

                // 1. Cari berdasarkan NIK atau PIN Mesin
                if (!empty($nikMesin) || !empty($pinMesin)) {
                    $contract = EmployeeContract::where('is_active', true)
                        ->where(function ($query) use ($nikMesin, $pinMesin) {
                            if (!empty($nikMesin)) $query->orWhere('nik_fingerprint', $nikMesin);
                            if (!empty($pinMesin)) $query->orWhere('fingerprint_pin', $pinMesin);
                        })
                        ->first();

                    if ($contract) {
                        $employee = $contract->employee;
                    }
                }

                // 2. Jika belum ketemu, cari berdasarkan Nama Karyawan
                if (!$employee && !empty($namaKaryawan)) {
                    $employee = Employee::whereRaw('LOWER(TRIM(full_name)) = ?', [strtolower($namaKaryawan)])->first();
                    if (!$employee) {
                        $employee = Employee::whereRaw('LOWER(TRIM(full_name)) LIKE ?', ['%' . strtolower($namaKaryawan) . '%'])->first();
                    }
                    if ($employee) {
                        $contract = $employee->activeContract;
                    }
                }

                if (!$employee || !$contract) {
                    continue;
                }

                // 3. Auto-bind NIK & PIN ke contract jika kosong
                $updateData = [];
                if (!empty($nikMesin) && empty($contract->nik_fingerprint)) $updateData['nik_fingerprint'] = $nikMesin;
                if (!empty($pinMesin) && empty($contract->fingerprint_pin)) $updateData['fingerprint_pin'] = $pinMesin;
                if (!empty($updateData)) $contract->update($updateData);

                $empId = $employee->id_employee;
                $basicSalary = (float) $contract->basic_salary;
                $allowance = (float) $contract->allowance;

                // 4. Ambil atau Buat Payroll (Acuan 26 Hari Kerja Flat)
                $payroll = Payroll::firstOrCreate(
                    [
                        'employee_id'  => $empId,
                        'period_month' => $targetPeriod,
                    ],
                    [
                        'work_days'        => 26,
                        'basic_salary'     => $basicSalary,
                        'allowance'        => $allowance,
                        'gross_salary'     => $basicSalary + $allowance,
                        'net_salary'       => $basicSalary + $allowance,
                        'status'           => 'Draft',
                        'daily_attendance' => json_encode([]),
                    ]
                );

                // 5. Smart Merge (Pertahankan data lama / manual yang sudah diubah HRD)
                $existingDaily = [];
                if ($payroll->daily_attendance) {
                    $decoded = is_array($payroll->daily_attendance) 
                        ? $payroll->daily_attendance 
                        : json_decode($payroll->daily_attendance, true);
                    
                    if (is_array($decoded)) {
                        $existingDaily = $decoded;
                    }
                }

                // Timpa data hasil scan mesin H hari ini
                foreach ($data['daily'] as $dayNum => $status) {
                    $existingDaily[(string)$dayNum] = $status;
                }

                // ==========================================
                // FITUR PROTEKSI SHIFT (BYPASS AUTO-ALPHA)
                // ==========================================
                // Cek apakah jabatan mengandung kata 'satpam' atau 'security'
                $jabatan = strtolower($contract->job_title ?? '');
                $isShiftWorker = str_contains($jabatan, 'satpam') || str_contains($jabatan, 'security');

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayStr = (string)$d;
                    $currentStatus = $existingDaily[$dayStr] ?? '-';

                    $currentDate = Carbon::parse($targetPeriod . '-' . sprintf('%02d', $d));
                    $isSunday = $currentDate->isSunday();

                    if ($isSunday) {
                        // Jika hari Minggu, tidak boleh Alpha
                        if ($currentStatus === 'A') {
                            $currentStatus = '-';
                        }
                    } elseif ($currentStatus === '-' || empty($currentStatus)) {
                        // Jika hari biasa dan kosong, hanya eksekusi Alpha JIKA BUKAN anak shift (Satpam)
                        if (!$isShiftWorker) {
                            $currentStatus = 'A';
                        }
                    }

                    $existingDaily[$dayStr] = $currentStatus;
                }

                // 7. Hitung ulang total Unpaid Leave (Alpha 'A' atau setengah hari 'H0.5')
                $unpaidCount = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $status = $existingDaily[(string)$d] ?? '';
                    if ($status === 'A') $unpaidCount += 1;
                    elseif ($status === 'H0.5') $unpaidCount += 0.5;
                }

                // 8. Simpan ke database
                $payroll->update([
                    'daily_attendance' => json_encode($existingDaily),
                    'unpaid_leave'     => $unpaidCount,
                    'updated_at'       => now(),
                ]);
            }
        }

        // Kosongkan memori buffer setelah eksekusi selesai
        self::$globalAttendanceBuffer = [];
        Log::info("=== SELESAI SIMPAN SEMUA ABSENSI KE DATABASE ===");
    }
}