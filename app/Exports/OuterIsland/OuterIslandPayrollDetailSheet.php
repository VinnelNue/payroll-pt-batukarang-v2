<?php

namespace App\Exports\OuterIsland;

use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class OuterIslandPayrollDetailSheet implements FromView, WithTitle
{
    public function __construct(
        private Collection $employees,
        private Collection $payrollsByEmployee,
        private Collection $holidays,
        private string $period,
        private string $userRole,
        private int $cutoffDay,
    ) {
    }

    public function view(): View
    {
        return view('payrolls.outer_island.exports.detail', [
            'employees' => $this->employees,
            'payrollsByEmployee' => $this->payrollsByEmployee,
            'holidays' => $this->holidays,
            'period' => $this->period,
            'userRole' => $this->userRole,
            'cutoffDay' => $this->cutoffDay,
        ]);
    }

    public function title(): string
    {
        return 'Detail Lengkap';
    }
}
