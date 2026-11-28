<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\ContractHistoryLocal;
use App\Models\Employee;
use App\Models\FingerprintLog;
use App\Models\Holiday;
use App\Models\Payroll;
use App\Support\ContractHistoryResolver;
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

    protected ?string $sourceFile;

    /**

     * Satu proses import dapat membaca beberapa file (misalnya ZIP).

     * Buffer diproses sekaligus agar query database tidak dilakukan

     * berulang kali untuk setiap baris/tanggal.

     */

    protected static array $globalAttendanceBuffer = [];

    private const MANUAL_SOURCES = [

        'manual',

        'manual_excel',

        'maternity',

    ];

    public function __construct($period, ?string $sourceFile = null)

    {

        $this->period = $period ?? date('Y-m');

        $this->sourceFile = $sourceFile;

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

                $rawTime = $cleanRow['jam'] ?? $cleanRow['time'] ?? $cleanRow['waktu'] ?? null;

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

                    'logs' => [],

                ];

                // Satu tanggal dianggap hadir hanya jika benar-benar ada jam scan valid.
                // Jika beberapa baris untuk tanggal yang sama, satu scan valid tetap menang.
                $scanAt = $this->parseAttendanceTimestamp($rawDate, $rawTime);

                if ($scanAt) {
                    self::$globalAttendanceBuffer[$targetPeriod][$mapKey]['daily'][$dateString] = 'H';

                    self::$globalAttendanceBuffer[$targetPeriod][$mapKey]['logs'][] = [
                        'attendance_date' => $dateString,
                        'pin_mesin' => $pin,
                        'nik_mesin' => $nik,
                        'scan_at' => $scanAt->format('Y-m-d H:i:s'),
                        'device_sn' => $this->nullableString($cleanRow['sn_mesin'] ?? $cleanRow['snmesin'] ?? null, 100),
                        'device_name' => $this->nullableString($cleanRow['nama_mesin'] ?? $cleanRow['namamesin'] ?? null, 150),
                        'verification' => $this->nullableString($cleanRow['verifikasi'] ?? $cleanRow['verification'] ?? null, 50),
                        'mode' => $this->nullableString($cleanRow['mode'] ?? null, 80),
                        'mode_update' => $this->nullableString($cleanRow['mode_update'] ?? $cleanRow['modeupdate'] ?? null, 80),
                        'branch' => $this->nullableString($cleanRow['cabang'] ?? $cleanRow['branch'] ?? null, 100),
                        'department' => $this->nullableString($cleanRow['departemen'] ?? $cleanRow['department'] ?? null, 100),
                        'job_position' => $this->nullableString($cleanRow['jabatan'] ?? $cleanRow['job_title'] ?? null, 100),
                        'source_file' => $this->nullableString($this->sourceFile, 255),
                    ];
                } elseif (!isset(self::$globalAttendanceBuffer[$targetPeriod][$mapKey]['daily'][$dateString])) {
                    // Baris tanggal ada, tetapi tidak mempunyai jam scan valid.
                    self::$globalAttendanceBuffer[$targetPeriod][$mapKey]['daily'][$dateString] = 'A';
                }

            } catch (Throwable $exception) {

                Log::warning('Baris fingerprint gagal dibaca.', [

                    'row_index' => $index,

                    'error' => $exception->getMessage(),

                ]);

            }

        }

    }

    private function nullableString(mixed $value, int $maxLength): ?string

    {

        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);

    }

    private function parseAttendanceTimestamp(mixed $rawDate, mixed $rawTime): ?Carbon

    {

        $date = $this->parseAttendanceDate($rawDate);

        if (!$date || $rawTime === null) {

            return null;

        }

        try {

            if ($rawTime instanceof \DateTimeInterface) {

                return $date->copy()->setTime(

                    (int) $rawTime->format('H'),

                    (int) $rawTime->format('i'),

                    (int) $rawTime->format('s')

                );

            }

            if (is_numeric($rawTime)) {

                $numericTime = (float) $rawTime;

                $excelDateTime = ExcelDate::excelToDateTimeObject($numericTime);

                return $date->copy()->setTime(

                    (int) $excelDateTime->format('H'),

                    (int) $excelDateTime->format('i'),

                    (int) $excelDateTime->format('s')

                );

            }

            $timeString = trim((string) $rawTime);

            if ($timeString === '') {

                return null;

            }

            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeString, $matches)) {

                $hour = (int) $matches[1];

                $minute = (int) $matches[2];

                $second = (int) ($matches[3] ?? 0);

                if ($hour > 23 || $minute > 59 || $second > 59) {

                    return null;

                }

                return $date->copy()->setTime($hour, $minute, $second);

            }

            // Fallback untuk format waktu teks yang tidak standar (mis. 7:10 PM).

            $parsed = Carbon::parse($timeString);

            return $date->copy()->setTime(

                (int) $parsed->format('H'),

                (int) $parsed->format('i'),

                (int) $parsed->format('s')

            );

        } catch (Throwable) {

            return null;

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

     * batches, then writes raw punch events and attendance_records using chunked upserts.

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

            'raw_logs_processed' => 0,

            'raw_logs_unmatched' => 0,
            'auto_alpha' => 0,

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

                $resolvedRawLogs = [];

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

                        $employeeIdForLog = $employee ? (int) $employee->id_employee : null;

                        $historyIdForLog = $history ? (int) $history->id_contract_history : null;

                        foreach (($data['logs'] ?? []) as $rawLog) {

                            if (($rawLog['attendance_date'] ?? null) !== $dateString) {

                                continue;

                            }

                            $resolvedRawLogs[] = self::prepareRawLogRow(

                                $rawLog,

                                $employeeIdForLog,

                                $historyIdForLog

                            );

                            $summary['raw_logs_processed']++;

                            if (!$employeeIdForLog) {

                                $summary['raw_logs_unmatched']++;

                            }

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

                // Tambahkan roster karyawan yang memiliki identitas fingerprint
                // tersimpan pada Contract History yang mencakup periode ini.
                // Ini juga menangkap karyawan yang tidak menghasilkan satu pun scan.
                $periodFingerprintHistories = ContractHistoryLocal::query()
                    ->with('contract.employee')
                    ->where('start_date', '<=', $periodEndString)
                    ->where(function ($query) use ($periodStartString): void {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>', $periodStartString);
                    })
                    ->where(function ($query): void {
                        $query->where(function ($identityQuery): void {
                            $identityQuery->whereNotNull('nik_fingerprint')
                                ->where('nik_fingerprint', '<>', '');
                        })->orWhere(function ($identityQuery): void {
                            $identityQuery->whereNotNull('fingerprint_pin')
                                ->where('fingerprint_pin', '<>', '');
                        });
                    })
                    ->get();

                foreach ($periodFingerprintHistories as $periodHistory) {
                    $periodEmployeeId = (int) ($periodHistory->contract?->employee?->id_employee ?? 0);
                    if ($periodEmployeeId > 0) {
                        $resolvedEmployeeIds[$periodEmployeeId] = $periodEmployeeId;
                    }
                }

                // Lengkapi kalender bulanan. Hari kerja dalam masa kontrak tanpa
                // punch fingerprint menjadi Alpha (A); manual entries tetap menang.
                // Minggu/libur, contract gap/overlap, dan tanggal di luar kontrak dikecualikan.
                if ($resolvedEmployeeIds !== []) {
                    $resolvedEmployees = Employee::query()
                        ->with('contract.histories')
                        ->whereIn('id_employee', array_values($resolvedEmployeeIds))
                        ->get()
                        ->keyBy('id_employee');

                    $holidayDates = Holiday::query()
                        ->where('is_active', true)
                        ->whereBetween('holiday_date', [$periodStartString, $periodEndString])
                        ->pluck('holiday_date')
                        ->map(static fn ($date): string => Carbon::parse($date)->format('Y-m-d'))
                        ->flip()
                        ->all();

                    // Gabungkan punch dari file ini dan raw log yang sudah tersimpan.
                    // Re-import tidak boleh mengubah tanggal yang sudah memiliki scan menjadi A.
                    $punchDates = [];
                    foreach ($resolvedRawLogs as $rawLog) {
                        $rawEmployeeId = (int) ($rawLog['employee_id'] ?? 0);
                        $rawScanAt = $rawLog['scan_at'] ?? null;
                        if ($rawEmployeeId > 0 && $rawScanAt) {
                            $rawDate = Carbon::parse($rawScanAt)->format('Y-m-d');
                            $punchDates[$rawEmployeeId . '|' . $rawDate] = true;
                        }
                    }

                    $existingPunchLogs = FingerprintLog::query()
                        ->whereIn('employee_id', array_values($resolvedEmployeeIds))
                        ->whereBetween('scan_at', [
                            $periodStartString . ' 00:00:00',
                            $periodEndString . ' 23:59:59',
                        ])
                        ->get(['employee_id', 'scan_at']);

                    foreach ($existingPunchLogs as $existingPunch) {
                        $rawEmployeeId = (int) $existingPunch->employee_id;
                        $rawDate = Carbon::parse($existingPunch->scan_at)->format('Y-m-d');
                        $punchDates[$rawEmployeeId . '|' . $rawDate] = true;
                    }

                    // Build satu status final per karyawan/tanggal.
                    $resolvedRows = [];
                    foreach ($resolvedEmployees as $employee) {
                        $employeeId = (int) $employee->id_employee;
                        $histories = $employee->contract?->histories ?? collect();

                        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
                            $dateString = $date->format('Y-m-d');

                            if (ContractHistoryResolver::statusOnDate($histories, $date) !== 'covered') {
                                continue;
                            }

                            $hasPunch = isset($punchDates[$employeeId . '|' . $dateString]);
                            $isNonWorkingDate = $date->isSunday() || isset($holidayDates[$dateString]);

                            // Sunday/holiday boleh H bila ada scan nyata, tetapi tidak dibuat A.
                            if ($isNonWorkingDate && !$hasPunch) {
                                continue;
                            }

                            // Hari ini dan tanggal masa depan belum boleh otomatis dianggap Alpha.
                            if (!$hasPunch && $date->greaterThanOrEqualTo(Carbon::today())) {
                                continue;
                            }

                            $resolvedRows[] = [
                                'employee_id' => $employeeId,
                                'attendance_date' => $dateString,
                                'status' => $hasPunch ? 'H' : 'A',
                                'day' => (int) $date->format('d'),
                            ];
                        }
                    }
                }

                if ($resolvedRows === [] && $resolvedRawLogs === []) {

                    foreach ($historyUpdates as $update) {

                        if ($update['changes'] !== []) {

                            $update['model']->update($update['changes']);

                        }

                    }

                    continue;

                }

                // Payroll lock diambil sekali untuk seluruh employee periode ini.

                $payrollRows = $resolvedEmployeeIds === []

                    ? collect()

                    : Payroll::query()

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

                $existingRecords = $resolvedEmployeeIds === []

                    ? collect()

                    : AttendanceRecord::query()

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

                    if ($status === 'A') {
                        $summary['auto_alpha']++;
                    }

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

                DB::transaction(function () use ($upserts, $resolvedRawLogs, $historyUpdates): void {

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

                    // Raw fingerprint punches disimpan utuh dan idempotent.

                    // Tidak ada interpretasi masuk/pulang atau H/0.5H pada tahap ini.

                    foreach (array_chunk($resolvedRawLogs, 500) as $chunk) {

                        \App\Models\FingerprintLog::query()->upsert(

                            $chunk,

                            ['log_hash'],

                            [

                                'employee_id',

                                'contract_history_id',

                                'updated_at',

                            ]

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

    private static function prepareRawLogRow(array $rawLog, ?int $employeeId, ?int $historyId): array

    {

        $identity = [

            trim((string) ($rawLog['pin_mesin'] ?? '')),

            strtoupper(trim((string) ($rawLog['nik_mesin'] ?? ''))),

            (string) ($rawLog['scan_at'] ?? ''),

            (string) ($rawLog['device_sn'] ?? ''),

            (string) ($rawLog['device_name'] ?? ''),

            (string) ($rawLog['mode'] ?? ''),

            (string) ($rawLog['mode_update'] ?? ''),

            (string) ($rawLog['verification'] ?? ''),

        ];

        return [

            'employee_id' => $employeeId,

            'contract_history_id' => $historyId,

            'fingerprint_pin' => self::nullableValue($rawLog['pin_mesin'] ?? null, 50),

            'nik' => self::nullableValue($rawLog['nik_mesin'] ?? null, 50),

            'scan_at' => $rawLog['scan_at'],

            'device_sn' => self::nullableValue($rawLog['device_sn'] ?? null, 100),

            'device_name' => self::nullableValue($rawLog['device_name'] ?? null, 150),

            'verification' => self::nullableValue($rawLog['verification'] ?? null, 50),

            'mode' => self::nullableValue($rawLog['mode'] ?? null, 80),

            'mode_update' => self::nullableValue($rawLog['mode_update'] ?? null, 80),

            'branch' => self::nullableValue($rawLog['branch'] ?? null, 100),

            'department' => self::nullableValue($rawLog['department'] ?? null, 100),

            'job_position' => self::nullableValue($rawLog['job_position'] ?? null, 100),

            'source_file' => self::nullableValue($rawLog['source_file'] ?? null, 255),

            'log_hash' => hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),

            'created_at' => now(),

            'updated_at' => now(),

        ];

    }

    private static function nullableValue(mixed $value, int $maxLength): ?string

    {

        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);

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
