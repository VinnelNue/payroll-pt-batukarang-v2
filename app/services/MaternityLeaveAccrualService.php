<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\CompanySetting;
use App\Models\ContractHistoryLocal;
use App\Models\EmployeeContract;
use App\Models\MaternityLeaveAccrual;
use App\Models\MaternityLeaveCase;
use App\Models\Payroll;
use App\Support\ContractHistoryResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaternityLeaveAccrualService
{
    /**
     * Snapshot the exact CM days withheld by a payroll calculation.
     * This is called in the payroll transaction after the payroll row is saved.
     */
    public function recordPayrollHoldAccruals(
        MaternityLeaveCase $case,
        array $attendanceDates,
        Payroll $payroll
    ): Collection {
        if ($case->status === 'verified') {
            return collect();
        }

        $employeeContract = $this->employeeContractFor($case);
        $histories = $this->historiesFor($employeeContract->id_contract);
        $result = collect();
        $attendanceDates = array_values(array_unique(array_map('strval', $attendanceDates)));

        // If this payroll is recalculated before HRD verifies the case, remove
        // stale, not-yet-settled day snapshots that are no longer withheld.
        $periodStart = Carbon::createFromFormat('Y-m-d', $payroll->period_month . '-01')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $staleQuery = MaternityLeaveAccrual::query()
            ->where('maternity_case_id', $case->id)
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNull('settlement_id');
        if ($attendanceDates !== []) {
            $staleQuery->whereNotIn('attendance_date', $attendanceDates);
        }
        $staleQuery->delete();

        foreach ($attendanceDates as $dateString) {
            $date = Carbon::parse($dateString)->startOfDay();

            if (
                $date->isSunday()
                || !$case->containsDate($date)
                || $date->format('Y-m') !== $payroll->period_month
            ) {
                continue;
            }

            $history = ContractHistoryResolver::uniqueForDate($histories, $date);
            if (!$history) {
                throw $this->historyError($histories, $date);
            }

            $standardWorkDays = (int) ($payroll->standard_work_days ?: $this->standardWorkDaysForPeriod($date));
            $accrual = $this->saveSnapshot(
                $case,
                $date,
                $history,
                $standardWorkDays,
                (int) $payroll->id_payroll,
                (float) $payroll->basic_salary,
                (float) $payroll->allowance
            );
            $result->push($accrual);
        }

        return $result;
    }

    /**
     * Create any earned CM snapshot not already captured by a payroll hold.
     * If a payroll row already exists for a month but there is no daily accrual
     * for a date, that date was not held by that payroll and remains payable in
     * the regular monthly payroll. We deliberately do not settle it a second time.
     *
     * Verification day itself is excluded: only complete days before verifiedAt
     * are included in this lump-sum settlement.
     */
    public function accrueThroughVerification(
        MaternityLeaveCase $case,
        CarbonInterface $verifiedAt
    ): Collection {
        $employeeContract = $this->employeeContractFor($case);
        $histories = $this->historiesFor($employeeContract->id_contract);

        $lastEarnedDate = Carbon::parse($verifiedAt)->startOfDay()->subDay();
        $firstDate = Carbon::parse($case->leave_start_date)->startOfDay();

        if ($lastEarnedDate->lt($firstDate)) {
            return collect();
        }

        $attendanceRows = AttendanceRecord::query()
            ->where('employee_id', $case->employee_id)
            ->whereDate('attendance_date', '>=', $firstDate->toDateString())
            ->whereDate('attendance_date', '<=', $lastEarnedDate->toDateString())
            ->get(['id', 'employee_id', 'attendance_date', 'status'])
            ->keyBy(fn (AttendanceRecord $row) => Carbon::parse($row->attendance_date)->toDateString());

        $months = [];
        $expectedDates = [];
        for ($date = $firstDate->copy(); $date->lte($lastEarnedDate); $date->addDay()) {
            if (!$date->isSunday() && $case->containsDate($date)) {
                $dateString = $date->toDateString();
                $expectedDates[] = $dateString;
                $months[$date->format('Y-m')] = true;
            }
        }

        $payrollsByPeriod = Payroll::query()
            ->where('employee_id', $case->employee_id)
            ->whereIn('period_month', array_keys($months))
            ->get()
            ->keyBy('period_month');

        $result = collect();

        foreach ($expectedDates as $dateString) {
            $date = Carbon::parse($dateString)->startOfDay();
            $attendance = $attendanceRows->get($dateString);
            $periodPayroll = $payrollsByPeriod->get($date->format('Y-m'));
            $existing = MaternityLeaveAccrual::query()
                ->where('maternity_case_id', $case->id)
                ->whereDate('attendance_date', $dateString)
                ->first();

            if ($existing) {
                // Existing per-day payroll snapshots are the source of truth.
                // Do not recalculate historical salary after Contract History changes.
                $result->push($existing);
                continue;
            }

            if ($periodPayroll) {
                // A legacy payroll row with an aggregate CM hold but no matching
                // daily snapshot is ambiguous. Stop instead of silently omitting
                // withheld money or risking a duplicate payout. Reprocess that
                // dummy/draft payroll with this patch before HRD verification.
                $savedAt = $periodPayroll->updated_at
                    ? Carbon::parse($periodPayroll->updated_at)->startOfDay()
                    : null;
                $payrollHasLegacyHold = (float) ($periodPayroll->maternity_hold_days ?? 0) > 0
                    || (float) ($periodPayroll->maternity_hold_amount ?? 0) > 0;

                if ($savedAt && $date->lt($savedAt) && $payrollHasLegacyHold) {
                    throw ValidationException::withMessages([
                        'maternity_case' => sprintf(
                            'Payroll %s memiliki penahanan CM agregat tetapi snapshot harian tanggal %s tidak ditemukan. Proses ulang payroll periode tersebut dengan patch baru sebelum HRD memverifikasi agar nominal settlement tepat.',
                            $date->format('Y-m'),
                            $date->format('d/m/Y')
                        ),
                    ]);
                }

                // This date was not included in the stored payroll hold snapshot.
                // It remains payable through regular payroll and is not settled again.
                continue;
            }

            if (!$attendance || strtoupper((string) $attendance->status) !== 'CM') {
                throw ValidationException::withMessages([
                    'maternity_case' => sprintf(
                        'Record absensi CM tanggal %s belum tersedia. Sinkronkan jadwal CM sebelum verifikasi agar settlement tidak kurang bayar.',
                        $date->format('d/m/Y')
                    ),
                ]);
            }

            $history = ContractHistoryResolver::uniqueForDate($histories, $date);
            if (!$history) {
                throw $this->historyError($histories, $date);
            }

            $standardWorkDays = $this->standardWorkDaysForPeriod($date);
            $accrual = $this->saveSnapshot(
                $case,
                $date,
                $history,
                $standardWorkDays,
                null,
                null,
                null
            );
            $result->push($accrual);
        }

        return $result->sortBy('attendance_date')->values();
    }

    private function saveSnapshot(
        MaternityLeaveCase $case,
        Carbon $date,
        mixed $history,
        int $standardWorkDays,
        ?int $payrollId,
        ?float $basicSalaryOverride = null,
        ?float $allowanceOverride = null
    ): MaternityLeaveAccrual {
        $standardWorkDays = in_array($standardWorkDays, [26, 27], true)
            ? $standardWorkDays
            : 26;

        $basicSalary = round(
            $basicSalaryOverride ?? (float) $history->basic_salary,
            2
        );
        $level = (int) ($history->level ?? 0);

        // A payroll-created snapshot must use the exact salary fields already
        // used by that payroll row. When HRD verifies before payroll exists,
        // derive the allowance using the controller's level x 2% rule.
        $allowance = $allowanceOverride !== null
            ? round($allowanceOverride, 2)
            : ($basicSalary > 0 && $level > 0
                ? round($basicSalary * ($level * 0.02), 2)
                : 0.0);

        $dailyRate = round(($basicSalary + $allowance) / $standardWorkDays, 2);

        $accrual = MaternityLeaveAccrual::query()->firstOrNew([
            'maternity_case_id' => $case->id,
            'attendance_date' => $date->toDateString(),
        ]);

        // An accrual assigned to a settlement must remain immutable.
        if ($accrual->exists && $accrual->settlement_id) {
            return $accrual;
        }

        $accrual->fill([
            'employee_id' => $case->employee_id,
            'contract_history_id' => $history->id_contract_history,
            'standard_work_days' => $standardWorkDays,
            'basic_salary' => $basicSalary,
            'allowance' => $allowance,
            'daily_rate' => $dailyRate,
            'amount' => $dailyRate,
            'payroll_id' => $payrollId,
            'status' => 'accrued',
        ]);
        $accrual->save();

        return $accrual;
    }

    private function employeeContractFor(MaternityLeaveCase $case): EmployeeContract
    {
        $employeeContract = EmployeeContract::query()
            ->where('employee_id', $case->employee_id)
            ->first();

        if (!$employeeContract) {
            throw ValidationException::withMessages([
                'maternity_case' => 'Karyawan belum memiliki master kontrak. Settlement CM tidak dapat dihitung.',
            ]);
        }

        return $employeeContract;
    }

    private function historiesFor(int $contractId): Collection
    {
        return ContractHistoryLocal::query()
            ->where('contract_id', $contractId)
            ->orderBy('start_date')
            ->orderBy('id_contract_history')
            ->get();
    }

    private function standardWorkDaysForPeriod(Carbon $date): int
    {
        // Same resolution order as PayrollController::periodStandardWorkDays().
        $savedDays = Payroll::query()
            ->where('period_month', $date->format('Y-m'))
            ->whereIn('standard_work_days', [26, 27])
            ->orderByDesc('id_payroll')
            ->value('standard_work_days');

        if ($savedDays !== null) {
            return (int) $savedDays;
        }

        $configured = (int) CompanySetting::get('standard_work_days', 26);

        return in_array($configured, [26, 27], true) ? $configured : 26;
    }

    private function historyError(Collection $histories, Carbon $date): ValidationException
    {
        $coverage = ContractHistoryResolver::statusOnDate($histories, $date);

        return ValidationException::withMessages([
            'maternity_case' => sprintf(
                'Histori kontrak tanggal %s berstatus %s (tidak ada tepat satu histori yang berlaku). Perbaiki Contract History sebelum verifikasi CM.',
                $date->format('d/m/Y'),
                $coverage
            ),
        ]);
    }
}
