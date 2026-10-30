<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\ContractHistoryLocal;
use App\Models\Employee;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected string $period;

    /**
     * Satu proses import dapat membaca beberapa file (misalnya ZIP).
     * Buffer diproses sekaligus agar query database tidak dilakukan
     * berulang kali untuk setiap baris/tanggal.
     */
    protected static array $globalAttendanceBuffer = [];

    private const MANUAL_SOURCES = [
        'manual',
        'manual_excel',
    ];

    public function __construct($period)
    {
        $this->period = $period ?? date('Y-m');
    }

    public static function resetBuffer(): void
    {
        self::$globalAttendanceBuffer = [];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                $cleanRow = [];

                foreach ($row as $key => $value) {
                    $cleanKey = strtolower(trim(preg_replace(
                        '/[^a-zA-Z0-9_]/',
                        '',
                        (string) $key
                    )));

                    $cleanRow[$cleanKey] = is_string($value)
                        ? trim($value)
                        : $value;
                }

                $pin = trim((string) ($cleanRow['pin'] ?? ''));
                $nik = strtoupper(trim((string) ($cleanRow['nik'] ?? '')));
                $name = trim((string) (
                    $cleanRow['namakaryawan']
                    ?? $cleanRow['nama_karyawan']
                    ?? $cleanRow['nama']
                    ?? ''
                ));
                $rawDate = $cleanRow['tanggal'] ?? $cleanRow['date'] ?? null;

                if ((($nik === '') && ($pin === '')) || $rawDate === null || $rawDate === '') {
                    continue;
                }

                $date = $this->parseAttendanceDate($rawDate);
                if (!$date) {
                    continue;
                }

                $targetPeriod = $date->format('Y-m');
                $dateString = $date->format('Y-m-d');
                $identity = $nik !== '' ? $nik : $pin;
                $mapKey = $targetPeriod . '|' . $identity . '|' . mb_strtolower($name);

                self::$globalAttendanceBuffer[$targetPeriod] ??= [];
                self::$globalAttendanceBuffer[$targetPeriod][$mapKey] ??= [
                    'period' => $targetPeriod,
                    'nik_mesin' => $nik,
                    'pin_mesin' => $pin,
                    'nama_karyawan' => $name,
                    'daily' => [],
                ];

                // Satu atau banyak log di tanggal yang sama tetap menjadi satu status H.
                self::$globalAttendanceBuffer[$targetPeriod][$mapKey]['daily'][$dateString] = 'H';
            } catch (Throwable $exception) {
                Log::warning('Baris fingerprint gagal dibaca.', [
                    'row_index' => $index,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function parseAttendanceDate(mixed $rawDate): ?Carbon
    {
        try {
            if (is_numeric($rawDate)) {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject((float) $rawDate)
                )->startOfDay();
            }

            $dateString = str_replace(['/', '.'], '-', trim((string) $rawDate));

            if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateString)) {
                return Carbon::createFromFormat('!d-m-Y', $dateString);
            }

            return Carbon::parse($dateString)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves fingerprint identities, lock data and existing attendance in
     * batches, then writes attendance_records using chunked upserts.
     */
    public static function saveSummaryToDatabase($defaultPeriod): array
    {
        $summary = [
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_locked' => 0,
            'skipped_manual' => 0,
            'unmatched' => 0,
        ];

        if (empty(self::$globalAttendanceBuffer)) {
            return $summary;
        }

        try {
            foreach (self::$globalAttendanceBuffer as $periodKey => $employeesData) {
                $targetPeriod = $periodKey ?: $defaultPeriod;
                $periodStart = Carbon::createFromFormat('!Y-m-d', $targetPeriod . '-01');
                $periodEnd = $periodStart->copy()->endOfMonth();
                $periodStartString = $periodStart->toDateString();
                $periodEndString = $periodEnd->toDateString();

                $niks = [];
                $pins = [];
                $names = [];

                foreach ($employeesData as $data) {
                    $nik = trim((string) ($data['nik_mesin'] ?? ''));
                    $pin = trim((string) ($data['pin_mesin'] ?? ''));
                    $name = trim((string) ($data['nama_karyawan'] ?? ''));

                    if ($nik !== '') {
                        $niks[$nik] = $nik;
                    }
                    if ($pin !== '') {
                        $pins[$pin] = $pin;
                    }
                    if ($name !== '') {
                        $names[mb_strtolower($name)] = $name;
                    }
                }

                // Satu query untuk seluruh candidate contract history periode ini.
                $historyQuery = ContractHistoryLocal::query()
                    ->with('contract.employee')
                    ->where(function ($query) use ($niks, $pins): void {
                        if ($niks !== []) {
                            $query->whereIn('nik_fingerprint', array_values($niks));
                        }
                        if ($pins !== []) {
                            if ($niks !== []) {
                                $query->orWhereIn('fingerprint_pin', array_values($pins));
                            } else {
                                $query->whereIn('fingerprint_pin', array_values($pins));
                            }
                        }
                    })
                    ->orderByDesc('start_date');

                $identityHistories = ($niks !== [] || $pins !== [])
                    ? $historyQuery->get()
                    : collect();

                $historiesByNik = [];
                $historiesByPin = [];

                foreach ($identityHistories as $history) {
                    $historyNik = trim((string) ($history->nik_fingerprint ?? ''));
                    $historyPin = trim((string) ($history->fingerprint_pin ?? ''));

                    if ($historyNik !== '') {
                        $historiesByNik[$historyNik][$history->id_contract_history] = $history;
                    }
                    if ($historyPin !== '') {
                        $historiesByPin[$historyPin][$history->id_contract_history] = $history;
                    }
                }

                // Fallback exact-name candidates dimuat sekaligus, bukan per karyawan.
                $fallbackEmployees = collect();
                foreach (array_chunk(array_values($names), 50) as $nameChunk) {
                    $chunkNames = array_map(
                        static fn (string $name): string => mb_strtolower(trim($name)),
                        $nameChunk
                    );

                    $found = Employee::query()
                        ->with('contract.histories')
                        ->where(function ($query) use ($chunkNames): void {
                            foreach ($chunkNames as $name) {
                                $query->orWhereRaw('LOWER(TRIM(full_name)) = ?', [$name]);
                            }
                        })
                        ->get();

                    $fallbackEmployees = $fallbackEmployees->concat($found);
                }

                $fallbackEmployeesByName = $fallbackEmployees->keyBy(
                    static fn (Employee $employee): string => mb_strtolower(trim((string) $employee->full_name))
                );

                $resolvedRows = [];
                $resolvedEmployeeIds = [];
                $historyUpdates = [];
                $fallbackCache = [];

                foreach ($employeesData as $data) {
                    $nik = trim((string) ($data['nik_mesin'] ?? ''));
                    $pin = trim((string) ($data['pin_mesin'] ?? ''));
                    $name = trim((string) ($data['nama_karyawan'] ?? ''));
                    $normalizedName = mb_strtolower($name);
                    $candidateHistories = [];

                    if ($nik !== '' && !empty($historiesByNik[$nik])) {
                        $candidateHistories += $historiesByNik[$nik];
                    }
                    if ($pin !== '' && !empty($historiesByPin[$pin])) {
                        $candidateHistories += $historiesByPin[$pin];
                    }

                    $employeeFallback = null;
                    if ($candidateHistories === [] && $normalizedName !== '') {
                        $employeeFallback = $fallbackEmployeesByName->get($normalizedName);

                        // Pertahankan fallback substring lama hanya untuk nama yang tidak exact-match.
                        if (!$employeeFallback && !array_key_exists($normalizedName, $fallbackCache)) {
                            $employeeFallback = Employee::query()
                                ->with('contract.histories')
                                ->whereRaw('LOWER(TRIM(full_name)) LIKE ?', ['%' . $normalizedName . '%'])
                                ->orderByRaw('CHAR_LENGTH(full_name) ASC')
                                ->first();
                            $fallbackCache[$normalizedName] = $employeeFallback;
                        } elseif (!$employeeFallback) {
                            $employeeFallback = $fallbackCache[$normalizedName];
                        }
                    }

                    if ($candidateHistories !== []) {
                        uasort($candidateHistories, static function ($left, $right): int {
                            return strcmp(
                                (string) ($right->start_date?->format('Y-m-d') ?? ''),
                                (string) ($left->start_date?->format('Y-m-d') ?? '')
                            );
                        });
                    }

                    $groupMatched = false;

                    foreach (($data['daily'] ?? []) as $dateString => $status) {
                        if (!is_string($dateString) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                            continue;
                        }

                        if (substr($dateString, 0, 7) !== $targetPeriod) {
                            continue;
                        }

                        $history = self::findHistoryForDate($candidateHistories, $dateString);
                        $employee = $history?->contract?->employee;

                        // Bila NIK/PIN belum terikat pada history periode tersebut, gunakan fallback nama.
                        if ((!$history || !$employee) && $employeeFallback) {
                            $fallbackHistories = $employeeFallback->contract?->histories ?? collect();
                            $history = self::findHistoryForDate($fallbackHistories, $dateString);
                            $employee = $history ? $employeeFallback : null;
                        }

                        if (!$history || !$employee) {
                            continue;
                        }

                        $groupMatched = true;
                        $employeeId = (int) $employee->id_employee;
                        $resolvedEmployeeIds[$employeeId] = $employeeId;

                        // Auto-bind identifier tetap dipertahankan, tetapi hanya satu kali per history.
                        $historyId = (int) $history->id_contract_history;
                        $historyUpdates[$historyId] ??= [
                            'model' => $history,
                            'changes' => [],
                        ];

                        if ($nik !== '' && empty($history->nik_fingerprint)) {
                            $historyUpdates[$historyId]['changes']['nik_fingerprint'] = $nik;
                            $history->nik_fingerprint = $nik;
                        }
                        if ($pin !== '' && empty($history->fingerprint_pin)) {
                            $historyUpdates[$historyId]['changes']['fingerprint_pin'] = $pin;
                            $history->fingerprint_pin = $pin;
                        }

                        $resolvedRows[] = [
                            'employee_id' => $employeeId,
                            'attendance_date' => $dateString,
                            'status' => $status ?: 'H',
                            'day' => (int) substr($dateString, 8, 2),
                        ];
                    }

                    if (!$groupMatched) {
                        $summary['unmatched']++;
                        Log::warning('Karyawan fingerprint / contract history tidak ditemukan untuk periode absensi.', [
                            'nik' => $nik,
                            'pin' => $pin,
                            'nama' => $name,
                            'period' => $targetPeriod,
                        ]);
                    }
                }

                if ($resolvedRows === []) {
                    foreach ($historyUpdates as $update) {
                        if ($update['changes'] !== []) {
                            $update['model']->update($update['changes']);
                        }
                    }
                    continue;
                }

                // Payroll lock diambil sekali untuk seluruh employee periode ini.
                $payrollRows = Payroll::query()
                    ->where('period_month', $targetPeriod)
                    ->whereIn('employee_id', array_values($resolvedEmployeeIds))
                    ->orderBy('id_payroll')
                    ->get(['id_payroll', 'employee_id', 'is_locked', 'cutoff_day']);

                $payrollByEmployee = [];
                foreach ($payrollRows as $payroll) {
                    $employeeId = (int) $payroll->employee_id;
                    $payrollByEmployee[$employeeId] ??= $payroll;
                }

                // Attendance existing diambil sekali per periode, bukan per hari.
                $existingRecords = AttendanceRecord::query()
                    ->whereIn('employee_id', array_values($resolvedEmployeeIds))
                    ->whereBetween('attendance_date', [$periodStartString, $periodEndString])
                    ->get(['employee_id', 'attendance_date', 'status', 'source'])
                    ->keyBy(static function (AttendanceRecord $record): string {
                        $date = $record->attendance_date instanceof Carbon
                            ? $record->attendance_date->format('Y-m-d')
                            : Carbon::parse($record->attendance_date)->format('Y-m-d');
                        return (int) $record->employee_id . '|' . $date;
                    });

                $upserts = [];
                $now = now();

                foreach ($resolvedRows as $row) {
                    $employeeId = $row['employee_id'];
                    $payroll = $payrollByEmployee[$employeeId] ?? null;
                    $isLocked = (bool) ($payroll?->is_locked ?? false);
                    $cutoffDay = max(1, min(31, (int) ($payroll?->cutoff_day ?? 26)));

                    if ($isLocked && $row['day'] < $cutoffDay) {
                        $summary['skipped_locked']++;
                        continue;
                    }

                    $key = $employeeId . '|' . $row['attendance_date'];
                    $existing = $existingRecords->get($key);
                    $existingSource = strtolower(trim((string) ($existing?->source ?? '')));

                    // Catatan manual dari UI / Excel selalu menang terhadap fingerprint.
                    if ($existing && in_array($existingSource, self::MANUAL_SOURCES, true)) {
                        $summary['skipped_manual']++;
                        continue;
                    }

                    $status = strtoupper(trim((string) $row['status']));
                    $currentStatus = strtoupper(trim((string) ($existing?->status ?? '')));

                    if ($existing && $currentStatus === $status) {
                        $summary['unchanged']++;
                        continue;
                    }

                    if ($existing) {
                        $summary['updated']++;
                    } else {
                        $summary['inserted']++;
                    }

                    $upserts[] = [
                        'employee_id' => $employeeId,
                        'attendance_date' => $row['attendance_date'],
                        'status' => $status,
                        'source' => 'fingerprint',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::transaction(function () use ($upserts, $historyUpdates): void {
                    // Simpan auto-binding NIK/PIN satu kali per contract history.
                    foreach ($historyUpdates as $update) {
                        if ($update['changes'] !== []) {
                            $update['model']->update($update['changes']);
                        }
                    }

                    foreach (array_chunk($upserts, 500) as $chunk) {
                        AttendanceRecord::query()->upsert(
                            $chunk,
                            ['employee_id', 'attendance_date'],
                            ['status', 'source', 'updated_at']
                        );
                    }
                });
            }
        } finally {
            // Mencegah buffer tertinggal pada worker PHP yang hidup lebih dari satu request.
            self::$globalAttendanceBuffer = [];
        }

        Log::info('Import fingerprint selesai.', $summary);

        return $summary;
    }

    /**
     * Pilih contract history yang efektif pada tanggal absensi.
     * end_date tetap exclusive, mengikuti perilaku sistem sebelumnya.
     */
    private static function findHistoryForDate(iterable $histories, string $dateString): ?ContractHistoryLocal
    {
        foreach ($histories as $history) {
            $startDate = $history->start_date instanceof Carbon
                ? $history->start_date->format('Y-m-d')
                : (string) $history->start_date;
            $endDate = $history->end_date instanceof Carbon
                ? $history->end_date->format('Y-m-d')
                : ($history->end_date ? (string) $history->end_date : null);

            if ($startDate !== '' && $startDate <= $dateString && ($endDate === null || $endDate === '' || $endDate > $dateString)) {
                return $history;
            }
        }

        return null;
    }
}
