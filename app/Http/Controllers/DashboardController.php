<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currentPeriod = date('Y-m');

        // 1. Stat Cards Data
        $totalEmployees = Employee::where('is_active', true)->count();
        
        $currentPayrolls = Payroll::where('period_month', $currentPeriod)->get();
        $totalGrossSalary = $currentPayrolls->sum('gross_salary');
        $totalPph21 = $currentPayrolls->sum('pph21_deduction');
        $totalBpjs = $currentPayrolls->sum('bpjs_tk_deduction') + $currentPayrolls->sum('bpjs_ks_deduction');

        // 2. Chart Data 1: Tren Payroll 6 Bulan Terakhir
        $monthlyTrends = Payroll::select(
                'period_month',
                DB::raw('SUM(gross_salary) as total_gross'),
                DB::raw('SUM(net_salary) as total_net')
            )
            ->groupBy('period_month')
            ->orderBy('period_month', 'asc')
            ->limit(6)
            ->get();

        $chartMonths = $monthlyTrends->pluck('period_month')->toArray();
        $chartGross = $monthlyTrends->pluck('total_gross')->toArray();
        $chartNet = $monthlyTrends->pluck('total_net')->toArray();

        // 3. Chart Data 2: Distribusi Karyawan per Category
        $categoryDistribution = EmployeeContract::where('is_active', true)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        // 4. Recent Payroll Status Table
        $recentPayrolls = Payroll::with('employee')
            ->where('period_month', $currentPeriod)
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalEmployees',
            'totalGrossSalary',
            'totalPph21',
            'totalBpjs',
            'chartMonths',
            'chartGross',
            'chartNet',
            'categoryDistribution',
            'recentPayrolls',
            'currentPeriod'
        ));
    }
}