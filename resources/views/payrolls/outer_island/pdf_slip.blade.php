<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>
        Slip Gaji {{ $employee->full_name_outer ?? '-' }}
    </title>

    <style>

        @page {
            size: A4 landscape;
            margin: 12px 14px 12px 14px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #111;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            vertical-align: middle;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        .border {
            border: 1px solid #555;
        }

        .border-top {
            border-top: 1px solid #555;
        }

        .border-bottom {
            border-bottom: 1px solid #555;
        }

        .border-left {
            border-left: 1px solid #555;
        }

        .border-right {
            border-right: 1px solid #555;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {
            width: 100%;
            margin-bottom: 4px;
        }

        .header-company {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            line-height: 16px;
        }

        .header-title {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | PERIOD
        |--------------------------------------------------------------------------
        */

        .period-table {
            width: 100%;
            margin-bottom: 2px;
        }

        .period-table td {
            height: 15px;
            padding: 1px 3px;
        }

        .period-label {
            width: 8%;
        }

        .period-separator {
            width: 2%;
            text-align: center;
        }

        .period-value {
            width: 34%;
        }

        .period-right {
            width: 22%;
            text-align: center;
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN GRID
        |--------------------------------------------------------------------------
        */

        .main-grid {
            width: 100%;
            table-layout: fixed;
        }

        .main-left {
            width: 50%;
            padding-right: 5px;
            vertical-align: top;
        }

        .main-middle {
            width: 24%;
            padding: 0 5px;
            vertical-align: top;
        }

        .main-right {
            width: 26%;
            padding-left: 5px;
            vertical-align: top;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE
        |--------------------------------------------------------------------------
        */

        .employee-table {
            width: 100%;
            margin-bottom: 5px;
        }

        .employee-table td {
            height: 15px;
            padding: 1px 3px;
        }

        .employee-label {
            width: 18%;
        }

        .employee-separator {
            width: 3%;
            text-align: center;
        }

        .employee-value {
            width: 79%;
        }

        /*
        |--------------------------------------------------------------------------
        | SALARY
        |--------------------------------------------------------------------------
        */

        .salary-title {
            font-weight: bold;
            margin-top: 3px;
            margin-bottom: 2px;
        }

        .salary-table {
            width: 100%;
        }

        .salary-table td {
            height: 16px;
            padding: 1px 3px;
        }

        .salary-no {
            width: 5%;
            text-align: center;
        }

        .salary-description {
            width: 39%;
        }

        .salary-formula {
            width: 17%;
            text-align: center;
        }

        .salary-equals {
            width: 4%;
            text-align: center;
        }

        .salary-amount {
            width: 35%;
            text-align: right;
            white-space: nowrap;
        }

        .subtotal {
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | OVERTIME
        |--------------------------------------------------------------------------
        */

        .overtime-table {
            margin-top: 4px;
        }

        .overtime-table td {
            height: 16px;
            padding: 1px 3px;
        }

        /*
        |--------------------------------------------------------------------------
        | DEDUCTIONS
        |--------------------------------------------------------------------------
        */

        .deduction-title {
            font-weight: bold;
            margin-top: 5px;
            margin-bottom: 2px;
        }

        .deduction-table {
            width: 100%;
        }

        .deduction-table td {
            height: 16px;
            padding: 1px 3px;
        }

        .deduction-no {
            width: 5%;
            text-align: center;
        }

        .deduction-description {
            width: 61%;
        }

        .deduction-equals {
            width: 4%;
            text-align: center;
        }

        .deduction-amount {
            width: 30%;
            text-align: right;
            white-space: nowrap;
        }

        .payable {
            font-weight: bold;
            font-size: 9px;
        }

        /*
        |--------------------------------------------------------------------------
        | TERBILANG
        |--------------------------------------------------------------------------
        */

        .terbilang-table {
            margin-top: 4px;
        }

        .terbilang-table td {
            padding: 3px;
            min-height: 18px;
        }

        /*
        |--------------------------------------------------------------------------
        | CATATAN
        |--------------------------------------------------------------------------
        */

        .notes-box {
            width: 100%;
            height: 245px;
            border: 1px solid #555;
        }

        .notes-title {
            font-weight: bold;
            padding: 4px;
            height: 20px;
        }

        .notes-content {
            height: 170px;
            vertical-align: top;
            padding: 4px;
        }

        .notes-signature {
            height: 55px;
            text-align: center;
            vertical-align: bottom;
            padding-bottom: 3px;
        }

        .signature-space {
            height: 28px;
        }

        /*
        |--------------------------------------------------------------------------
        | RIGHT SIDE NOTES
        |--------------------------------------------------------------------------
        */

        .right-note {
            border: 1px solid #555;
            margin-bottom: 5px;
            min-height: 58px;
            padding: 5px;
            vertical-align: top;
        }

        .right-numbered {
            border: 0;
            padding: 2px 4px;
            min-height: 18px;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 5px;
            font-size: 6px;
            color: #555;
            text-align: center;
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

    $basicSalary = (float) ($payroll->basic_salary ?? 0);

    $allowance = (float) ($payroll->allowance ?? 0);

    $overtimePay = (float) ($payroll->overtime_pay ?? 0);

    $incentive = (float) ($payroll->incentive ?? 0);

    $maternityPay = (float) ($payroll->maternity_leave_pay ?? 0);

    $grossSalary = (float) ($payroll->gross_salary ?? 0);

    $bpjsTk = (float) ($payroll->bpjs_tk_deduction ?? 0);

    $bpjsKs = (float) ($payroll->bpjs_ks_deduction ?? 0);

    $pph21 = (float) ($payroll->pph21_deduction ?? 0);

    $cashAdvance = (float) ($payroll->cash_advance ?? 0);

    $otherDeductions = (float) ($payroll->other_deductions ?? 0);

    $gantungan = (float) ($payroll->gantungan_deduction ?? 0);

    $previousGantungan =
        (float) ($payroll->previous_gantungan_deduction ?? 0);

    $netSalary = (float) ($payroll->net_salary ?? 0);

    $totalDeduction =
        $bpjsTk
        + $bpjsKs
        + $pph21
        + $cashAdvance
        + $otherDeductions
        + $gantungan
        + $previousGantungan;


    /*
    |--------------------------------------------------------------------------
    | PERIOD
    |--------------------------------------------------------------------------
    */

    try {

        $periodDate = \Carbon\Carbon::createFromFormat(
            'Y-m',
            $period
        );

        $periodStart = $periodDate->copy()->startOfMonth();

        $periodEnd = $periodDate->copy()->endOfMonth();

    } catch (\Throwable $e) {

        $periodDate = now();

        $periodStart = now()->startOfMonth();

        $periodEnd = now()->endOfMonth();
    }


    $months = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $periodText =
        $periodStart->day
        . ' - '
        . $periodEnd->day
        . ' '
        . $months[(int) $periodEnd->month]
        . ' '
        . $periodEnd->year;


    $dateText =
        $periodEnd->locale('id')
            ->translatedFormat('l, d F Y');


    /*
    |--------------------------------------------------------------------------
    | TERBILANG
    |--------------------------------------------------------------------------
    */

    $terbilang = function ($angka) use (&$terbilang) {

        $angka = (int) $angka;

        $nama = [
            '',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
            'sepuluh',
            'sebelas',
        ];

        if ($angka < 12) {
            return $nama[$angka];
        }

        if ($angka < 20) {
            return
                $terbilang($angka - 10)
                . ' belas';
        }

        if ($angka < 100) {
            return
                $terbilang(intdiv($angka, 10))
                . ' puluh '
                . $terbilang($angka % 10);
        }

        if ($angka < 200) {
            return
                'seratus '
                . $terbilang($angka - 100);
        }

        if ($angka < 1000) {
            return
                $terbilang(intdiv($angka, 100))
                . ' ratus '
                . $terbilang($angka % 100);
        }

        if ($angka < 2000) {
            return
                'seribu '
                . $terbilang($angka - 1000);
        }

        if ($angka < 1000000) {
            return
                $terbilang(intdiv($angka, 1000))
                . ' ribu '
                . $terbilang($angka % 1000);
        }

        if ($angka < 1000000000) {
            return
                $terbilang(intdiv($angka, 1000000))
                . ' juta '
                . $terbilang($angka % 1000000);
        }

        if ($angka < 1000000000000) {
            return
                $terbilang(intdiv($angka, 1000000000))
                . ' miliar '
                . $terbilang($angka % 1000000000);
        }

        return 'angka terlalu besar';
    };


    $terbilangNet = $netSalary <= 0
        ? 'Nol'
        : ucfirst(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $terbilang($netSalary)
                )
            )
        );


    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $workDays = (float) ($payroll->work_days ?? 0);

    $unpaidLeave = (float) ($payroll->unpaid_leave ?? 0);

    $overtimeHours = (float) ($payroll->overtime_hours ?? 0);

    $gantunganDays =
        (float) ($payroll->gantungan_days ?? 0);

@endphp


{{-- =========================================================
     HEADER
========================================================= --}}

<table class="header">

    <tr>
        <td class="header-company">
            PT. BATU KARANG - MALANG
        </td>
    </tr>

    <tr>
        <td class="header-title">
            SLIP GAJI
        </td>
    </tr>

</table>


{{-- =========================================================
     PERIOD
========================================================= --}}

<table class="period-table">

    <tr>

        <td class="period-label bold">
            Periode
        </td>

        <td class="period-separator">
            :
        </td>

        <td class="period-value">
            {{ $periodText }}
        </td>

        <td class="period-right">
            {{ $dateText }}
        </td>

    </tr>

    <tr>

        <td class="period-label bold">
            Hari Kerja
        </td>

        <td class="period-separator">
            :
        </td>

        <td class="period-value">

            {{ number_format($workDays, 0, ',', '.') }}

            hari

            @if($overtimeHours > 0)
                + Lembur
                {{ number_format($overtimeHours, 0, ',', '.') }}
            @endif

        </td>

        <td></td>

    </tr>

    <tr>

        <td class="period-label bold">
            Tidak masuk kerja
        </td>

        <td class="period-separator">
            :
        </td>

        <td class="period-value">

            {{ number_format($unpaidLeave, 0, ',', '.') }}
            hari

        </td>

        <td></td>

    </tr>

</table>


{{-- =========================================================
     MAIN 3 COLUMN
========================================================= --}}

<table class="main-grid">

<tr>


{{-- =========================================================
     LEFT
========================================================= --}}

<td class="main-left">


    {{-- EMPLOYEE --}}

    <table class="employee-table border">

        <tr>

            <td class="employee-label bold">
                Nama
            </td>

            <td class="employee-separator">
                :
            </td>

            <td class="employee-value">
                {{ $employee->full_name_outer ?? '-' }}
            </td>

        </tr>


        <tr>

            <td class="employee-label bold">
                Area
            </td>

            <td class="employee-separator">
                :
            </td>

            <td class="employee-value">
                {{ $contract?->placement_area ?? '-' }}
            </td>

        </tr>


        <tr>

            <td class="employee-label bold">
                Jabatan
            </td>

            <td class="employee-separator">
                :
            </td>

            <td class="employee-value">
                {{ $contract?->job_title ?? '-' }}
            </td>

        </tr>


        <tr>

            <td class="employee-label bold">
                Kategori
            </td>

            <td class="employee-separator">
                :
            </td>

            <td class="employee-value">
                {{ $contract?->category ?? '-' }}
            </td>

        </tr>


        <tr>

            <td class="employee-label bold">
                Level
            </td>

            <td class="employee-separator">
                :
            </td>

            <td class="employee-value">
                {{ $contract?->level ?? '-' }}
            </td>

        </tr>

    </table>


    {{-- GAJI TETAP --}}

    <div class="salary-title">
        Gaji Tetap terdiri dari :
    </div>


    <table class="salary-table">

        <tr>

            <td class="salary-no">
                1.
            </td>

            <td class="salary-description">
                Gaji Pokok
            </td>

            <td class="salary-formula">
                Rp {{ number_format($basicSalary, 0, ',', '.') }}
            </td>

            <td class="salary-equals">
                =
            </td>

            <td class="salary-amount">
                Rp {{ number_format($basicSalary, 0, ',', '.') }}
                / bln
            </td>

        </tr>


        <tr>

            <td class="salary-no">
                2.
            </td>

            <td class="salary-description">
                Tunj. Jabatan
            </td>

            <td class="salary-formula">
                Rp {{ number_format($allowance, 0, ',', '.') }}
            </td>

            <td class="salary-equals">
                =
            </td>

            <td class="salary-amount">
                Rp {{ number_format($allowance, 0, ',', '.') }}
                / bln
            </td>

        </tr>


        <tr>

            <td colspan="4" class="right bold">
                Total Gaji =
            </td>

            <td class="salary-amount bold">
                Rp {{ number_format($basicSalary + $allowance, 0, ',', '.') }}
                / bln
            </td>

        </tr>

    </table>


    {{-- LEMBUR --}}

    <table class="overtime-table">

        <tr>

            <td class="bold">
                Lembur Hari Minggu / Libur Nasional :
            </td>

        </tr>

        <tr>

            <td>

                −
                {{ number_format($overtimeHours, 0, ',', '.') }}
                hari

                &nbsp;&nbsp; X &nbsp;&nbsp;

                {{ number_format($overtimePay, 0, ',', '.') }}

                / hari

                &nbsp;&nbsp; = &nbsp;&nbsp;

                Rp {{ number_format($overtimePay, 0, ',', '.') }}

            </td>

        </tr>

        <tr>

            <td class="right bold">

                Total Penghasilan =

                Rp {{ number_format($grossSalary, 0, ',', '.') }}
                / bln

            </td>

        </tr>

    </table>


    {{-- POTONGAN --}}

    <div class="deduction-title">
        Potongan :
    </div>


    <table class="deduction-table">


        <tr>

            <td class="deduction-no">
                1.
            </td>

            <td class="deduction-description">
                BPJS Ketenagakerjaan bulan
                {{ $months[(int) $periodEnd->month] }}
                {{ $periodEnd->year }}
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($bpjsTk, 0, ',', '.') }}
                / bln
            </td>

        </tr>


        <tr>

            <td class="deduction-no">
                2.
            </td>

            <td class="deduction-description">
                BPJS Kesehatan bulan
                {{ $months[(int) $periodEnd->month] }}
                {{ $periodEnd->year }}
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($bpjsKs, 0, ',', '.') }}
                / bln
            </td>

        </tr>


        <tr>

            <td class="deduction-no">
                3.
            </td>

            <td class="deduction-description">
                Pajak PPh 21
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($pph21, 0, ',', '.') }}
            </td>

        </tr>


        <tr>

            <td class="deduction-no">
                4.
            </td>

            <td class="deduction-description">
                Angsuran Pinjaman ke .....
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($cashAdvance, 0, ',', '.') }}
                / bln
            </td>

        </tr>


        @if($otherDeductions > 0)

        <tr>

            <td class="deduction-no">
                5.
            </td>

            <td class="deduction-description">
                Potongan lainnya
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($otherDeductions, 0, ',', '.') }}
            </td>

        </tr>

        @endif


        @if($gantungan > 0)

        <tr>

            <td class="deduction-no">
                6.
            </td>

            <td class="deduction-description">
                Gantungan
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($gantungan, 0, ',', '.') }}
            </td>

        </tr>

        @endif


        @if($previousGantungan > 0)

        <tr>

            <td class="deduction-no">
                7.
            </td>

            <td class="deduction-description">
                Gantungan periode sebelumnya
            </td>

            <td class="deduction-equals">
                =
            </td>

            <td class="deduction-amount">
                Rp {{ number_format($previousGantungan, 0, ',', '.') }}
            </td>

        </tr>

        @endif


        <tr>

            <td colspan="3" class="right payable">
                Gaji yang dibayarkan
            </td>

            <td class="deduction-amount payable">
                Rp {{ number_format($netSalary, 0, ',', '.') }}
                / bln
            </td>

        </tr>

    </table>


    {{-- TERBILANG --}}

    <table class="terbilang-table">

        <tr>

            <td style="width:12%;">
                Terbilang
            </td>

            <td style="width:3%;">
                :
            </td>

            <td class="bold">
                # {{ $terbilangNet }} Rupiah #
            </td>

        </tr>

    </table>


