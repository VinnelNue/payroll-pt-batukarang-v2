<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\ContractLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currentPeriod = date('Y-m');

        /*
        |--------------------------------------------------------------------------
        | 1. STAT CARDS DATA
        |--------------------------------------------------------------------------
        */

        $totalEmployees = Employee::where('is_active', true)
            ->count();


        $currentPayrolls = Payroll::where(
            'period_month',
            $currentPeriod
        )->get();


        $totalGrossSalary = $currentPayrolls
            ->sum('gross_salary');


        $totalPph21 = $currentPayrolls
            ->sum('pph21_deduction');


        $totalBpjs =
            $currentPayrolls->sum('bpjs_tk_deduction')
            +
            $currentPayrolls->sum('bpjs_ks_deduction');


        /*
        |--------------------------------------------------------------------------
        | 2. CHART DATA
        | TREND PAYROLL 6 BULAN TERAKHIR
        |--------------------------------------------------------------------------
        */

        $monthlyTrends = Payroll::select(
                'period_month',
                DB::raw('SUM(gross_salary) as total_gross'),
                DB::raw('SUM(net_salary) as total_net')
            )
            ->groupBy('period_month')
            ->orderBy('period_month', 'asc')
            ->limit(6)
            ->get();


        $chartMonths = $monthlyTrends
            ->pluck('period_month')
            ->toArray();


        $chartGross = $monthlyTrends
            ->pluck('total_gross')
            ->toArray();


        $chartNet = $monthlyTrends
            ->pluck('total_net')
            ->toArray();


        /*
        |--------------------------------------------------------------------------
        | 3. CHART DATA
        | DISTRIBUSI KARYAWAN PER CATEGORY
        |--------------------------------------------------------------------------
        |
        | STRUKTUR BARU:
        |
        | employee_contracts
        |        ↓
        | employee_contract_histories
        |        ↓
        | category
        |
        | Category TIDAK lagi berada di:
        |
        | employee_contracts
        |
        | Karena itu kita mengambil category dari
        | current contract history.
        |
        |--------------------------------------------------------------------------
        */

        $categoryDistribution = ContractLocal::query()
            ->where('employee_contracts.is_active', true)
            ->join(
                'employee_contract_histories',
                'employee_contracts.current_contract_history_id',
                '=',
                'employee_contract_histories.id_contract_history'
            )
            ->where(
                'employee_contract_histories.is_active',
                true
            )
            ->select(
                'employee_contract_histories.category',
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull(
                'employee_contract_histories.category'
            )
            ->groupBy(
                'employee_contract_histories.category'
            )
            ->pluck(
                'count',
                'category'
            )
            ->toArray();


        /*
        |--------------------------------------------------------------------------
        | 4. RECENT PAYROLL STATUS
        |--------------------------------------------------------------------------
        */

        $recentPayrolls = Payroll::with('employee')
            ->where(
                'period_month',
                $currentPeriod
            )
            ->orderBy(
                'updated_at',
                'desc'
            )
            ->take(5)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | 5. DASHBOARD VIEW
        |--------------------------------------------------------------------------
        */

        return view(
            'dashboard',
            compact(
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
            )
        );
    }
}