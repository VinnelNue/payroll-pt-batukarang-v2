<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeOuterIsland;
use App\Models\Payroll;
use App\Models\PayrollOuterIsland;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    private const FULL_FINANCE_ROLES = [
        'super_admin',
        'manager_keuangan',
    ];

    private const HEAD_HRD_ROLES = [
        'head_hrd',
        'kepala_hrd',
    ];

    private const LIMITED_FINANCE_ROLES = [
        'keuangan',
        'finance',
    ];

    private const HRD_ROLES = [
        'hrd',
    ];

    private const EMPLOYEE_ROLES = [
        'karyawan',
    ];

    public function index(Request $request)
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'scope'  => ['nullable', 'in:all,local,outer_island'],
        ]);

        $user = $request->user();
        $role = strtolower(trim((string) ($user?->role ?? '')));
        $period = (string) ($request->input('period') ?: now()->format('Y-m'));
        $periodStart = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        $previousPeriod = $periodStart->copy()->subMonth()->format('Y-m');
        $windowStart = $periodStart->copy()->subMonths(5)->startOfMonth();
        $windowStartPeriod = $windowStart->format('Y-m');
        $periodLabel = $periodStart->copy()->locale('id')->translatedFormat('F Y');

        $isFullFinance = in_array($role, self::FULL_FINANCE_ROLES, true);
        $isHeadHrd = in_array($role, self::HEAD_HRD_ROLES, true);
        $isLimitedFinance = in_array($role, self::LIMITED_FINANCE_ROLES, true);
        $isHrd = in_array($role, self::HRD_ROLES, true);
        $isEmployeeRole = in_array($role, self::EMPLOYEE_ROLES, true);
        $isKnownStaffRole = $isFullFinance || $isHeadHrd || $isLimitedFinance || $isHrd;
        $canSeeFinancials = $isFullFinance || $isHeadHrd || $isLimitedFinance;
        $canSeeFullFinancials = $isFullFinance;
        // Hanya Super Admin/Manager Keuangan yang memperoleh daftar record dan aksi payroll dari dashboard.
        // Role terbatas hanya menerima agregat finansial yang telah difilter sesuai aturan akses.
        $canSeePayrollWorkflow = $isFullFinance;
        $canSeeUnlockRequests = $isFullFinance;
        $canSeeLevelCategory = $isFullFinance || $isHeadHrd || $isLimitedFinance || $isHrd;

        // Dashboard ini mendukung filter wilayah; default menampilkan seluruh cakupan.
        $scope = (string) ($request->input('scope') ?: 'all');
        $scopeLabel = match ($scope) {
            'local' => 'Local',
            'outer_island' => 'Outer Island',
            default => 'Semua Wilayah',
        };

        $roleLabels = [
            'super_admin'      => 'Super Admin',
            'manager_keuangan' => 'Manager Keuangan',
            'head_hrd'         => 'Head HRD',
            'kepala_hrd'       => 'Kepala HRD',
            'finance'          => 'Finance',
            'keuangan'         => 'Keuangan',
            'hrd'              => 'HRD',
            'karyawan'         => 'Karyawan',
        ];

        $userName = (string) (
            $user?->name
            ?? $user?->username
            ?? $user?->full_name
            ?? 'Pengguna'
        );

        $quickLinkDefinitions = $this->quickLinkDefinitions($role, $period);
        $quickLinks = collect($quickLinkDefinitions)
            ->filter(fn (array $link) => Route::has($link['route']))
            ->map(function (array $link) use ($period) {
                $params = $link['period_param'] ?? false
                    ? ['period' => $period]
                    : [];

                return [
                    'title'       => $link['title'],
                    'description' => $link['description'],
                    'icon'        => $link['icon'],
                    'url'         => route($link['route'], $params),
                    'tone'        => $link['tone'],
                ];
            })
            ->values();

        $periodChoices = collect(range(0, 5))
            ->map(fn (int $offset) => $periodStart->copy()->subMonths(5 - $offset)->format('Y-m'));

        $baseData = [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'previousPeriod' => $previousPeriod,
            'scope' => $scope,
            'scopeLabel' => $scopeLabel,
            'scopeOptions' => [
                'all' => 'Semua Wilayah',
                'local' => 'Local',
                'outer_island' => 'Outer Island',
            ],
            'periodChoices' => $periodChoices,
            'role' => $role,
            'roleLabel' => $roleLabels[$role] ?? 'Pengguna',
            'userName' => $userName,
            'isEmployeeRole' => $isEmployeeRole,
            'isFullFinance' => $isFullFinance,
            'isHeadHrd' => $isHeadHrd,
            'isLimitedFinance' => $isLimitedFinance,
            'isHrd' => $isHrd,
            'isKnownStaffRole' => $isKnownStaffRole,
            'canSeeFinancials' => $canSeeFinancials,
            'canSeeFullFinancials' => $canSeeFullFinancials,
            'canSeePayrollWorkflow' => $canSeePayrollWorkflow,
            'canSeeUnlockRequests' => $canSeeUnlockRequests,
            'canSeeLevelCategory' => $canSeeLevelCategory,
            'quickLinks' => $quickLinks,
            'accessDenied' => !$isKnownStaffRole && !$isEmployeeRole,
            'totalEmployees' => 0,
            'employeesWithContract' => 0,
            'employeesMissingContract' => 0,
            'contractsExpiringCount' => 0,
            'contractsExpiredCount' => 0,
            'contractsExpiring' => collect(),
            'categoryDistribution' => [],
            'totalGrossSalary' => 0,
            'totalNetSalary' => 0,
            'totalPph21' => 0,
            'totalBpjs' => 0,
            'previousGrossSalary' => 0,
            'grossSalaryChange' => null,
            'currentPayrollCount' => 0,
            'currentPayrollEmployees' => 0,
            'payrollPopulationCount' => 0,
            'missingPayrolls' => 0,
            'payrollCoveragePercent' => 0,
            'lockedPayrollCount' => 0,
            'unlockRequestCount' => 0,
            'monthlyTrend' => collect(),
            'recentPayrolls' => collect(),
        ];

        // Karyawan tidak memperoleh statistik agregat perusahaan atau daftar payroll kolega.
        // Pemetaan akun pengguna ke tabel employee perlu diverifikasi sebelum menampilkan slip pribadi.
        if ($isEmployeeRole || !$isKnownStaffRole) {
            return view('dashboard', $baseData);
        }

        $includeLocal = in_array($scope, ['all', 'local'], true);
        $includeOuterIsland = in_array($scope, ['all', 'outer_island'], true);

        $windowPayrollLocal = collect();
        $windowPayrollOuter = collect();

        // Pengguna HRD biasa tidak menjalankan query payroll sama sekali.
        // Head HRD/Keuangan hanya akan menerima agregat untuk level yang diizinkan.
        if ($canSeeFinancials) {
            if ($includeLocal) {
                $windowPayrollLocal = Payroll::query()
                    ->whereBetween('period_month', [$windowStartPeriod, $period])
                    ->get();
            }

            if ($includeOuterIsland && !$isLimitedFinance) {
                $windowPayrollOuter = PayrollOuterIsland::query()
                    ->whereBetween('period_month', [$windowStartPeriod, $period])
                    ->get();
            }
        }

        $localPayrollEmployeeIds = $windowPayrollLocal
            ->pluck('employee_id')->filter()->unique()->values();
        $outerPayrollEmployeeIds = $windowPayrollOuter
            ->pluck('employee_outer_island_id')->filter()->unique()->values();

        $localEmployeeRecords = collect();
        $outerEmployeeRecords = collect();

        if ($includeLocal) {
            $localEmployeeRecords = Employee::query()
                ->with('contract.currentHistory')
                ->where(function ($query) use ($localPayrollEmployeeIds) {
                    $query->where('is_active', true);
                    if ($localPayrollEmployeeIds->isNotEmpty()) {
                        $query->orWhereIn('id_employee', $localPayrollEmployeeIds);
                    }
                })
                ->get();
        }

        if ($includeOuterIsland) {
            $outerEmployeeRecords = EmployeeOuterIsland::query()
                ->with('contractMaster.currentHistory')
                ->where(function ($query) use ($outerPayrollEmployeeIds) {
                    $query->where('is_active', true);
                    if ($outerPayrollEmployeeIds->isNotEmpty()) {
                        $query->orWhereIn('id_employee_outer_island', $outerPayrollEmployeeIds);
                    }
                })
                ->get();
        }

        $normalizeEmployee = function ($employee, string $region): array {
            if ($region === 'local') {
                $contract = $employee->contract;
                $id = $employee->id_employee;
            } else {
                $contract = $employee->contractMaster;
                $id = $employee->id_employee_outer_island;
            }

            $history = $contract?->currentHistory;
            $levelValue = $history?->level;
            $endDate = null;

            if ($history?->end_date) {
                try {
                    $endDate = Carbon::parse($history->end_date)->startOfDay();
                } catch (\Throwable) {
                    $endDate = null;
                }
            }

            return [
                'key' => $region . ':' . (string) $id,
                'id' => $id,
                'region' => $region,
                'region_label' => $region === 'local' ? 'Local' : 'Outer Island',
                'name' => (string) ($employee->full_name ?? $employee->name ?? 'Nama tidak tersedia'),
                'active' => (bool) ($employee->is_active ?? false),
                'has_contract' => $history !== null,
                'contract_active' => (bool) ($contract?->is_active ?? false)
                    && (bool) ($history?->is_active ?? false),
                'job_title' => (string) ($history?->job_title ?? ''),
                'department' => (string) ($history?->department ?? ''),
                'placement_area' => (string) ($history?->placement_area ?? ''),
                'category' => trim((string) ($history?->category ?? '')),
                'level' => is_numeric($levelValue) ? (int) $levelValue : null,
                'start_date' => $history?->start_date,
                'end_date_raw' => $history?->end_date,
                'end_date' => $endDate,
            ];
        };

        $localEmployeeMap = $localEmployeeRecords
            ->mapWithKeys(function ($employee) use ($normalizeEmployee) {
                $normalized = $normalizeEmployee($employee, 'local');
                return [$normalized['key'] => $normalized];
            });

        $outerEmployeeMap = $outerEmployeeRecords
            ->mapWithKeys(function ($employee) use ($normalizeEmployee) {
                $normalized = $normalizeEmployee($employee, 'outer_island');
                return [$normalized['key'] => $normalized];
            });

        $employeeMap = $localEmployeeMap->merge($outerEmployeeMap);

        $activeEmployees = $employeeMap
            ->filter(fn (array $employee) => $employee['active'])
            ->values();

        $canSeeEmployeeLevel = function (array $employee) use (
            $isFullFinance,
            $isHeadHrd,
            $isLimitedFinance,
            $isHrd
        ): bool {
            if ($isFullFinance) {
                return true;
            }

            if (!($isHeadHrd || $isLimitedFinance || $isHrd)) {
                return false;
            }

            return $employee['level'] === null || $employee['level'] <= 13;
        };

        $employeesWithContract = $activeEmployees
            ->filter(fn (array $employee) => $employee['contract_active'])
            ->count();

        $employeesMissingContract = max(0, $activeEmployees->count() - $employeesWithContract);

        $today = Carbon::today();
        $expiringLimit = $today->copy()->addDays(30)->endOfDay();

        $contractAlerts = $activeEmployees
            ->filter(function (array $employee) use ($today, $expiringLimit) {
                return $employee['contract_active']
                    && $employee['end_date'] instanceof Carbon
                    && $employee['end_date']->greaterThanOrEqualTo($today)
                    && $employee['end_date']->lessThanOrEqualTo($expiringLimit);
            })
            ->sortBy(fn (array $employee) => $employee['end_date']?->timestamp ?? PHP_INT_MAX)
            ->take(8)
            ->values();

        $contractsExpiringCount = $activeEmployees
            ->filter(function (array $employee) use ($today, $expiringLimit) {
                return $employee['contract_active']
                    && $employee['end_date'] instanceof Carbon
                    && $employee['end_date']->greaterThanOrEqualTo($today)
                    && $employee['end_date']->lessThanOrEqualTo($expiringLimit);
            })
            ->count();

        $contractsExpiredCount = $activeEmployees
            ->filter(fn (array $employee) => $employee['end_date'] instanceof Carbon
                && $employee['end_date']->lessThan($today))
            ->count();

        $categoryDistribution = $activeEmployees
            ->filter(fn (array $employee) => $employee['category'] !== '' && $canSeeEmployeeLevel($employee))
            ->groupBy(fn (array $employee) => $employee['category'])
            ->map(fn (Collection $group) => $group->count())
            ->sortKeys()
            ->all();

        $normalizePayroll = function ($payroll, string $region) use ($employeeMap): array {
            $employeeId = $region === 'local'
                ? $payroll->employee_id
                : $payroll->employee_outer_island_id;

            $employeeKey = $region . ':' . (string) $employeeId;
            $employee = $employeeMap->get($employeeKey);
            $updatedAt = null;

            if ($payroll->updated_at) {
                try {
                    $updatedAt = Carbon::parse($payroll->updated_at);
                } catch (\Throwable) {
                    $updatedAt = null;
                }
            }

            return [
                'key' => $employeeKey . ':' . (string) ($payroll->period_month ?? '') . ':' . (string) ($payroll->id_payroll ?? $payroll->id ?? spl_object_id($payroll)),
                'employee_key' => $employeeKey,
                'employee_found' => $employee !== null,
                'employee_name' => $employee['name'] ?? 'Data karyawan tidak ditemukan',
                'department' => $employee['department'] ?? '',
                'region' => $region,
                'region_label' => $region === 'local' ? 'Local' : 'Outer Island',
                'level' => $employee['level'] ?? null,
                'period_month' => (string) ($payroll->period_month ?? ''),
                'status' => trim((string) ($payroll->status ?? '')) ?: 'Tersimpan',
                'gross_salary' => (float) ($payroll->gross_salary ?? 0),
                'net_salary' => (float) ($payroll->net_salary ?? 0),
                'pph21_deduction' => (float) ($payroll->pph21_deduction ?? 0),
                'bpjs_tk_deduction' => (float) ($payroll->bpjs_tk_deduction ?? 0),
                'bpjs_ks_deduction' => (float) ($payroll->bpjs_ks_deduction ?? 0),
                'is_locked' => (bool) ($payroll->is_locked ?? false),
                'unlock_requested' => (bool) ($payroll->unlock_requested ?? false),
                'updated_at' => $updatedAt,
            ];
        };

        $normalizedPayrolls = collect();

        if ($canSeeFinancials) {
            foreach ($windowPayrollLocal as $payroll) {
                $normalizedPayrolls->push($normalizePayroll($payroll, 'local'));
            }
            foreach ($windowPayrollOuter as $payroll) {
                $normalizedPayrolls->push($normalizePayroll($payroll, 'outer_island'));
            }
        }

        $financialRowAllowed = function (array $row) use ($isFullFinance, $isHeadHrd, $isLimitedFinance): bool {
            if ($isFullFinance) {
                return true;
            }

            // Keuangan non-manager dibatasi ke data Local. Head HRD mengikuti batas level di kedua wilayah.
            if ($isLimitedFinance && $row['region'] !== 'local') {
                return false;
            }

            // Prinsip fail-closed: payroll yang tidak dapat dipetakan ke karyawan tidak dihitung.
            if (!$row['employee_found']) {
                return false;
            }

            if (!($isHeadHrd || $isLimitedFinance)) {
                return false;
            }

            return $row['level'] === null || $row['level'] <= 13;
        };

        $visibleFinancialPayrolls = $normalizedPayrolls
            ->filter($financialRowAllowed)
            ->values();

        $currentFinancialPayrolls = $visibleFinancialPayrolls
            ->filter(fn (array $row) => $row['period_month'] === $period)
            ->values();

        $previousFinancialPayrolls = $visibleFinancialPayrolls
            ->filter(fn (array $row) => $row['period_month'] === $previousPeriod)
            ->values();

        $totalGrossSalary = $currentFinancialPayrolls->sum('gross_salary');
        $totalNetSalary = $currentFinancialPayrolls->sum('net_salary');
        $totalPph21 = $currentFinancialPayrolls->sum('pph21_deduction');
        $totalBpjs = $currentFinancialPayrolls->sum('bpjs_tk_deduction')
            + $currentFinancialPayrolls->sum('bpjs_ks_deduction');
        $previousGrossSalary = $previousFinancialPayrolls->sum('gross_salary');
        $grossSalaryChange = $previousGrossSalary > 0
            ? round((($totalGrossSalary - $previousGrossSalary) / $previousGrossSalary) * 100, 1)
            : null;

        $payrollEligibleEmployees = $activeEmployees
            ->filter(function (array $employee) use ($isFullFinance, $isHeadHrd, $isLimitedFinance) {
                if ($isFullFinance) {
                    return true;
                }
                if ($isLimitedFinance && $employee['region'] !== 'local') {
                    return false;
                }
                return ($isHeadHrd || $isLimitedFinance)
                    && ($employee['level'] === null || $employee['level'] <= 13);
            })
            ->values();

        $currentPayrollEmployees = $currentFinancialPayrolls
            ->pluck('employee_key')
            ->unique()
            ->count();

        $payrollPopulationCount = $payrollEligibleEmployees->count();
        $missingPayrolls = max(0, $payrollPopulationCount - $currentPayrollEmployees);
        $payrollCoveragePercent = $payrollPopulationCount > 0
            ? round(min(100, ($currentPayrollEmployees / $payrollPopulationCount) * 100), 1)
            : 0;

        $monthlyTrend = $periodChoices->map(function (string $month) use ($visibleFinancialPayrolls) {
            $monthlyRows = $visibleFinancialPayrolls
                ->where('period_month', $month);

            return [
                'period' => $month,
                'label' => Carbon::createFromFormat('!Y-m', $month)->format('m/Y'),
                'gross' => round($monthlyRows->sum('gross_salary'), 2),
                'net' => round($monthlyRows->sum('net_salary'), 2),
            ];
        });

        // Data payroll per karyawan hanya diteruskan ke view manager/super-admin.
        $recentPayrolls = $isFullFinance
            ? $currentFinancialPayrolls
                ->sortByDesc(fn (array $row) => $row['updated_at']?->timestamp ?? 0)
                ->take(8)
                ->values()
            : collect();

        $baseData = array_merge($baseData, [
            'totalEmployees' => $activeEmployees->count(),
            'employeesWithContract' => $employeesWithContract,
            'employeesMissingContract' => $employeesMissingContract,
            'contractsExpiringCount' => $contractsExpiringCount,
            'contractsExpiredCount' => $contractsExpiredCount,
            'contractsExpiring' => $contractAlerts,
            'categoryDistribution' => $categoryDistribution,
            'totalGrossSalary' => $totalGrossSalary,
            'totalNetSalary' => $totalNetSalary,
            'totalPph21' => $totalPph21,
            'totalBpjs' => $totalBpjs,
            'previousGrossSalary' => $previousGrossSalary,
            'grossSalaryChange' => $grossSalaryChange,
            'currentPayrollCount' => $currentFinancialPayrolls->count(),
            'currentPayrollEmployees' => $currentPayrollEmployees,
            'payrollPopulationCount' => $payrollPopulationCount,
            'missingPayrolls' => $missingPayrolls,
            'payrollCoveragePercent' => $payrollCoveragePercent,
            'lockedPayrollCount' => $currentFinancialPayrolls->where('is_locked', true)->count(),
            'unlockRequestCount' => $currentFinancialPayrolls->where('unlock_requested', true)->count(),
            'monthlyTrend' => $monthlyTrend,
            'recentPayrolls' => $recentPayrolls,
        ]);

        return view('dashboard', $baseData);
    }

    private function quickLinkDefinitions(string $role, string $period): array
    {
        $employeeLinks = [
            [
                'title' => 'Master Karyawan Local',
                'description' => 'Data dan status tenaga kerja Local.',
                'icon' => 'fa-users',
                'route' => 'employees.local.index',
                'tone' => 'blue',
            ],
            [
                'title' => 'Master Karyawan Outer Island',
                'description' => 'Data tenaga kerja wilayah luar pulau.',
                'icon' => 'fa-earth-asia',
                'route' => 'employees.outer_island.index',
                'tone' => 'cyan',
            ],
        ];

        $contractLinks = [
            [
                'title' => 'Kontrak Local',
                'description' => 'Kelola periode dan riwayat kontrak Local.',
                'icon' => 'fa-file-contract',
                'route' => 'contracts.local.index',
                'tone' => 'purple',
            ],
            [
                'title' => 'Kontrak Outer Island',
                'description' => 'Kelola periode dan riwayat kontrak Outer Island.',
                'icon' => 'fa-file-signature',
                'route' => 'contracts.outer_island.index',
                'tone' => 'violet',
            ],
        ];

        $payrollLinks = [
            [
                'title' => 'Rekap Payroll Local',
                'description' => 'Lihat hasil payroll sesuai periode.',
                'icon' => 'fa-money-check-dollar',
                'route' => 'payrolls.local.index',
                'tone' => 'green',
            ],
            [
                'title' => 'Input Payroll Local',
                'description' => 'Input absensi dan variabel payroll.',
                'icon' => 'fa-calculator',
                'route' => 'payrolls.local.create',
                'period_param' => true,
                'tone' => 'emerald',
            ],
            [
                'title' => 'Rekap Payroll Outer Island',
                'description' => 'Lihat hasil payroll Outer Island.',
                'icon' => 'fa-money-bill-transfer',
                'route' => 'payrolls.outer_island.index',
                'tone' => 'teal',
            ],
            [
                'title' => 'Input Payroll Outer Island',
                'description' => 'Input proses payroll Outer Island.',
                'icon' => 'fa-calculator',
                'route' => 'payrolls.outer_island.create',
                'period_param' => true,
                'tone' => 'cyan',
            ],
        ];

        $financeLinks = [[
            'title' => 'Master Pajak & BPJS',
            'description' => 'Pengaturan acuan pajak dan BPJS.',
            'icon' => 'fa-scale-balanced',
            'route' => 'tax-bpjs.index',
            'tone' => 'orange',
        ]];

        if ($role === 'super_admin') {
            return array_merge($employeeLinks, $contractLinks, $payrollLinks, $financeLinks, [[
                'title' => 'Manajemen Pengguna',
                'description' => 'Kelola akun dan akses sistem.',
                'icon' => 'fa-user-shield',
                'route' => 'users.index',
                'tone' => 'red',
            ]]);
        }

        if ($role === 'manager_keuangan') {
            return array_merge($employeeLinks, $contractLinks, $payrollLinks, $financeLinks);
        }

        if (in_array($role, self::HEAD_HRD_ROLES, true)) {
            return array_merge($employeeLinks, $contractLinks);
        }

        if (in_array($role, self::LIMITED_FINANCE_ROLES, true)) {
            // Role keuangan/finance hanya mendapat agregat dashboard dan tautan kontrak Local;
            // tautan detail payroll tidak diberikan dari dashboard.
            return [
                $contractLinks[0],
            ];
        }

        if (in_array($role, self::HRD_ROLES, true)) {
            return array_merge($employeeLinks, $contractLinks);
        }

        if (in_array($role, self::EMPLOYEE_ROLES, true)) {
            return [[
                'title' => 'Profil Saya',
                'description' => 'Periksa informasi profil akun.',
                'icon' => 'fa-user',
                'route' => 'profile.edit',
                'tone' => 'blue',
            ]];
        }

        return [];
    }
}
