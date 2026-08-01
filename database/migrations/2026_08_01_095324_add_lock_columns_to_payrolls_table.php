<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\SalarySlipMail;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    // Halaman Index: Daftar Riwayat Gajian per Periode
    public function index(Request $request)
    {
        $period = $request->get('period', date('Y-m'));

        $payrolls = Payroll::with('employee.activeContract')
            ->where('period_month', $period)
            ->get();

        return view('payrolls.index', compact('payrolls', 'period'));
    }

    // Halaman Form Input Absensi & Variabel Bulanan (/absensi/input)
    public function create(Request $request)
    {
        $period = $request->get('period', date('Y-m'));

        // Cek apakah periode ini sudah terkunci
        $isLocked = Payroll::where('period_month', $period)->where('is_locked', true)->exists();

        // Ambil karyawan aktif beserta data payroll periode terpilih
        $employees = Employee::with(['activeContract', 'payrolls' => function($q) use ($period) {
            $q->where('period_month', $period);
        }])
        ->where('is_active', true)
        ->get();

        return view('absensi.create', compact('employees', 'period', 'isLocked'));
    }

    // Import Rekap Absensi dari File Excel
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        return redirect()->route('absensi.create')
            ->with('success', 'File rekap absensi berhasil di-upload dan diproses!');
    }

    // Simpan & Hitung Otomatis Process Payroll
    public function store(Request $request)
    {
        $request->validate([
            'period_month' => 'required|string',
            'payrolls'     => 'required|array',
        ]);

        $period = $request->period_month;

        // Cegah penyimpanan jika periode sudah terkunci
        $isLocked = Payroll::where('period_month', $period)->where('is_locked', true)->exists();
        if ($isLocked) {
            return redirect()->back()->with('error', 'Gagal menyimpan! Kalkulasi Payroll periode ini sudah dikunci (Locked).');
        }

        foreach ($request->payrolls as $empId => $data) {
            $employee = Employee::with('activeContract')->find($empId);
            if (!$employee || !$employee->activeContract) continue;

            $contract = $employee->activeContract;

            // Helper pembersih format ribuan
            $cleanNumber = function ($value) {
                if (empty($value)) return 0;
                $cleaned = preg_replace('/[^0-9]/', '', (string)$value);
                return (float) $cleaned;
            };

            // 1. Clean Input Formatting
            $workDays          = (float) ($data['work_days'] ?? 27);
            $unpaidLeave       = (float) ($data['unpaid_leave'] ?? 0);
            $overtimeHours     = (float) ($data['overtime_hours'] ?? 0);
            $maternityLeavePay = $cleanNumber($data['maternity_leave_pay'] ?? 0);
            $incentive         = $cleanNumber($data['incentive'] ?? 0);
            $cashAdvance       = $cleanNumber($data['cash_advance'] ?? 0);
            $otherDeductions   = $cleanNumber($data['other_deductions'] ?? 0);

            // 2. Acuan Master Kontrak Karyawan
            $basicSalary = (float) $contract->basic_salary;
            $allowance   = (float) $contract->allowance;

            // 3. Kalkulasi Potongan Unpaid Leave (Pro-rata Hari Kerja)
            $basicSalaryDeduction = ($unpaidLeave > 0) ? ($basicSalary / 27) * $unpaidLeave : 0;
            $allowanceDeduction   = ($unpaidLeave > 0) ? ($allowance / 27) * $unpaidLeave : 0;

            $netBasicSalary = max(0, $basicSalary - $basicSalaryDeduction);
            $netAllowance   = max(0, $allowance - $allowanceDeduction);

            // 4. Kalkulasi Gaji Bruto
            $overtimePay = $overtimeHours * 20000;
            $grossSalary = $netBasicSalary + $netAllowance + $overtimePay + $maternityLeavePay + $incentive;

            // 5. Kalkulasi Potongan BPJS Dinamis
            $tkRate = (float) CompanySetting::get('bpjs_tk_employee_rate', 2.0) / 100;
            $ksRate = (float) CompanySetting::get('bpjs_ks_employee_rate', 1.0) / 100;
            $ksCap  = (float) CompanySetting::get('bpjs_ks_max_cap', 12000000);

            $bpjsTkDeduction = $contract->is_bpjstk_active ? ($basicSalary * $tkRate) : 0;
            
            $basisBpjsKs     = min($basicSalary, $ksCap);
            $bpjsKsDeduction = $contract->is_bpjs_health_active ? ($basisBpjsKs * $ksRate) : 0;

            // 6. TER PPh 21 Otomatis
            $pph21Rate      = $this->calculateTerRate($contract->ptkp_status ?? 'TK/0', $grossSalary);
            $pph21Deduction = $grossSalary * $pph21Rate;

            // 7. Hitung Take Home Pay (Net Salary)
            $totalDeductions = $bpjsTkDeduction + $bpjsKsDeduction + $pph21Deduction + $cashAdvance + $otherDeductions;
            $netSalary       = max(0, $grossSalary - $totalDeductions);

            // 8. Update / Create ke Database
            Payroll::updateOrCreate(
                [
                    'employee_id'  => $empId,
                    'period_month' => $period,
                ],
                [
                    'work_days'           => $workDays,
                    'unpaid_leave'        => $unpaidLeave,
                    'overtime_hours'      => $overtimeHours,
                    'basic_salary'        => $basicSalary,
                    'allowance'           => $allowance,
                    'overtime_pay'        => $overtimePay,
                    'maternity_leave_pay' => $maternityLeavePay,
                    'incentive'           => $incentive,
                    'cash_advance'        => $cashAdvance,
                    'other_deductions'    => $otherDeductions,
                    'bpjs_tk_deduction'   => $bpjsTkDeduction,
                    'bpjs_ks_deduction'   => $bpjsKsDeduction,
                    'pph21_deduction'     => $pph21Deduction,
                    'gross_salary'        => $grossSalary,
                    'net_salary'          => $netSalary,
                    'status'              => 'Draft',
                ]
            );
        }

        return redirect()->route('absensi.create', ['period' => $period])
            ->with('success', 'Data Absensi & Payroll Periode ' . $period . ' Berhasil Diproses!');
    }

   // Lock Calculation
    public function lockCalculation(Request $request)
    {
        $period = $request->input('period');

        // GANTI 'period' MENJADI 'period_month'
        Payroll::where('period_month', $period)->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => Auth::id(),
            'status'    => 'Approved',
        ]);

        return redirect()->back()->with('success', "Kalkulasi Payroll periode {$period} berhasil DIKUNCI (Locked)!");
    }

    // Unlock Calculation
    public function unlockCalculation(Request $request)
    {
        if (Auth::user()->role !== 'manager_keuangan') {
            return redirect()->back()->with('error', 'Hanya Manager Keuangan yang berhak membuka kuncian payroll!');
        }

        $period = $request->input('period');

        // GANTI 'period' MENJADI 'period_month'
        Payroll::where('period_month', $period)->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'status'    => 'Draft',
        ]);

        return redirect()->back()->with('success', "Kuncian Payroll periode {$period} berhasil DIBUKA kembali!");
    }

    // Export CSV BCA Mass Transfer
    public function exportBca(Request $request)
    {
        $period = $request->input('period', date('Y-m'));

        // GANTI 'period' MENJADI 'period_month'
        $payrolls = Payroll::with('employee')
            ->where('period_month', $period)
            ->get();

        $fileName = "Payroll_BCA_{$period}.csv";

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function() use ($payrolls) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['No Rekening', 'Nominal Transfer', 'Nama Pemilik Rekening', 'Keterangan']);

            foreach ($payrolls as $p) {
                $bankAcc = $p->employee->bank_account_number ?? '0000000000';
                $netSal  = $p->net_salary ?? 0;
                $name    = $p->employee->full_name ?? 'Karyawan';
                $remark  = "Gaji " . $p->period_month;

                fputcsv($file, [$bankAcc, $netSal, $name, $remark]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Download / Preview Slip Gaji PDF
    public function printPdf($id)
    {
        $payroll = Payroll::with(['employee.activeContract'])->findOrFail($id);

        $pdf = Pdf::loadView('payrolls.pdf_slip', compact('payroll'))->setPaper('a4', 'portrait');

        return $pdf->stream('Slip_Gaji_' . $payroll->employee->full_name . '_' . $payroll->period_month . '.pdf');
    }

    // Kirim Slip Gaji PDF ke Email Karyawan
    public function sendEmail($id)
    {
        $payroll = Payroll::with(['employee.activeContract'])->findOrFail($id);
        
        $emailDestination = $payroll->employee->email;

        if (!$emailDestination) {
            return redirect()->back()->with('error', 'Email karyawan ' . $payroll->employee->full_name . ' belum diisi di Master Karyawan!');
        }

        $pdfBinary = Pdf::loadView('payrolls.pdf_slip', compact('payroll'))->setPaper('a4', 'portrait')->output();

        Mail::to($emailDestination)->send(new SalarySlipMail($payroll, $pdfBinary));

        return redirect()->back()->with('success', 'Slip Gaji berhasil dikirim ke email: ' . $emailDestination);
    }

    // Master TER PPh 21 & BPJS
    public function taxBpjsMaster()
    {
        $bpjsSettings = [
            'bpjs_tk_rate' => CompanySetting::get('bpjs_tk_employee_rate', 2.0),
            'bpjs_ks_rate' => CompanySetting::get('bpjs_ks_employee_rate', 1.0),
            'bpjs_ks_cap'  => CompanySetting::get('bpjs_ks_max_cap', 12000000),
        ];

        $terCategories = [
            'TER_A' => [
                'ptkp' => 'TK/0, TK/1, K/0',
                'description' => 'Tidak Kawin Tanggungan 0-1, atau Kawin Tanggungan 0',
                'brackets' => [
                    ['max' => 5400000, 'rate' => 0.00],
                    ['max' => 5650000, 'rate' => 0.25],
                    ['max' => 5950000, 'rate' => 0.50],
                    ['max' => 6300000, 'rate' => 0.75],
                    ['max' => 6750000, 'rate' => 1.25],
                    ['max' => 7500000, 'rate' => 1.75],
                    ['max' => 'Seterusnya', 'rate' => 2.50],
                ]
            ],
            'TER_B' => [
                'ptkp' => 'TK/2, TK/3, K/1, K/2',
                'description' => 'Tidak Kawin Tanggungan 2-3, atau Kawin Tanggungan 1-2',
                'brackets' => [
                    ['max' => 6200000, 'rate' => 0.00],
                    ['max' => 6500000, 'rate' => 0.25],
                    ['max' => 7000000, 'rate' => 0.50],
                    ['max' => 'Seterusnya', 'rate' => 1.50],
                ]
            ],
            'TER_C' => [
                'ptkp' => 'K/3',
                'description' => 'Kawin Tanggungan 3',
                'brackets' => [
                    ['max' => 6600000, 'rate' => 0.00],
                    ['max' => 'Seterusnya', 'rate' => 1.25],
                ]
            ],
        ];

        return view('payrolls.tax_bpjs_master', compact('terCategories', 'bpjsSettings'));
    }

    // Update Setting BPJS
    public function updateBpjsSetting(Request $request)
    {
        $request->validate([
            'bpjs_tk_employee_rate' => 'required|numeric|min:0|max:100',
            'bpjs_ks_employee_rate' => 'required|numeric|min:0|max:100',
            'bpjs_ks_max_cap'        => 'required|numeric|min:0',
        ]);

        CompanySetting::updateOrCreate(['key' => 'bpjs_tk_employee_rate'], ['value' => $request->bpjs_tk_employee_rate]);
        CompanySetting::updateOrCreate(['key' => 'bpjs_ks_employee_rate'], ['value' => $request->bpjs_ks_employee_rate]);
        CompanySetting::updateOrCreate(['key' => 'bpjs_ks_max_cap'], ['value' => $request->bpjs_ks_max_cap]);

        return redirect()->back()->with('success', 'Parameter BPJS Berhasil Diperbarui!');
    }

/**
     * Helper Hitung Persentase TER PPh 21 (PP 58/2023)
     */
    private function calculateTerRate($ptkp, $gross)
    {
        // 1. Tentukan Kategori TER berdasarkan Status PTKP
        $category = match($ptkp) {
            'TK/0', 'TK/1', 'K/0' => 'TER_A',
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'TER_B',
            'K/3' => 'TER_C',
            default => 'TER_A'
        };

        // 2. Logika TER A (TK/0, TK/1, K/0)
        if ($category === 'TER_A') {
            if ($gross <= 5400000) return 0.00;
            if ($gross <= 5650000) return 0.0025;
            if ($gross <= 5950000) return 0.005;
            if ($gross <= 6300000) return 0.0075;
            if ($gross <= 6750000) return 0.0125;
            if ($gross <= 7500000) return 0.0175;
            return 0.025; // > 7.500.000
        }

        // 3. Logika TER B (TK/2, TK/3, K/1, K/2)
        if ($category === 'TER_B') {
            if ($gross <= 6200000) return 0.00;
            if ($gross <= 6500000) return 0.0025;
            if ($gross <= 7000000) return 0.005;
            if ($gross <= 8000000) return 0.01;
            if ($gross <= 9200000) return 0.015;
            return 0.025; // > 9.200.000
        }

        // 4. Logika TER C (K/3)
        if ($category === 'TER_C') {
            if ($gross <= 6600000) return 0.00;
            if ($gross <= 6950000) return 0.0025;
            if ($gross <= 7350000) return 0.005;
            if ($gross <= 8200000) return 0.0075;
            if ($gross <= 9650000) return 0.0125;
            return 0.0175; // > 9.650.000
        }

        return 0.00;
    }
}