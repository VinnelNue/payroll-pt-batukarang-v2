<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes the shared employee master into the two attendance screens.
 * The source of truth is the current contract history job_title, not department.
 *
 * Raw fingerprint records remain in the shared attendance_records table.
 */
final class AttendanceEmployeeScope
{
    public const SECURITY_JOB_TITLE = 'satpam';

    public static function normalize(string $scope): string
    {
        return strtolower(trim($scope)) === 'security' ? 'security' : 'local';
    }

    /**
     * Payroll screens must include active employees plus employees who were
     * contract-covered during a historical period even if they are inactive today.
     * Active employees without a history remain visible for HR review.
     */
    public static function applyPeriodEligibility(Builder $query, string $period): Builder
    {
        $periodStart = \Carbon\Carbon::createFromFormat('!Y-m-d', $period . '-01')
            ->startOfMonth()
            ->format('Y-m-d');
        $periodEnd = \Carbon\Carbon::createFromFormat('!Y-m-d', $period . '-01')
            ->endOfMonth()
            ->format('Y-m-d');

        return $query->where(function (Builder $eligible) use ($periodStart, $periodEnd): void {
            $eligible->where('employees.is_active', true)
                ->orWhereHas('contract.histories', function (Builder $history) use ($periodStart, $periodEnd): void {
                    $history->whereDate('start_date', '<=', $periodEnd)
                        ->where(function (Builder $end) use ($periodStart): void {
                            $end->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $periodStart);
                        })
                        ->where(function (Builder $exit) use ($periodStart): void {
                            $exit->whereNull('exit_date')
                                ->orWhereDate('exit_date', '>=', $periodStart);
                        });
                });
        });
    }

    /**
     * Payroll-only variant: classify an employee using contract histories
     * that overlap the selected payroll month. The normal attendance screens
     * keep using apply(), which continues to reflect today's current contract.
     */
    public static function applyForPeriod(Builder $query, string $scope, string $period): Builder
    {
        $scope = self::normalize($scope);
        $periodStart = \Carbon\Carbon::createFromFormat('!Y-m-d', $period . '-01')
            ->startOfMonth()
            ->format('Y-m-d');
        $periodEnd = \Carbon\Carbon::createFromFormat('!Y-m-d', $period . '-01')
            ->endOfMonth()
            ->format('Y-m-d');

        $isSecurityHistory = static function (Builder $historyQuery) use ($periodStart, $periodEnd): void {
            $historyQuery
                ->whereRaw(
                    "LOWER(TRIM(COALESCE(job_title, ''))) = ?",
                    [self::SECURITY_JOB_TITLE]
                )
                ->whereDate('start_date', '<=', $periodEnd)
                ->where(function (Builder $dateQuery) use ($periodStart): void {
                    $dateQuery->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $periodStart);
                })
                ->where(function (Builder $exitQuery) use ($periodStart): void {
                    $exitQuery->whereNull('exit_date')
                        ->orWhereDate('exit_date', '>=', $periodStart);
                });
        };

        if ($scope === 'security') {
            return $query->whereHas('contract.histories', $isSecurityHistory);
        }

        // Employees with no history overlapping the month remain in Local;
        // only people with a Satpam history during that month are excluded.
        return $query->whereDoesntHave('contract.histories', $isSecurityHistory);
    }

    public static function apply(Builder $query, string $scope): Builder
    {
        $scope = self::normalize($scope);

        $isSecurityJob = static function (Builder $historyQuery): void {
            $historyQuery->whereRaw(
                "LOWER(TRIM(COALESCE(job_title, ''))) = ?",
                [self::SECURITY_JOB_TITLE]
            );
        };

        if ($scope === 'security') {
            return $query->whereHas('contract.currentHistory', $isSecurityJob);
        }

        // Keep employees without a current contract history in the normal
        // local list; only the exact Satpam job title is excluded.
        return $query->whereDoesntHave('contract.currentHistory', $isSecurityJob);
    }
}