</td>


{{-- =========================================================
     MIDDLE - CATATAN
========================================================= --}}

<td class="main-middle">

    <table class="notes-box">

        <tr>

            <td class="notes-title">
                Catatan :
            </td>

        </tr>


        <tr>

            <td class="notes-content">

                @if($maternityPay > 0)

                    Pembayaran normatif:
                    Rp {{ number_format($maternityPay, 0, ',', '.') }}

                    <br><br>

                @endif

                @if($incentive > 0)

                    Insentif:
                    Rp {{ number_format($incentive, 0, ',', '.') }}

                    <br><br>

                @endif

                @if($gantunganDays > 0)

                    Gantungan:
                    {{ number_format($gantunganDays, 1) }}
                    hari

                    <br><br>

                @endif

                @if($unpaidLeave > 0)

                    Tidak masuk:
                    {{ number_format($unpaidLeave, 1) }}
                    hari

                    <br><br>

                @endif

                @if($overtimeHours > 0)

                    Lembur:
                    {{ number_format($overtimeHours, 2) }}
                    jam

                @endif

            </td>

        </tr>


        <tr>

            <td class="notes-signature">

                Manager Keuangan &amp;
                <br>
                Perpajakan II

                <div class="signature-space"></div>

                __________________________

                <br>

                (Yohana)

            </td>

        </tr>

    </table>

</td>


{{-- =========================================================
     RIGHT
========================================================= --}}

<td class="main-right">


    <div class="right-note">

        Adanya penyesuaian kenaikan
        <br>
        nominal Tunjangan Jabatan

    </div>


    <div class="right-note">

        Adanya penyesuaian kenaikan
        <br>
        nominal Gaji Pokok &amp; Tunjangan
        <br>
        Jabatan

    </div>


    <div class="right-note">

        Adanya penyesuaian kenaikan
        <br>
        nominal Gaji Pokok

    </div>


    <div class="right-numbered">

        3. Pajak PPh 21

    </div>


    <div class="right-numbered">

        3. Pajak PPh 21 (pengembalian)

    </div>


    <div class="right-numbered">

        5. Tidak masuk bekerja di bulan
        Desember 2022

    </div>


    <div class="right-numbered">

        5. Tidak masuk bekerja tgl 23 - 24
        Desember 2022 (02 hari)

    </div>


</td>

</tr>

</table>


<div class="footer">

    Dokumen ini dibuat secara elektronik melalui sistem Payroll
    PT. Batu Karang.

</div>


</body>

</html>