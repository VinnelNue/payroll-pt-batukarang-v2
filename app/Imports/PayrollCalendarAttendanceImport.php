<?php

namespace App\Imports;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Throwable;

/**
 * Re-imports attendance edited in the Payroll Local "Detail Lengkap" sheet.
 *
 * The sheet contains a hidden internal employee ID column and real calendar
 * day headers. Empty / '-' / generated 'HB' cells are display placeholders and
 * are deliberately not written to attendance_records.
 */
class PayrollCalendarAttendanceImport implements WithMultipleSheets, SkipsUnknownSheets
{
    private PayrollCalendarAttendanceSheetImport $sheetImport;

    public function __construct(
        string $period,
        int $cutoffDay,
        bool $isLocked
    ) {
        $this->sheetImport = new PayrollCalendarAttendanceSheetImport(
            $period,
            $cutoffDay,
            $isLocked
        );
    }

    public function sheets(): array
    {
        return [
            'Detail Lengkap' => $this->sheetImport,
        ];
    }

    public function onUnknownSheet(string $sheetName): void
    {
        // A workbook without "Detail Lengkap" may be a fingerprint workbook.
        // The controller detects this and falls back to AttendanceImport.
    }

    public function sheetWasFound(): bool
    {
        return $this->sheetImport->sheetWasFound();
    }

    public function wasRecognized(): bool
    {
        return $this->sheetImport->wasRecognized();
    }

    public function summary(): array
    {
        return $this->sheetImport->summary();
    }
}

class PayrollCalendarAttendanceSheetImport implements ToCollection, WithStartRow
{
    private string $period;
    private int $cutoffDay;
    private bool $isLocked;
    private bool $sheetWasFound = false;
    private bool $recognized = false;

    private array $summary = [
        'inserted' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped_locked' => 0,
        'skipped_placeholder' => 0,
        'skipped_invalid' => 0,
        'skipped_unknown_employee' => 0,
    ];

    private const ALLOWED_STATUSES = [
        'H', 'H0.5', 'A', 'I', 'SKD', 'S', 'C', 'CM', 'M/HB',
    ];

    public function __construct(
        string $period,
        int $cutoffDay,
        bool $isLocked
    ) {
        $this->period = $period;
        $this->cutoffDay = max(1, min(31, $cutoffDay));
        $this->isLocked = $isLocked;
    }

    public function startRow(): int
    {
        // Row 5 is the calendar header (01 MIN, 02 SEN, ...).
        return 5;
    }

    public function sheetWasFound(): bool
    {
        return $this->sheetWasFound;
    }

    public function wasRecognized(): bool
    {
        return $this->recognized;
    }

    public function summary(): array
    {
        return $this->summary;
    }

    public function collection(Collection $rows): void
    {
        $this->sheetWasFound = true;

        if ($rows->isEmpty()) {
            return;
        }

        $header = $this->rowValues($rows->first());
        $employeeIdColumn = null;
        $dayColumns = [];

        foreach ($header as $columnIndex => $headerValue) {
            $label = strtoupper(trim((string) $headerValue));

            if ($label === '_EMPLOYEE_ID') {
                $employeeIdColumn = (int) $columnIndex;
                continue;
            }

            if (preg_match('/^\s*(\d{1,2})(?:\s|$)/u', $label, $matches)) {
                $day = (int) $matches[1];
                if ($day >= 1 && $day <= 31) {
                    $dayColumns[(int) $columnIndex] = $day;
                }
            }
        }

        $daysInMonth = Carbon::createFromFormat('Y-m-d', $this->period . '-01')->daysInMonth;
        $dayColumns = array_filter(
            $dayColumns,
            static fn (int $day): bool => $day <= $daysInMonth
        );

        if ($employeeIdColumn === null || empty($dayColumns)) {
            return;
        }

        $this->recognized = true;

        $pending = [];
        $employeeIds = [];

        foreach ($rows->slice(1) as $row) {
            $values = $this->rowValues($row);
            $rawEmployeeId = $values[$employeeIdColumn] ?? null;

            if (!is_numeric($rawEmployeeId) || (int) $rawEmployeeId < 1) {
                continue; // Department headings and total rows have no internal ID.
            }

            $employeeId = (int) $rawEmployeeId;
            $employeeIds[$employeeId] = $employeeId;

            foreach ($dayColumns as $columnIndex => $day) {
                $rawStatus = $values[$columnIndex] ?? null;
                $status = strtoupper(trim((string) ($rawStatus ?? '')));

                // Export-generated empty marks / holiday labels are not attendance input.
                if ($status === '' || in_array($status, ['-', '—', 'HB'], true)) {
                    $this->summary['skipped_placeholder']++;
                    continue;
                }

                if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                    $this->summary['skipped_invalid']++;
                    continue;
                }

                if ($this->isLocked && $day < $this->cutoffDay) {
                    $this->summary['skipped_locked']++;
                    continue;
                }

                $date = Carbon::createFromFormat(
                    'Y-m-d',
                    sprintf('%s-%02d', $this->period, $day)
                );
                $dateString = $date->format('Y-m-d');
                $key = $employeeId . '|' . $dateString;

                $pending[$key] = [
                    'employee_id' => $employeeId,
                    'attendance_date' => $dateString,
                    'status' => $status,
                ];
            }
        }

        if (empty($pending)) {
            return;
        }

        $validEmployeeIds = Employee::query()
            ->whereIn('id_employee', array_values($employeeIds))
            ->pluck('id_employee')
            ->map(static fn ($id): int => (int) $id)
            ->flip();

        $pending = array_filter(
            $pending,
            function (array $item) use ($validEmployeeIds): bool {
                if (!$validEmployeeIds->has($item['employee_id'])) {
                    $this->summary['skipped_unknown_employee']++;
                    return false;
                }
                return true;
            }
        );

        if (empty($pending)) {
            return;
        }

        $employeeIdsForLookup = array_values(array_unique(array_column($pending, 'employee_id')));
        $periodStart = $this->period . '-01';
        $periodEnd = Carbon::createFromFormat('Y-m-d', $periodStart)->endOfMonth()->format('Y-m-d');

        $existingRecords = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIdsForLookup)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->get(['employee_id', 'attendance_date', 'status'])
            ->keyBy(static function (AttendanceRecord $record): string {
                return (int) $record->employee_id . '|' . Carbon::parse($record->attendance_date)->format('Y-m-d');
            });

        $upserts = [];
        $now = now();

        foreach ($pending as $key => $item) {
            $existing = $existingRecords->get($key);
            $currentStatus = strtoupper(trim((string) ($existing?->status ?? '')));

            if ($existing && $currentStatus === $item['status']) {
                $this->summary['unchanged']++;
                continue; // Keep the original source (e.g. fingerprint/manual).
            }

            if ($existing) {
                $this->summary['updated']++;
            } else {
                $this->summary['inserted']++;
            }

            $upserts[] = [
                'employee_id' => $item['employee_id'],
                'attendance_date' => $item['attendance_date'],
                'status' => $item['status'],
                'source' => 'manual_excel',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upserts)) {
            DB::transaction(function () use ($upserts): void {
                foreach (array_chunk($upserts, 500) as $chunk) {
                    AttendanceRecord::query()->upsert(
                        $chunk,
                        ['employee_id', 'attendance_date'],
                        ['status', 'source', 'updated_at']
                    );
                }
            });
        }
    }

    private function rowValues(mixed $row): array
    {
        if ($row instanceof Collection) {
            return array_values($row->toArray());
        }

        if (is_array($row)) {
            return array_values($row);
        }

        return [];
    }
}
