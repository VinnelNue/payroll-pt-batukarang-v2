<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\MaternityLeaveCase;
use App\Models\Payroll;
use Carbon\CarbonPeriod;

class MaternityLeaveScheduleService
{
    /**
     * Sync CM schedule to attendance_records.
     * CM still spans calendar days, but Sundays are not stored as CM
     * attendance records and are not included in Sigma/CM hold days.
     *
     * For a locked payroll month, dates on or before cutoff_day are protected.
     * Dates after cutoff_day may still be synchronized to attendance_records.
     * If cutoff_day is missing or invalid, the entire locked month is protected.
     * This service does not modify, unlock, or recalculate payroll records.
     *
     * @return array{
     *     updated:int,
     *     restored:int,
     *     skipped_locked:int,
     *     from:string,
     *     to:string,
     *     period1_days:int,
     *     period2_days:int,
     *     calendar_days:int,
     *     sundays_excluded:int,
     *     sundays_legacy_without_snapshot:int
     * }
     */
    public function syncAttendance(MaternityLeaveCase $case): array
    {
        $desiredDates = [];
        $calendarDays = 0;
        $sundaysExcluded = 0;

        foreach ($case->schedulePeriods() as $period) {
            foreach (CarbonPeriod::create($period['start'], $period['end']) as $date) {
                $calendarDays++;

                // Sunday remains inside the calendar duration, but it is not
                // created as a CM attendance row and is not counted as a CM hold day.
                if ($date->isSunday()) {
                    $sundaysExcluded++;
                    continue;
                }

                $desiredDates[$date->format('Y-m-d')] = true;
            }
        }

        // Collect every month touched by the new schedule or existing snapshots.
        // Existing snapshot months are included so dates removed from a revised
        // schedule can also be restored, provided those dates are not locked.
        $periodMonths = [];

        foreach (array_keys($desiredDates) as $dateString) {
            $periodMonths[substr($dateString, 0, 7)] = true;
        }

        $snapshots = $case->attendance_snapshots ?? [];

        foreach (array_keys($snapshots) as $dateString) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateString)) {
                $periodMonths[substr((string) $dateString, 0, 7)] = true;
            }
        }

        // Only locked payroll months are loaded. Lock protection is then applied
        // per date using cutoff_day, rather than blocking the entire month.
        $lockedCutoffs = Payroll::query()
            ->where('employee_id', $case->employee_id)
            ->whereIn('period_month', array_keys($periodMonths))
            ->where('is_locked', true)
            ->get(['period_month', 'cutoff_day'])
            ->keyBy(fn ($payroll) => substr((string) $payroll->period_month, 0, 7));

        $isDateLocked = static function (string $dateString) use ($lockedCutoffs): bool {
            $month = substr($dateString, 0, 7);
            $payroll = $lockedCutoffs->get($month);

            // No locked payroll exists for this month.
            if (!$payroll) {
                return false;
            }

            $cutoffDay = (int) $payroll->cutoff_day;

            // Fail safely: if cutoff_day is absent/empty/invalid, protect the
            // entire locked month instead of risking changes to finalized data.
            if ($cutoffDay < 1 || $cutoffDay > 31) {
                return true;
            }

            $day = (int) substr($dateString, 8, 2);

            // The cutoff date itself and all earlier dates remain protected.
            // Only dates strictly after cutoff_day are eligible for CM sync.
            return $day <= $cutoffDay;
        };

        $updated = 0;
        $restored = 0;
        $skippedLocked = 0;
        $sundaysLegacyWithoutSnapshot = 0;

        // Detect old Sunday CM rows that have no original-state snapshot.
        // Keep them untouched rather than risk deleting a real Sunday attendance
        // that an older P7 build may have overwritten before snapshots existed.
        $legacyMaternityRows = AttendanceRecord::query()
            ->where('employee_id', $case->employee_id)
            ->where('source', 'maternity')
            ->whereBetween('attendance_date', [
                $case->firstPeriodStartDate()->toDateString(),
                $case->leaveEndDate()->toDateString(),
            ])
            ->get(['attendance_date']);

        foreach ($legacyMaternityRows as $legacyRow) {
            $legacyDate = $legacyRow->attendance_date->copy()->startOfDay();
            $legacyDateString = $legacyDate->toDateString();

            if (
                $legacyDate->isSunday()
                && $case->containsDate($legacyDate)
                && !array_key_exists($legacyDateString, $snapshots)
            ) {
                $sundaysLegacyWithoutSnapshot++;
            }
        }

        // Restore records from the previous schedule that are no longer CM.
        // This also handles old Sunday CM rows when a snapshot is available.
        foreach (array_keys($snapshots) as $dateString) {
            if (isset($desiredDates[$dateString])) {
                continue;
            }

            if ($isDateLocked((string) $dateString)) {
                $skippedLocked++;
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('employee_id', $case->employee_id)
                ->whereDate('attendance_date', $dateString)
                ->first();

            $wasMaternityRecord = $record
                && strtolower((string) $record->source) === 'maternity';

            if ($wasMaternityRecord) {
                $record->delete();
            }

            $original = $snapshots[$dateString] ?? ['exists' => false];

            if (!empty($original['exists'])) {
                // Restore the original record only when the CM row was removed
                // or the date is now empty. Never overwrite a later manual row.
                $current = AttendanceRecord::query()
                    ->where('employee_id', $case->employee_id)
                    ->whereDate('attendance_date', $dateString)
                    ->first();

                if (!$current) {
                    AttendanceRecord::create([
                        'employee_id' => $case->employee_id,
                        'attendance_date' => $dateString,
                        'status' => $original['status'] ?? null,
                        'source' => $original['source'] ?? null,
                    ]);
                    $restored++;
                }
            } elseif ($wasMaternityRecord) {
                // No attendance existed before the CM scheduler created the row.
                $restored++;
            }

            unset($snapshots[$dateString]);
        }

        // Apply CM only to non-Sunday dates in the calendar schedule.
        foreach (array_keys($desiredDates) as $dateString) {
            if ($isDateLocked($dateString)) {
                $skippedLocked++;
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('employee_id', $case->employee_id)
                ->whereDate('attendance_date', $dateString)
                ->first();

            if (!array_key_exists($dateString, $snapshots)) {
                // Capture the original state before replacing it with CM.
                $snapshots[$dateString] = $record
                    && strtolower((string) $record->source) !== 'maternity'
                    ? [
                        'exists' => true,
                        'status' => $record->status,
                        'source' => $record->source,
                    ]
                    : ['exists' => false];
            }

            if ($record) {
                if ($record->status !== 'CM' || $record->source !== 'maternity') {
                    $record->status = 'CM';
                    $record->source = 'maternity';
                    $record->save();
                }
            } else {
                AttendanceRecord::create([
                    'employee_id' => $case->employee_id,
                    'attendance_date' => $dateString,
                    'status' => 'CM',
                    'source' => 'maternity',
                ]);
            }

            $updated++;
        }

        $case->forceFill([
            'attendance_snapshots' => $snapshots,
        ])->save();

        return [
            'updated' => $updated,
            'restored' => $restored,
            'skipped_locked' => $skippedLocked,
            'from' => $case->firstPeriodStartDate()->format('Y-m-d'),
            'to' => $case->leaveEndDate()->format('Y-m-d'),
            'period1_days' => $case->firstPeriodDays(),
            'period2_days' => $case->secondPeriodDays(),
            'calendar_days' => $calendarDays,
            'sundays_excluded' => $sundaysExcluded,
            'sundays_legacy_without_snapshot' => $sundaysLegacyWithoutSnapshot,
        ];
    }
}
