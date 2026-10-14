<?php

namespace App\Exports\OuterIsland;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OuterIslandPayrollExport implements WithMultipleSheets
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

    public function sheets(): array
    {
        $shared = [
            $this->employees,
            $this->payrollsByEmployee,
            $this->holidays,
            $this->period,
            $this->userRole,
            $this->cutoffDay,
        ];

        return [
            new OuterIslandPayrollRecapSheet(...$shared),
            new OuterIslandPayrollDetailSheet(...$shared),
        ];
    }
}
