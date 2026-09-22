<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <title>
        Slip Gaji - {{ $payroll->employee->full_name }}
    </title>

    <style>
        @page {
            margin: 10px;
        }

        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .text-start {
            text-align: left;
        }

        .fw-bold {
            font-weight: bold;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .subtitle {
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
        }

        table {
            border-collapse: collapse;
        }

        table.full {
            width: 100%;
        }

        td {
            vertical-align: top;
        }

        .border {
            border: 1px solid #000;
        }

        .border-top {
            border-top: 1px solid #000;
        }

        .border-bottom {
            border-bottom: 1px solid #000;
        }

        .border-left {
            border-left: 1px solid #000;
        }

        .border-right {
            border-right: 1px solid #000;
        }

        .box {
            border: 1px solid #000;
            padding: 6px;
        }

        .inner {
            width: 100%;
        }

        .inner td {
            padding: 2px 2px;
        }

        .section-title {
            font-weight: bold;
            padding-bottom: 3px;
            margin-bottom: 3px;
            border-bottom: 1px solid #000;
        }

        .small {
            font-size: 9px;
        }

        .very-small {
            font-size: 8px;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .info-table td {
            padding: 2px 1px;
        }

        .main-table {
            width: 100%;
            border: 1px solid #000;
        }

        .main-left {
            width: 75%;
            border-right: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        .main-right {
            width: 25%;
            padding: 0;
            vertical-align: top;
        }

        .right-note {
            padding: 6px;
            min-height: 230px;
        }

        .right-signature {
            border-top: 1px solid #000;
            padding: 8px 5px 5px 5px;
            text-align: center;
            min-height: 100px;
        }

        .signature-space {
            height: 55px;
        }

        .salary-table {
            width: 100%;
        }

        .salary-table td {
            padding: 3px 2px;
        }

        .salary-name {
            width: 40%;
        }

        .salary-value {
            width: 25%;
        }

        .salary-minus {
            width: 10%;
            text-align: right;
        }

        .salary-total {
            width: 25%;
            text-align: right;
        }

        .deduction-table {
            width: 100%;
        }

        .deduction-table td {
            padding: 3px 2px;
        }

        .deduction-number {
            width: 4%;
        }

        .deduction-name {
            width: 56%;
        }

        .deduction-value {
            width: 40%;
            text-align: right;
        }

        .grand-total {
            font-size: 11px;
            font-weight: bold;
        }

        .terbilang {
            margin-top: 7px;
            border: 1px solid #000;
            padding: 6px;
            font-style: italic;
            font-weight: bold;
        }

        .info-note {
            margin-top: 5px;
            padding: 4px;
            border: 1px solid #000;
            font-size: 8px;
        }
    </style>
</head>

<body>

@php
    /*
    |--------------------------------------------------------------------------
    | DATA PAYROLL
    |--------------------------------------------------------------------------
    */

    $employee = $payroll->employee;
    $contract = $employee->activeContract;

    $isMaternity = (float) $payroll->maternity_leave_pay > 0;

    $basicSalary = (float) ($payroll->basic_salary ?? 0);
    $allowance = (float) ($payroll->allowance ?? 0);

    $totalGajiTetap = $basicSalary + $allowance;

    $overtimeHours = (float) ($payroll->overtime_hours ?? 0);
    $overtimePay = (float) ($payroll->overtime_pay ?? 0);

    $maternityPay = (float) ($payroll->maternity_leave_pay ?? 0);

    $grossSalary = (float) ($payroll->gross_salary ?? 0);

    /*
    |--------------------------------------------------------------------------
    | POTONGAN
    |--------------------------------------------------------------------------
    */

    $bpjsTk = (float) ($payroll->bpjs_tk_deduction ?? 0);
    $bpjsKs = (float) ($payroll->bpjs_ks_deduction ?? 0);
    $pph21 = (float) ($payroll->pph21_deduction ?? 0);

    $cashAdvance = (float) ($payroll->cash_advance ?? 0);
    $otherDeductions = (float) ($payroll->other_deductions ?? 0);

    /*
    |--------------------------------------------------------------------------
    | GANTUNGAN
    |--------------------------------------------------------------------------
    |
    | gantungan_deduction
    | = gantungan periode berjalan.
    |
    | previous_gantungan_deduction
    | = gantungan periode sebelumnya yang DIBAYARKAN/DIPOTONG
    |   pada payroll bulan ini.
    |
    */

    $currentGantunganDays = (float) ($payroll->gantungan_days ?? 0);

    $currentGantunganDeduction =
        (float) ($payroll->gantungan_deduction ?? 0);

    $previousGantunganDeduction =
        (float) ($payroll->previous_gantungan_deduction ?? 0);

    /*
    |--------------------------------------------------------------------------
    | TOTAL POTONGAN
    |--------------------------------------------------------------------------
    */

    $totalPotongan =
        $bpjsTk
        + $bpjsKs
        + $pph21
        + $previousGantunganDeduction
        + $cashAdvance
        + $otherDeductions;

    /*
    |--------------------------------------------------------------------------
    | NET SALARY
    |--------------------------------------------------------------------------
    */

    $netSalary = (float) ($payroll->net_salary ?? 0);
@endphp


<!-- ============================================================
     HEADER
============================================================= -->

<div class="text-center">

    <div class="title">
        PT. BATU KARANG - MALANG
    </div>

    <div class="subtitle">
        SLIP GAJI
        @if($isMaternity)
            (CUTI MELAHIRKAN)
        @endif
    </div>

</div>


<!-- ============================================================
     INFO PEGAWAI
     FULL WIDTH — TIDAK DIPOTONG CATATAN
============================================================= -->

<table class="full border" style="margin-bottom: 8px;">

    <tr>

        <td style="width: 16%; padding: 4px 6px;">
            Periode
        </td>

        <td style="width: 2%; padding: 4px 0;">
            :
        </td>

        <td style="width: 32%; padding: 4px 6px;" class="fw-bold">
            {{ $payroll->period_month }}
        </td>

        <td style="width: 16%; padding: 4px 6px;">
            Hari Kerja
        </td>

        <td style="width: 2%; padding: 4px 0;">
            :
        </td>

        <td style="width: 32%; padding: 4px 6px;">
            {{ number_format((float) $payroll->work_days, 1, ',', '.') }} hari

            @if($overtimeHours > 0)
                + Lembur {{ number_format($overtimeHours, 1, ',', '.') }} jam
            @endif
        </td>

    </tr>

    <tr>

        <td style="padding: 4px 6px;">
            Tidak masuk kerja
        </td>

        <td style="padding: 4px 0;">
            :
        </td>

        <td style="padding: 4px 6px;">
            {{ number_format((float) $payroll->unpaid_leave, 1, ',', '.') }} hari
        </td>

        <td style="padding: 4px 6px;">
            Nama
        </td>

        <td style="padding: 4px 0;">
            :
        </td>

        <td style="padding: 4px 6px;" class="fw-bold">
            {{ $employee->full_name }}
        </td>

    </tr>

    <tr>

        <td style="padding: 4px 6px;">
            Jabatan / Level
        </td>

        <td style="padding: 4px 0;">
            :
        </td>

        <td colspan="4" style="padding: 4px 6px;">
            {{ $contract->job_title ?? '-' }}
            /
            Lvl {{ $contract->level ?? '-' }}
        </td>

    </tr>

</table>


<!-- ============================================================
     MAIN CONTENT
     75% KIRI + 25% KANAN
============================================================= -->

<table class="main-table">

    <tr>

        <!-- ====================================================
             KIRI 75%
        ===================================================== -->

        <td class="main-left">


            <!-- ==================================================
                 GAJI TETAP
            =================================================== -->

            <div class="section-title">
                Gaji Tetap terdiri dari :
            </div>

            <table class="salary-table">

                <tr>

                    <td class="salary-name">
                        1. Gaji Pokok
                    </td>

                    <td class="salary-value">
                        Rp {{ number_format($basicSalary, 0, ',', '.') }}
                    </td>

                    <td class="salary-minus">
                        —
                    </td>

                    <td class="salary-total">
                        Rp {{ number_format($basicSalary, 0, ',', '.') }} / bln
                    </td>

                </tr>

                <tr>

                    <td class="salary-name">
                        2. Tunj. Jabatan
                    </td>

                    <td class="salary-value">
                        Rp {{ number_format($allowance, 0, ',', '.') }}
                    </td>

                    <td class="salary-minus">
                        —
                    </td>

                    <td class="salary-total">
                        Rp {{ number_format($allowance, 0, ',', '.') }} / bln
                    </td>

                </tr>

                <tr class="fw-bold border-top">

                    <td colspan="3" class="text-end">
                        Total Gaji Tetap =
                    </td>

                    <td class="salary-total">
                        Rp {{ number_format($totalGajiTetap, 0, ',', '.') }} / bln
                    </td>

                </tr>

            </table>


            <!-- ==================================================
                 PENGHASILAN TAMBAHAN
            =================================================== -->

            <div class="border-top" style="margin-top: 7px; padding-top: 5px;">

                <div class="fw-bold">
                    Penghasilan Tambahan :
                </div>

                <table class="salary-table">

                    @if($isMaternity)

                        <tr>

                            <td style="width: 65%;">
                                Gaji Normative Cuti Melahirkan
                            </td>

                            <td class="amount" style="width: 35%;">
                                Rp {{ number_format($maternityPay, 0, ',', '.') }}
                            </td>

                        </tr>

                        <tr>

                            <td>
                                Perhitungan {{ number_format($totalGajiTetap, 0, ',', '.') }}
                                ÷ 26 × 63 hari
                            </td>

                            <td class="amount">
                                Rp {{ number_format($maternityPay, 0, ',', '.') }}
                            </td>

                        </tr>

                    @else

                        <tr>

                            <td style="width: 65%;">
                                Lembur Hari Minggu / Libur Nasional
                            </td>

                            <td class="amount" style="width: 35%;">
                                Rp {{ number_format($overtimePay, 0, ',', '.') }}
                            </td>

                        </tr>

                        <tr>

                            <td>
                                {{ number_format($overtimeHours, 1, ',', '.') }}
                                jam × Rp 20.000 / jam
                            </td>

                            <td class="amount">
                                Rp {{ number_format($overtimePay, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endif

                </table>

            </div>


            <!-- ==================================================
                 CURRENT GANTUNGAN — INFORMASI SAJA
            =================================================== -->

            <div class="border-top" style="margin-top: 7px; padding-top: 5px;">

                <div class="fw-bold">
                    Gantungan Periode Berjalan :
                </div>

                <table class="salary-table">

                    <tr>

                        <td style="width: 65%;">
                            Gantungan
                        </td>

                        <td class="amount" style="width: 35%;">
                            {{ number_format($currentGantunganDays, 1, ',', '.') }} hari
                        </td>

                    </tr>

                    <tr>

                        <td class="small">
                            Tidak dipotong pada periode berjalan
                        </td>

                        <td class="amount small">
                            Rp {{ number_format($currentGantunganDeduction, 0, ',', '.') }}
                        </td>

                    </tr>

                </table>

            </div>


            <!-- ==================================================
                 TOTAL PENGHASILAN
            =================================================== -->

            <div class="border-top" style="margin-top: 7px; padding-top: 5px;">

                <table class="salary-table">

                    <tr class="fw-bold">

                        <td style="width: 65%;">
                            Total Penghasilan
                        </td>

                        <td class="amount" style="width: 35%;">
                            Rp {{ number_format($grossSalary, 0, ',', '.') }}
                        </td>

                    </tr>

                </table>

            </div>


            <!-- ==================================================
                 POTONGAN
            =================================================== -->

            <div class="border-top" style="margin-top: 7px; padding-top: 5px;">

                <div class="section-title">
                    Potongan :
                </div>

                <table class="deduction-table">

                    <!-- BPJS TK -->

                    <tr>

                        <td class="deduction-number">
                            1.
                        </td>

                        <td class="deduction-name">
                            BPJS Ketenagakerjaan
                        </td>

                        <td class="deduction-value">
                            Rp {{ number_format($bpjsTk, 0, ',', '.') }}
                        </td>

                    </tr>


                    <!-- BPJS KS -->

                    <tr>

                        <td class="deduction-number">
                            2.
                        </td>

                        <td class="deduction-name">
                            BPJS Kesehatan
                        </td>

                        <td class="deduction-value">
                            Rp {{ number_format($bpjsKs, 0, ',', '.') }}
                        </td>

                    </tr>


                    <!-- PPH21 -->

                    <tr>

                        <td class="deduction-number">
                            3.
                        </td>

                        <td class="deduction-name">
                            Pajak PPh 21
                        </td>

                        <td class="deduction-value">
                            Rp {{ number_format($pph21, 0, ',', '.') }}
                        </td>

                    </tr>


                    <!-- GANTUNGAN PERIODE SEBELUMNYA -->

                    @if($previousGantunganDeduction > 0)

                        <tr>

                            <td class="deduction-number">
                                4.
                            </td>

                            <td class="deduction-name">
                                Gantungan Periode Sebelumnya
                            </td>

                            <td class="deduction-value">
                                Rp {{ number_format($previousGantunganDeduction, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endif


                    <!-- KASBON -->

                    @if($cashAdvance > 0)

                        <tr>

                            <td class="deduction-number">
                                {{ $previousGantunganDeduction > 0 ? '5.' : '4.' }}
                            </td>

                            <td class="deduction-name">
                                Angsuran Pinjaman / Kasbon
                            </td>

                            <td class="deduction-value">
                                Rp {{ number_format($cashAdvance, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endif


                    <!-- OTHER DEDUCTION -->

                    @if($otherDeductions > 0)

                        @php
                            $otherNumber = 4;

                            if ($previousGantunganDeduction > 0) {
                                $otherNumber++;
                            }

                            if ($cashAdvance > 0) {
                                $otherNumber++;
                            }
                        @endphp

                        <tr>

                            <td class="deduction-number">
                                {{ $otherNumber }}.
                            </td>

                            <td class="deduction-name">
                                Potongan Lainnya
                            </td>

                            <td class="deduction-value">
                                Rp {{ number_format($otherDeductions, 0, ',', '.') }}
                            </td>

                        </tr>

                    @endif


                    <!-- TOTAL POTONGAN -->

                    <tr class="fw-bold border-top">

                        <td colspan="2" class="text-end">
                            Total Potongan =
                        </td>

                        <td class="deduction-value">
                            Rp {{ number_format($totalPotongan, 0, ',', '.') }}
                        </td>

                    </tr>


                    <!-- GAJI DIBAYARKAN -->

                    <tr class="fw-bold border-top grand-total">

                        <td colspan="2" class="text-end">
                            Gaji yang Dibayarkan =
                        </td>

                        <td class="deduction-value"
                            style="text-decoration: underline;">

                            Rp {{ number_format($netSalary, 0, ',', '.') }}

                        </td>

                    </tr>

                </table>

            </div>


        </td>


        <!-- ====================================================
             KANAN 25%
             CATATAN + TTD
        ===================================================== -->

        <td class="main-right">


            <!-- ==================================================
                 CATATAN
            =================================================== -->

            <div class="right-note">

                <div class="fw-bold"
                     style="border-bottom: 1px solid #000; padding-bottom: 4px;">

                    Catatan :

                </div>

                <div style="margin-top: 10px; line-height: 1.5;">

                    @if($isMaternity)

                        <div class="fw-bold">
                            Normative Cuti Melahirkan
                        </div>

                        <div style="margin-top: 4px;">
                            Hak normative cuti melahirkan
                            selama 63 hari.
                        </div>

                    @else

                        @if($currentGantunganDays > 0)

                            <div class="fw-bold">
                                Gantungan:
                            </div>

                            <div style="margin-top: 3px;">
                                {{ number_format($currentGantunganDays, 1, ',', '.') }}
                                hari pada periode
                                {{ $payroll->period_month }}.
                            </div>

                            <div style="margin-top: 5px;">
                                Gantungan periode berjalan
                                belum dipotong pada slip ini.
                            </div>

                        @else

                            <div>
                                -
                            </div>

                        @endif

                    @endif

                </div>

            </div>


            <!-- ==================================================
                 TANDA TANGAN
            =================================================== -->

            <div class="right-signature">

                <div class="fw-bold">
                    Manager Keuangan & Perpajakan II
                </div>

                <div class="signature-space"></div>

                <div class="fw-bold">
                    (Yohana)
                </div>

            </div>


        </td>

    </tr>

</table>


<!-- ============================================================
     TERBILANG
============================================================= -->

<div class="terbilang">

    Terbilang :
    # Rp {{ number_format($netSalary, 0, ',', '.') }} #

</div>


<!-- ============================================================
     FOOTER / KETERANGAN
============================================================= -->

<div class="info-note">

    <strong>Keterangan:</strong>

    Gantungan periode berjalan merupakan hari tidak dibayar
    setelah tanggal cutoff dan tidak dipotong pada periode ini.
    Gantungan tersebut akan menjadi potongan pada periode berikutnya
    sesuai perhitungan payroll.

</div>

</body>
</html>