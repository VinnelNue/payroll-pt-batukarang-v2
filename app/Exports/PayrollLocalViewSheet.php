<?php

namespace App\Exports;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PayrollLocalViewSheet implements FromView, WithEvents, WithTitle
{
    private readonly string $period;
    private readonly string $userRole;
    private readonly string $viewName;
    private readonly string $sheetTitle;
    private readonly bool $isCompact;

    public function __construct(
        string $period,
        string $userRole,
        string $viewName,
        string $sheetTitle,
        bool $isCompact = false,
    ) {
        $this->period = trim($period);
        $this->userRole = strtolower(trim($userRole));
        $this->viewName = $viewName;
        $this->sheetTitle = $sheetTitle;
        $this->isCompact = $isCompact;
    }

    public function view(): View
    {
        $periodDate = Carbon::createFromFormat('Y-m-d', $this->period . '-01');
        $startDate = $periodDate->copy()->startOfMonth();
        $endDate = $periodDate->copy()->endOfMonth();

        // Semua karyawan aktif tetap disertakan, termasuk yang belum memiliki payroll row.
        $employees = Employee::query()
            ->with([
                'contract.currentHistory',
                'attendanceRecords' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('attendance_date', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ])->orderBy('attendance_date');
                },
            ])
            ->where('is_active', true)
            ->get();

        $employeeIds = $employees->pluck('id_employee')->filter()->values();

        $payrollsByEmployee = Payroll::query()
            ->where('period_month', $this->period)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ])
            ->orderBy('holiday_date')
            ->get()
            ->keyBy(static fn ($holiday) => Carbon::parse($holiday->holiday_date)->format('Y-m-d'));

        // Prioritas cutoff: payroll tersimpan -> setting periode -> setting global -> 26.
        $cutoffDay = $payrollsByEmployee
            ->pluck('cutoff_day')
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn ($value) => $value >= 20 && $value <= 28)
            ->first();

        if ($cutoffDay === null) {
            $cutoffDay = (int) CompanySetting::get(
                'attendance_cutoff_day:' . $this->period,
                CompanySetting::get('attendance_cutoff_day', 26)
            );
        }

        $cutoffDay = min(28, max(20, (int) $cutoffDay));

        return view($this->viewName, [
            'employees' => $employees,
            'payrollsByEmployee' => $payrollsByEmployee,
            'holidays' => $holidays,
            'cutoffDay' => $cutoffDay,
            'period' => $this->period,
            'userRole' => $this->userRole,
        ]);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setShowGridlines(false);

                $isFinanceRole = in_array(
                    $this->userRole,
                    ['manager_keuangan', 'super_admin'],
                    true
                );
                $isHeadHrd = in_array(
                    $this->userRole,
                    ['head_hrd', 'kepala_hrd'],
                    true
                );

                $identityCount = $isFinanceRole ? 5 : 3;
                $firstAttendanceColumn = $identityCount + 1;
                $daysInMonth = Carbon::createFromFormat(
                    'Y-m-d',
                    $this->period . '-01'
                )->daysInMonth;
                $showBpjsColumns = $isFinanceRole || $isHeadHrd;

                // Finance melihat kolom keuangan di kedua worksheet.
                // Role lain mengikuti kolom yang dirender Blade masing-masing.
                $showFinancialColumns = $isFinanceRole;

                $sheet->freezePane(
                    Coordinate::stringFromColumnIndex($firstAttendanceColumn) . '6'
                );

                // Lebar kolom identitas.
                $sheet->getColumnDimension('A')->setWidth(4.5);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(40);

                if ($isFinanceRole) {
                    $sheet->getColumnDimension('D')->setWidth(8);
                    $sheet->getColumnDimension('E')->setWidth(6);
                }

                // Kalender harian.
                for ($i = 0; $i < $daysInMonth; $i++) {
                    $index = $firstAttendanceColumn + $i;
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($index)
                    )->setWidth(3.1);
                }

                // Total kolom ringkasan menyesuaikan worksheet ringkas/detail.
                $summaryWidths = $this->isCompact
                    ? [8, 10, 10, 8, 7, 9]
                    : [8, 10, 10, 8, 7, 7, 9, 9, 10, 9];

                $columnIndex = $firstAttendanceColumn + $daysInMonth;
                foreach ($summaryWidths as $width) {
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth($width);
                    $columnIndex++;
                }

                if ($showBpjsColumns) {
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth(15);
                    $columnIndex++;

                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth(15);
                    $columnIndex++;
                }

                if ($showFinancialColumns) {
                    foreach ([13, 13, 15, 13, 15, 14, 12, 14, 16] as $width) {
                        $sheet->getColumnDimension(
                            Coordinate::stringFromColumnIndex($columnIndex)
                        )->setWidth($width);
                        $columnIndex++;
                    }
                }

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setPaperSize(PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToPage(true);

                $sheet->getPageMargins()
                    ->setTop(0.25)
                    ->setRight(0.2)
                    ->setBottom(0.25)
                    ->setLeft(0.2);
            },
        ];
    }
}
