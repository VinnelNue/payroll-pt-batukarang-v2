<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $payroll->employee->full_name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #000; margin: 0; padding: 10px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .title { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { font-size: 13px; font-weight: bold; text-decoration: underline; margin-bottom: 12px; }
        
        table.outer { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.outer td { vertical-align: top; }
        
        .border-box { border: 1px solid #000; padding: 6px; }
        .border-bottom { border-bottom: 1px solid #000; }
        .border-top { border-top: 1px solid #000; }
        
        table.inner { width: 100%; border-collapse: collapse; }
        table.inner td { padding: 3px 2px; }
        
        .terbilang { border: 1px solid #000; padding: 5px; font-style: italic; font-weight: bold; margin-top: 5px; }
        .signature-box { border: 1px solid #000; height: 100px; text-align: center; }
    </style>
</head>
<body>

@php
    $isMaternity = $payroll->maternity_leave_pay > 0;
    $contract = $payroll->employee->activeContract;
    $basicSalary = $payroll->basic_salary;
    $allowance = $payroll->allowance;
    $totalGaji = $basicSalary + $allowance;
@endphp

<div class="text-center">
    <div class="title">PT. BATU KARANG - MALANG</div>
    <div class="subtitle">SLIP GAJI {{ $isMaternity ? '(CUTI MELAHIRKAN)' : '' }}</div>
</div>

<!-- INFO PEGAWAI & CATATAN -->
<table class="outer">
    <tr>
        <td style="width: 65%; padding-right: 10px;">
            <table class="inner border-box">
                <tr>
                    <td style="width: 25%;">Periode</td>
                    <td style="width: 5%;">:</td>
                    <td>{{ $payroll->period_month }}</td>
                </tr>
                <tr>
                    <td>Hari Kerja</td>
                    <td>:</td>
                    <td>{{ $payroll->work_days }} hari @if($payroll->overtime_hours > 0) + Lembur {{ $payroll->overtime_hours }} jam @endif</td>
                </tr>
                <tr>
                    <td>Tidak masuk kerja</td>
                    <td>:</td>
                    <td>{{ $payroll->unpaid_leave }} hari</td>
                </tr>
                <tr>
                    <td>Nama</td>
                    <td>:</td>
                    <td class="fw-bold">{{ $payroll->employee->full_name }}</td>
                </tr>
                <tr>
                    <td>Jabatan / Level</td>
                    <td>:</td>
                    <td>{{ $contract->job_title ?? '-' }} / Lvl {{ $contract->level ?? '-' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 35%;">
            <div class="border-box signature-box">
                <div class="fw-bold">Catatan :</div>
                <div style="margin-top: 15px;">
                    @if($isMaternity)
                        Normative Cuti Melahirkan 63 hari
                    @else
                        -
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

<!-- DETAIL KOMPONEN GAJI -->
<div class="border-box">
    <div class="fw-bold border-bottom" style="padding-bottom: 3px;">Gaji Tetap terdiri dari :</div>
    <table class="inner" style="margin-top: 5px;">
        <tr>
            <td style="width: 30%;">1. Gaji Pokok</td>
            <td style="width: 20%;">: Rp {{ number_format($basicSalary, 0, ',', '.') }}</td>
            <td style="width: 15%;">— Rp 0</td>
            <td class="text-end" style="width: 35%;">= Rp {{ number_format($basicSalary, 0, ',', '.') }} / bln</td>
        </tr>
        <tr>
            <td>2. Tunj. Jabatan</td>
            <td>: Rp {{ number_format($allowance, 0, ',', '.') }}</td>
            <td>— Rp 0</td>
            <td class="text-end">= Rp {{ number_format($allowance, 0, ',', '.') }} / bln +</td>
        </tr>
        <tr class="fw-bold border-top">
            <td colspan="3" class="text-end">Total Gaji =</td>
            <td class="text-end">Rp {{ number_format($totalGaji, 0, ',', '.') }} / bln</td>
        </tr>
    </table>

    @if($isMaternity)
    <!-- KHUSUS CUTI MELAHIRKAN / NORMATIVE -->
    <div class="border-top" style="margin-top: 8px; padding-top: 5px;">
        <div class="fw-bold">Perhitungan Gaji Normative (Cuti Melahirkan) :</div>
        <table class="inner">
            <tr>
                <td>Rp {{ number_format($totalGaji, 0, ',', '.') }} dibagi 26 hari kerja X 63 hari</td>
                <td class="text-end fw-bold">= Rp {{ number_format($payroll->maternity_leave_pay, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
    @else
    <!-- REGULAR LEMBUR -->
    <div class="border-top" style="margin-top: 8px; padding-top: 5px;">
        <div class="fw-bold">Lembur Hari Minggu / Libur Nasional :</div>
        <table class="inner">
            <tr>
                <td>= {{ $payroll->overtime_hours }} jam X Rp 20.000 / jam</td>
                <td class="text-end fw-bold">= Rp {{ number_format($payroll->overtime_pay, 0, ',', '.') }} / bln +</td>
            </tr>
            <tr class="fw-bold border-top">
                <td class="text-end">Total Penghasilan =</td>
                <td class="text-end">Rp {{ number_format($payroll->gross_salary, 0, ',', '.') }} / bln</td>
            </tr>
        </table>
    </div>
    @endif

    <!-- POTONGAN -->
    <div class="border-top" style="margin-top: 8px; padding-top: 5px;">
        <div class="fw-bold">Potongan :</div>
        <table class="inner">
            <tr>
                <td style="width: 50%;">1. BPJS Ketenagakerjaan</td>
                <td class="text-end" style="width: 50%;">= Rp {{ number_format($payroll->bpjs_tk_deduction, 0, ',', '.') }} / bln</td>
            </tr>
            <tr>
                <td>2. BPJS Kesehatan</td>
                <td class="text-end">= Rp {{ number_format($payroll->bpjs_ks_deduction, 0, ',', '.') }} / bln</td>
            </tr>
            <tr>
                <td>3. Pajak PPh 21</td>
                <td class="text-end">= Rp {{ number_format($payroll->pph21_deduction, 0, ',', '.') }} / bln</td>
            </tr>
            <tr>
                <td>4. Angsuran Pinjaman / Kasbon / Lainnya</td>
                <td class="text-end">= Rp {{ number_format($payroll->cash_advance + $payroll->other_deductions, 0, ',', '.') }} / bln -</td>
            </tr>
            <tr class="fw-bold border-top" style="font-size: 12px;">
                <td class="text-end">Gaji yang dibayarkan =</td>
                <td class="text-end" style="text-decoration: underline;">Rp {{ number_format($payroll->net_salary, 0, ',', '.') }} / bln</td>
            </tr>
        </table>
    </div>
</div>

<!-- TERBILANG -->
<div class="terbilang">
    Terbilang : # Rp {{ number_format($payroll->net_salary, 0, ',', '.') }} #
</div>

<!-- TANDA TANGAN -->
<table class="outer" style="margin-top: 15px;">
    <tr>
        <td style="width: 60%;"></td>
        <td style="width: 40%; text-align: center;">
            <div>Manager Keuangan & Perpajakan II</div>
            <div style="height: 50px;"></div>
            <div class="fw-bold">(Yohana)</div>
        </td>
    </tr>
</table>

</body>
</html>