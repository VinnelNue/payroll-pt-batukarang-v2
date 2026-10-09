<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Workbook payroll lokal dengan dua worksheet:
 * - Rekap Absensi: mengikuti layout ringkas referensi.
 * - Detail Lengkap: memuat rekap tambahan dan kolom finansial sesuai role.
 *
 * File ini HARUS mendeklarasikan PayrollLocalExport, bukan PayrollLocalViewSheet.
 */
class PayrollLocalExport implements WithMultipleSheets
{
    private readonly string $period;

    private readonly string $userRole;

    public function __construct(string $period, string $userRole)
    {
        $this->period = trim($period);
        $this->userRole = strtolower(trim($userRole));
    }

    public function sheets(): array
    {
        return [
            new PayrollLocalViewSheet(
                $this->period,
                $this->userRole,
                'payrolls.local.export_excel',
                'Rekap Absensi',
                true,
            ),
            new PayrollLocalViewSheet(
                $this->period,
                $this->userRole,
                'payrolls.local.export_excel_detail',
                'Detail Lengkap',
                false,
            ),
        ];
    }
}
