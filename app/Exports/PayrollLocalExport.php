<?php

namespace App\Exports;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PayrollLocalExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        private readonly string $period,
        private readonly string $userRole
    ) {
    }

    public function view(): View
    {
        $periodDate = Carbon::createFromFormat(
            'Y-m-d',
            $this->period . '-01'
        );

        $startDate = $periodDate->copy()->startOfMonth();
        $endDate = $periodDate->copy()->endOfMonth();

        /*
         * PENTING:
         * Ambil SEMUA KARYAWAN AKTIF.
         * Jangan mengambil daftar employee dari payrolls karena employee
         * yang belum punya payroll row akan hilang dari Excel.
         */
        $employees = Employee::query()
            ->with([
                'contract.currentHistory',
                'attendanceRecords' => function ($query) use (
                    $startDate,
                    $endDate
                ) {
                    $query
                        ->whereBetween(
                            'attendance_date',
                            [
                                $startDate->format('Y-m-d'),
                                $endDate->format('Y-m-d'),
                            ]
                        )
                        ->orderBy('attendance_date');
                },
            ])
            ->where('is_active', true)
            ->get();

        $employeeIds = $employees
            ->pluck('id_employee')
            ->filter()
            ->values();

        /*
         * Payroll hanya sebagai sumber angka payroll yang memang sudah
         * tersimpan. Kalau belum ada, view tetap membuat baris employee
         * dengan nilai finansial 0 dan absensi dari AttendanceRecord.
         */
        $payrollsByEmployee = Payroll::query()
            ->where('period_month', $this->period)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        /*
         * Holiday tetap digunakan sebagai HB ketika tidak ada explicit
         * AttendanceRecord pada tanggal tersebut.
         */
        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereBetween(
                'holiday_date',
                [
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d'),
                ]
            )
            ->orderBy('holiday_date')
            ->get()
            ->keyBy(function ($holiday) {
                return Carbon::parse(
                    $holiday->holiday_date
                )->format('Y-m-d');
            });

        /*
         * Cut-off:
         * payroll periode -> setting periode -> setting global -> 26.
         */
        $cutoffDay = $payrollsByEmployee
            ->pluck('cutoff_day')
            ->filter(
                fn ($value) => $value !== null
            )
            ->map(
                fn ($value) => (int) $value
            )
            ->filter(
                fn ($value) => $value >= 20 && $value <= 28
            )
            ->first();

        if ($cutoffDay === null) {
            $cutoffDay = (int) CompanySetting::get(
                'attendance_cutoff_day:' . $this->period,
                CompanySetting::get(
                    'attendance_cutoff_day',
                    26
                )
            );
        }

        $cutoffDay = min(
            28,
            max(20, (int) $cutoffDay)
        );

        return view(
            'payrolls.local.export_excel',
            [
                'employees' => $employees,
                'payrollsByEmployee' => $payrollsByEmployee,
                'holidays' => $holidays,
                'cutoffDay' => $cutoffDay,
                'period' => $this->period,
                'userRole' => $this->userRole,
            ]
        );
    }

    public function title(): string
    {
        return 'Rekap Payroll';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                /*
                 * Kolom identitas dibekukan.
                 * Untuk Head HRD/HRD level disembunyikan sehingga posisi
                 * kolom setelah identity bergeser; F5 tetap aman sebagai
                 * area kiri tetap saat scroll horizontal.
                 */
                $sheet->freezePane('F7');

                $sheet->getPageSetup()
                    ->setOrientation(
                        PageSetup::ORIENTATION_LANDSCAPE
                    );

                $sheet->getPageSetup()
                    ->setFitToWidth(1);

                $sheet->getPageSetup()
                    ->setFitToHeight(0);

                $sheet->getPageMargins()
                    ->setTop(0.25)
                    ->setRight(0.25)
                    ->setBottom(0.25)
                    ->setLeft(0.25);
            },
        ];
    }
}
