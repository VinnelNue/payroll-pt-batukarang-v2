@php
    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL PAYROLL LOCAL - DETAIL LENGKAP
    |--------------------------------------------------------------------------
    |
    | Lembar ini mempertahankan laporan detail sebelumnya, termasuk rekap
    | tambahan CM, hari kerja, gantungan, lembur, BPJS, serta rincian finansial
    | yang hanya ditampilkan kepada manager_keuangan / super_admin.
    |
    | Role Head HRD tetap hanya mendapatkan identitas dasar, absensi/rekap,
    | dan BPJS dengan nominal level 1-13; level 14+ berwarna biru/kosong.
    | Role HRD tidak mendapatkan kolom BPJS atau rincian finansial.
    |--------------------------------------------------------------------------
    */

    $currentPeriod = \Carbon\Carbon::createFromFormat(
        'Y-m-d',
        $period . '-01'
    );

    $startDate = $currentPeriod->copy()->startOfMonth();
    $endDate = $currentPeriod->copy()->endOfMonth();

    $userRole = strtolower(trim((string) ($userRole ?? '')));

    /*
    |--------------------------------------------------------------------------
    | EXPORT PERMISSIONS - CONSISTENT WITH THE PAYROLL UI
    |--------------------------------------------------------------------------
    |
    | manager_keuangan + super_admin:
    |   Full worksheet, including KATEGORI, LEVEL, BPJS and all salary fields.
    |
    | head_hrd / kepala_hrd:
    |   Attendance + summary + BPJS only.
    |   No KATEGORI, LEVEL or salary columns.
    |   BPJS amount is visible only for Level 1-13; Level 14+ is blue/blank.
    |
    | hrd:
    |   Identity + attendance/summary only, with no BPJS or salary columns.
    |
    */

    $isFinanceRole = in_array(
        $userRole,
        ['manager_keuangan', 'super_admin'],
        true
    );

    $isHeadHrd = in_array(
        $userRole,
        ['head_hrd', 'kepala_hrd'],
        true
    );

    $isHrd = $userRole === 'hrd';

    // KATEGORI and LEVEL are included only in the full Finance worksheet.
    $showIdentityDetails = $isFinanceRole;
    $showCategoryColumn = $showIdentityDetails;
    $showLevelColumn = $showIdentityDetails;

    // Head HRD sees the BPJS columns, but not salary columns.
    $showBpjsColumns = $isFinanceRole || $isHeadHrd;
    $showFinancialColumns = $isFinanceRole;

    /*
    |--------------------------------------------------------------------------
    | BPJS AMOUNT ACCESS PER EMPLOYEE
    |--------------------------------------------------------------------------
    */
    $canViewBpjsAmount = static function ($level) use (
        $isFinanceRole,
        $isHeadHrd
    ): bool {
        if ($isFinanceRole) {
            return true;
        }

        return $isHeadHrd
            && $level !== null
            && (int) $level <= 13;
    };

    /*
    |--------------------------------------------------------------------------
    | MONTH / DAY LABEL
    |--------------------------------------------------------------------------
    */

    $monthNames = [
        1 => 'JANUARI',
        2 => 'FEBRUARI',
        3 => 'MARET',
        4 => 'APRIL',
        5 => 'MEI',
        6 => 'JUNI',
        7 => 'JULI',
        8 => 'AGUSTUS',
        9 => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DESEMBER',
    ];

    $dayNames = [
        0 => 'MIN',
        1 => 'SEN',
        2 => 'SEL',
        3 => 'RAB',
        4 => 'KAM',
        5 => 'JUM',
        6 => 'SAB',
    ];

    /*
    |--------------------------------------------------------------------------
    | DAY COUNT
    |--------------------------------------------------------------------------
    */

    $dayCount = iterator_count(
        \Carbon\CarbonPeriod::create(
            $startDate,
            '1 day',
            $endDate
        )
    );

    /*
    |--------------------------------------------------------------------------
    | COLUMN COUNT
    |--------------------------------------------------------------------------
    |
    | 5 identity:
    | NO, NAMA, JABATAN, KATEGORI, LEVEL
    |
    | 10 TOTAL:
    | HADIR, ABSEN TDK DIBAYAR, ABSEN DIBAYAR, Σ ABSEN,
    | M/HB, CM, NORMATIF, HARI KERJA, GANTUNGAN, LEMBUR
    |
    | 2 BPJS
    |
    | 9 FINANCIAL:
    | INSENTIF, KASBON, GANTUNGAN DIPOTONG, POTONGAN LAIN,
    | GAJI POKOK, TUNJANGAN, PPH21, GROSS, THP
    |
    */

    $identityCount = $showIdentityDetails ? 5 : 3;
    $totalCount = 10;
    $bpjsCount = $showBpjsColumns ? 2 : 0;
    $financialCount = $showFinancialColumns ? 9 : 0;

    $columnCount =
        $identityCount
        + $dayCount
        + $totalCount
        + $bpjsCount
        + $financialCount;

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE STATUS
    |--------------------------------------------------------------------------
    */

    $normalizeStatus = static function ($status): string {

        $status = strtoupper(
            trim(
                (string) $status
            )
        );

        return $status === ''
            ? '-'
            : $status;
    };

    /*
    |--------------------------------------------------------------------------
    | BUILD EFFECTIVE ATTENDANCE
    |--------------------------------------------------------------------------
    |
    | AttendanceRecord = source of truth.
    | Holiday tanpa explicit record = HB.
    | Sunday tanpa record = '-'.
    |
    */

    $buildAttendance = static function ($employee) use (
        $startDate,
        $endDate,
        $holidays,
        $normalizeStatus
    ): array {

        $recordMap = [];

        foreach (
            ($employee->attendanceRecords ?? [])
            as $record
        ) {

            if (!$record->attendance_date) {
                continue;
            }

            $key = \Carbon\Carbon::parse(
                $record->attendance_date
            )->format('Y-m-d');

            $recordMap[$key] =
                $normalizeStatus(
                    $record->status
                );
        }

        $result = [];

        foreach (
            \Carbon\CarbonPeriod::create(
                $startDate,
                '1 day',
                $endDate
            ) as $date
        ) {

            $key =
                $date->format('Y-m-d');

            if (
                array_key_exists(
                    $key,
                    $recordMap
                )
            ) {

                $result[$key] =
                    $recordMap[$key];

                continue;
            }

            if (
                $holidays
                    ->has($key)
            ) {

                $result[$key] = 'HB';

                continue;
            }

            $result[$key] = '-';
        }

        return $result;
    };

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE SUMMARY
    |--------------------------------------------------------------------------
    */

    $summarize = static function (
        array $attendance
    ): array {

        $present = 0.0;
        $unpaid = 0.0;
        $paid = 0.0;
        $mhb = 0.0;
        $cm = 0.0;
        $normative = 0.0;
        $total = 0.0;

        foreach (
            $attendance as $status
        ) {

            switch ($status) {

                case 'H':

                    $present += 1;
                    $total += 1;

                    break;

                case 'H0.5':

                    $present += 0.5;
                    $unpaid += 0.5;
                    $total += 1;

                    break;

                case 'A':
                case 'I':

                    $unpaid += 1;
                    $total += 1;

                    break;

                case 'SKD':
                case 'S':
                case 'C':

                    $paid += 1;
                    $total += 1;

                    break;

                case 'CM':

                    $paid += 1;
                    $cm += 1;
                    $normative += 1;
                    $total += 1;

                    break;

                case 'M/HB':

                    $paid += 1;
                    $normative += 1;
                    $total += 1;

                    break;

                case 'HB':

                    $paid += 1;
                    $mhb += 1;
                    $normative += 1;
                    $total += 1;

                    break;
            }
        }

        return [
            'present' =>
                round($present, 1),

            'unpaid' =>
                round($unpaid, 1),

            'paid' =>
                round($paid, 1),

            'mhb' =>
                round($mhb, 1),

            'cm' =>
                round($cm, 1),

            'normative' =>
                round($normative, 1),

            'total' =>
                round($total, 1),
        ];
    };

    /*
    |--------------------------------------------------------------------------
    | GRAND TOTAL
    |--------------------------------------------------------------------------
    */

    $grand = [

        'present' => 0.0,
        'unpaid' => 0.0,
        'paid' => 0.0,
        'total' => 0.0,
        'mhb' => 0.0,
        'cm' => 0.0,
        'normative' => 0.0,
        'work_days' => 0.0,
        'gantungan' => 0.0,
        'overtime_hours' => 0.0,

        'bpjs_tk' => 0.0,
        'bpjs_ks' => 0.0,

        'incentive' => 0.0,
        'cash_advance' => 0.0,
        'previous_gantungan' => 0.0,
        'other_deductions' => 0.0,
        'basic_salary' => 0.0,
        'allowance' => 0.0,
        'pph21' => 0.0,
        'gross' => 0.0,
        'net' => 0.0,
    ];

    /*
    |--------------------------------------------------------------------------
    | GROUP BY DEPARTMENT
    |--------------------------------------------------------------------------
    */

    $groupedEmployees =
        $employees->groupBy(
            function ($employee) {

                return $employee
                    ->contract
                    ?->currentHistory
                    ?->department
                    ?? 'Tanpa Department';
            }
        );
@endphp

<table
    style="
        border-collapse:collapse;
        width:100%;
        table-layout:fixed;
        font-family:Arial,Helvetica,sans-serif;
        font-size:9px;
        color:#000;
    "
>

    {{-- ============================================================
         COLUMN WIDTH
    ============================================================= --}}

    <colgroup>
        <col style="width:32px;">
        <col style="width:145px;">
        <col style="width:255px;">

        @if($showIdentityDetails)
            <col style="width:43px;">
            <col style="width:32px;">
        @endif

        @foreach(
            \Carbon\CarbonPeriod::create(
                $startDate,
                '1 day',
                $endDate
            ) as $date
        )
            <col style="width:29px;">
        @endforeach

        {{-- TOTAL --}}
        <col style="width:48px;">
        <col style="width:58px;">
        <col style="width:55px;">
        <col style="width:50px;">
        <col style="width:43px;">
        <col style="width:43px;">
        <col style="width:54px;">
        <col style="width:59px;">
        <col style="width:59px;">
        <col style="width:58px;">

        @if($showBpjsColumns)
            <col style="width:82px;">
            <col style="width:82px;">
        @endif

        @if($showFinancialColumns)
            <col style="width:82px;">
            <col style="width:82px;">
            <col style="width:88px;">
            <col style="width:82px;">
            <col style="width:92px;">
            <col style="width:88px;">
            <col style="width:78px;">
            <col style="width:98px;">
            <col style="width:105px;">
        @endif
    </colgroup>

    {{-- ============================================================
         TITLE
    ============================================================= --}}

    <tr style="height:18px;">
        <td
            colspan="{{ $columnCount }}"
            style="
                padding:2px 4px;
                border:0;
                text-align:center;
                font-size:16px;
                font-weight:800;
                font-family:Georgia,'Times New Roman',serif;
            "
        >
            PT. BATU KARANG
        </td>
    </tr>

    <tr style="height:20px;">
        <td
            colspan="{{ $columnCount }}"
            style="
                padding:2px 4px;
                border:0;
                text-align:center;
                font-size:14px;
                font-weight:800;
                font-family:Georgia,'Times New Roman',serif;
            "
        >
            DETAIL LENGKAP ABSENSI &amp; PAYROLL
        </td>
    </tr>

    <tr style="height:18px;">
        <td
            colspan="{{ $columnCount }}"
            style="
                padding:2px 4px 5px 4px;
                border-bottom:2px solid #000;
                border-top:0;
                border-left:0;
                border-right:0;
                text-align:center;
                font-size:11px;
                font-weight:800;
                font-family:Georgia,'Times New Roman',serif;
            "
        >
            PERIODE :
            {{ $startDate->format('d') }}
            {{ $monthNames[(int) $startDate->format('m')] }}
            {{ $startDate->format('Y') }}
            S/D
            {{ $endDate->format('d') }}
            {{ $monthNames[(int) $endDate->format('m')] }}
            {{ $endDate->format('Y') }}
        </td>
    </tr>

    {{-- ============================================================
         HEADER GROUP
    ============================================================= --}}

    <tr style="height:20px;">

        <th
            rowspan="2"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            N<br>O
        </th>

        <th
            rowspan="2"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            NAMA
        </th>

        <th
            rowspan="2"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            JABATAN
        </th>

        @if($showIdentityDetails)
        <th
            rowspan="2"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            KATE<br>GORI
        </th>

        <th
            rowspan="2"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            LEV<br>EL
        </th>
        @endif



        <th
            colspan="{{ $dayCount }}"
            style="
                border:1px solid #000;
                background:#9dc3e6;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            ABSENSI
        </th>

        <th
            colspan="{{ $totalCount }}"
            style="
                border:1px solid #000;
                background:#bdd7ee;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            TOTAL
        </th>

        @if($showBpjsColumns)
            <th
                colspan="2"
                style="
                    border:1px solid #000;
                    background:#bdd7ee;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                BPJS
            </th>
        @endif

        @if($showFinancialColumns)
            <th
                colspan="9"
                style="
                    border:1px solid #000;
                    background:#c6e0b4;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                VARIABEL GAJI &amp; POTONGAN
            </th>
        @endif
    </tr>

    {{-- ============================================================
         HEADER DETAIL
    ============================================================= --}}

    <tr style="height:30px;">

        @foreach(
            \Carbon\CarbonPeriod::create(
                $startDate,
                '1 day',
                $endDate
            ) as $date
        )

            @php
                $dateKey =
                    $date->format('Y-m-d');

                $isSunday =
                    $date->isSunday();

                $isHoliday =
                    $holidays->has($dateKey);

                if ($isHoliday) {
                    $dayBg = '#ffff00';
                } elseif ($isSunday) {
                    $dayBg = '#f4cccc';
                } else {
                    $dayBg = '#eaf3f8';
                }

                $dayTextColor =
                    $isSunday || $isHoliday
                        ? '#ff0000'
                        : '#000000';
            @endphp

            <th
                style="
                    border:1px solid #000;
                    background:{{ $dayBg }};
                    color:{{ $dayTextColor }};
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    font-size:8px;
                    line-height:1.05;
                "
            >
                {{ $date->format('d') }}
                <br>
                <span style="font-size:8px;">
                    {{ $dayNames[$date->dayOfWeek] }}
                </span>
            </th>

        @endforeach

        <th
            style="
                border:1px solid #000;
                background:#eaf3f8;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            Hadir
        </th>

        <th
            style="
                border:1px solid #000;
                background:#fde9e7;
                color:#ff0000;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
                line-height:1;
            "
        >
            Absen<br> Tdk<br>Dibayar
        </th>

        <th
            style="
                border:1px solid #000;
                background:#e2f0d9;
                color:#008000;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
                line-height:1;
            "
        >
            Absen<br>Dibayar
        </th>

        <th
            style="
                border:1px solid #000;
                background:#d9eaf7;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            Σ Absen
        </th>

        <th
            style="
                border:1px solid #000;
                background:#e7e6e6;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            M/HB
        </th>

        <th
            style="
                border:1px solid #000;
                background:#e9d5ff;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            CM
        </th>

        <th
            style="
                border:1px solid #000;
                background:#d9ead3;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            Norm<br>atif
        </th>

        <th
            style="
                border:1px solid #000;
                background:#fce4d6;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
                line-height:1;
            "
        >
            Hari<br>Kerja
        </th>

        <th
            style="
                border:1px solid #000;
                background:#fff2cc;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
            "
        >
            Gantungan
        </th>

        <th
            style="
                border:1px solid #000;
                background:#d9e1f2;
                text-align:center;
                vertical-align:middle;
                font-weight:800;
                line-height:1;
            "
        >
            Lembur<br>(Jam)
        </th>

        @if($showBpjsColumns)

            <th
                style="
                    border:1px solid #000;
                    background:#fff2cc;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                BPJS TK<br>
                {{ strtoupper($startDate->format('M Y')) }}
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#fff2cc;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                BPJS KES<br>
                {{ strtoupper($startDate->format('M Y')) }}
            </th>

        @endif

        @if($showFinancialColumns)

            <th
                style="
                    border:1px solid #000;
                    background:#e2f0d9;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                Insentif
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#fde9e7;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                Kasbon
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#fde9e7;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                Gantungan<br>Dipotong
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#fde9e7;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                Potongan<br>Lain
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#e2f0d9;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                Gaji<br>Pokok
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#e2f0d9;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                Tunjangan
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#fde9e7;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                PPH 21
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#c6e0b4;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                "
            >
                Gross
            </th>

            <th
                style="
                    border:1px solid #000;
                    background:#a9d18e;
                    text-align:center;
                    vertical-align:middle;
                    font-weight:800;
                    line-height:1;
                "
            >
                Take Home<br>Pay
            </th>

        @endif
    </tr>

    {{-- ============================================================
         DEPARTMENT
    ============================================================= --}}

    @foreach(
        $groupedEmployees
        as $department => $departmentEmployees
    )

        @php
            $departmentTotal = [
                'present' => 0.0,
                'unpaid' => 0.0,
                'paid' => 0.0,
                'total' => 0.0,
                'mhb' => 0.0,
                'cm' => 0.0,
                'normative' => 0.0,
                'work_days' => 0.0,
                'gantungan' => 0.0,
                'overtime_hours' => 0.0,
                'bpjs_tk' => 0.0,
                'bpjs_ks' => 0.0,
                'incentive' => 0.0,
                'cash_advance' => 0.0,
                'previous_gantungan' => 0.0,
                'other_deductions' => 0.0,
                'basic_salary' => 0.0,
                'allowance' => 0.0,
                'pph21' => 0.0,
                'gross' => 0.0,
                'net' => 0.0,
            ];
        @endphp

        <tr style="height:18px;">
            <td
                colspan="{{ $columnCount }}"
                style="
                    border:1px solid #000;
                    background:#d9eaf7;
                    font-weight:800;
                    padding:2px 5px;
                    text-align:left;
                "
            >
                ({{ chr(65 + $loop->index) }})
                {{ strtoupper($department) }}
            </td>
        </tr>

        @foreach(
            $departmentEmployees
            as $employee
        )

            @php
                $payroll =
                    $payrollsByEmployee->get(
                        $employee->id_employee
                    );

                $history =
                    $employee
                        ->contract
                        ?->currentHistory;

                $jobTitle =
                    $history?->job_title
                    ?? '-';

                $category =
                    $history?->category
                    ?? '-';

                $level =
                    $history?->level !== null
                        ? (int) $history->level
                        : null;

                /*
                 * PENTING:
                 * Head HRD does not receive category/level columns; BPJS level 14+ is blank blue.
                 */
                $level14PlusHidden =
                    $isHeadHrd
                    && $level !== null
                    && $level >= 14;

                $financialAllowed =
                    $canViewBpjsAmount(
                        $level
                    );

                $attendance =
                    $buildAttendance(
                        $employee
                    );

                $summary =
                    $summarize(
                        $attendance
                    );

                /*
                 * Payroll values.
                 * Jika payroll belum ada -> default 0.
                 */
                $workDays =
                    $payroll?->work_days !== null
                        ? (float) $payroll->work_days
                        : $summary['total'];

                $gantunganDays =
                    (float) (
                        $payroll?->gantungan_days
                        ?? 0
                    );

                /*
                 * Fallback gantungan untuk employee
                 * yang belum mempunyai payroll row.
                 */
                if (!$payroll) {

                    $gantunganDays = 0.0;

                    foreach (
                        ($employee->attendanceRecords ?? [])
                        as $record
                    ) {

                        $status =
                            strtoupper(
                                trim(
                                    (string)
                                    $record->status
                                )
                            );

                        if (
                            !in_array(
                                $status,
                                [
                                    'A',
                                    'I',
                                    'H0.5',
                                ],
                                true
                            )
                        ) {
                            continue;
                        }

                        $recordDate =
                            \Carbon\Carbon::parse(
                                $record->attendance_date
                            );

                        if (
                            (int)
                            $recordDate->format('d')
                            <= $cutoffDay
                        ) {
                            continue;
                        }

                        $gantunganDays +=
                            $status === 'H0.5'
                                ? 0.5
                                : 1.0;
                    }
                }

                $overtimeHours =
                    (float) (
                        $payroll?->overtime_hours
                        ?? 0
                    );

                $bpjsTk =
                    (float) (
                        $payroll?->bpjs_tk_deduction
                        ?? 0
                    );

                $bpjsKs =
                    (float) (
                        $payroll?->bpjs_ks_deduction
                        ?? 0
                    );

                $incentive =
                    (float) (
                        $payroll?->incentive
                        ?? 0
                    );

                $cashAdvance =
                    (float) (
                        $payroll?->cash_advance
                        ?? 0
                    );

                $previousGantungan =
                    (float) (
                        $payroll
                        ?->previous_gantungan_deduction
                        ?? 0
                    );

                $otherDeductions =
                    (float) (
                        $payroll?->other_deductions
                        ?? 0
                    );

                $basic =
                    (float) (
                        $payroll?->basic_salary
                        ?? 0
                    );

                $allowance =
                    (float) (
                        $payroll?->allowance
                        ?? 0
                    );

                $pph21 =
                    (float) (
                        $payroll?->pph21_deduction
                        ?? 0
                    );

                $gross =
                    (float) (
                        $payroll?->gross_salary
                        ?? 0
                    );

                $net =
                    (float) (
                        $payroll?->net_salary
                        ?? 0
                    );

                /*
                 * TOTAL ABSENSI SELALU DIJUMLAHKAN.
                 */
                foreach (
                    [
                        'present',
                        'unpaid',
                        'paid',
                        'total',
                        'mhb',
                        'cm',
                        'normative',
                    ] as $key
                ) {

                    $departmentTotal[$key] +=
                        $summary[$key];

                    $grand[$key] +=
                        $summary[$key];
                }

                $departmentTotal['work_days'] +=
                    $workDays;

                $departmentTotal['gantungan'] +=
                    $gantunganDays;

                $departmentTotal['overtime_hours'] +=
                    $overtimeHours;

                $grand['work_days'] +=
                    $workDays;

                $grand['gantungan'] +=
                    $gantunganDays;

                $grand['overtime_hours'] +=
                    $overtimeHours;

                /*
                 * BPJS dijumlahkan secara terpisah karena Head HRD melihat
                 * BPJS level 1-13 meskipun kolom gaji disembunyikan.
                 */
                if ($showBpjsColumns && $financialAllowed) {
                    $departmentTotal['bpjs_tk'] += $bpjsTk;
                    $departmentTotal['bpjs_ks'] += $bpjsKs;
                    $grand['bpjs_tk'] += $bpjsTk;
                    $grand['bpjs_ks'] += $bpjsKs;
                }

                /*
                 * Hanya Finance/Super Admin yang memiliki kolom dan total gaji.
                 */
                if ($showFinancialColumns && $financialAllowed) {
                    $departmentTotal['incentive'] += $incentive;
                    $departmentTotal['cash_advance'] += $cashAdvance;
                    $departmentTotal['previous_gantungan'] += $previousGantungan;
                    $departmentTotal['other_deductions'] += $otherDeductions;
                    $departmentTotal['basic_salary'] += $basic;
                    $departmentTotal['allowance'] += $allowance;
                    $departmentTotal['pph21'] += $pph21;
                    $departmentTotal['gross'] += $gross;
                    $departmentTotal['net'] += $net;

                    $grand['incentive'] += $incentive;
                    $grand['cash_advance'] += $cashAdvance;
                    $grand['previous_gantungan'] += $previousGantungan;
                    $grand['other_deductions'] += $otherDeductions;
                    $grand['basic_salary'] += $basic;
                    $grand['allowance'] += $allowance;
                    $grand['pph21'] += $pph21;
                    $grand['gross'] += $gross;
                    $grand['net'] += $net;
                }

                $rowNumber =
                    $loop->iteration;
            @endphp

            {{-- ====================================================
                 EMPLOYEE ROW
            ===================================================== --}}

            <tr style="height:17px;">

                <td
                    style="
                        border:1px solid #000;
                        text-align:center;
                        vertical-align:middle;
                    "
                >
                    {{ $rowNumber }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        vertical-align:middle;
                        white-space:nowrap;
                        overflow:hidden;
                    "
                >
                    {{ $employee->full_name ?? '-' }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        vertical-align:middle;
                        white-space:nowrap;
                        overflow:hidden;
                    "
                >
                    {{ $jobTitle }}
                </td>

                @if($showIdentityDetails)
                    <td style="border:1px solid #000; text-align:center; vertical-align:middle;">
                        {{ $category }}
                    </td>
                    <td style="border:1px solid #000; text-align:center; vertical-align:middle;">
                        {{ $level ?? '-' }}
                    </td>
                @endif

                {{-- =================================================
                     DAILY ATTENDANCE
                ================================================== --}}

                @foreach(
                    \Carbon\CarbonPeriod::create(
                        $startDate,
                        '1 day',
                        $endDate
                    ) as $date
                )

                    @php
                        $status =
                            $attendance[
                                $date->format('Y-m-d')
                            ] ?? '-';

                        $statusBg =
                            match ($status) {
                                'A',
                                'I'
                                    => '#ffd9d9',

                                'H0.5'
                                    => '#fff2cc',

                                'HB',
                                'M/HB'
                                    => '#e7e6e6',

                                'CM'
                                    => '#eadcf8',

                                default
                                    => '#ffffff',
                            };

                        $statusColor =
                            match ($status) {
                                'A',
                                'I'
                                    => '#ff0000',

                                default
                                    => '#000000',
                            };
                    @endphp

                    <td
                        style="
                            border:1px solid #000;
                            background:{{ $statusBg }};
                            color:{{ $statusColor }};
                            text-align:center;
                            vertical-align:middle;
                            font-weight:700;
                            font-size:8px;
                            padding:0;
                        "
                    >
                        {{ $status }}
                    </td>

                @endforeach

                {{-- =================================================
                     TOTAL ABSENSI
                ================================================== --}}

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['present'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        color:#ff0000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['unpaid'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        color:#008000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['paid'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['total'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        color:#ff0000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['mhb'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['cm'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        color:#008000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $summary['normative'],
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $workDays,
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $gantunganDays,
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        vertical-align:middle;
                    "
                >
                    {{ number_format(
                        $overtimeHours,
                        1,
                        ',',
                        '.'
                    ) }}
                </td>

                {{-- =================================================
                     BPJS
                ================================================== --}}

                @if($showBpjsColumns)

                    @if($financialAllowed)

                        <td
                            style="
                                border:1px solid #000;
                                text-align:right;
                                vertical-align:middle;
                            "
                        >
                            {{ number_format(
                                $bpjsTk,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td
                            style="
                                border:1px solid #000;
                                text-align:right;
                                vertical-align:middle;
                            "
                        >
                            {{ number_format(
                                $bpjsKs,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                    @elseif($level14PlusHidden)

                        {{-- HEAD HRD LEVEL 14+ = BLUE / BLANK BPJS CELLS --}}

                        <td
                            style="
                                border:1px solid #000;
                                background:#00A9E0;
                                color:#00A9E0;
                            "
                        ></td>

                        <td
                            style="
                                border:1px solid #000;
                                background:#00A9E0;
                                color:#00A9E0;
                            "
                        ></td>

                    @else

                        <td
                            style="
                                border:1px solid #000;
                            "
                        ></td>

                        <td
                            style="
                                border:1px solid #000;
                            "
                        ></td>

                    @endif

                @endif

                {{-- =================================================
                     FINANCIAL
                ================================================== --}}

                @if($showFinancialColumns)

                    @if($financialAllowed)

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $incentive,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $cashAdvance,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $previousGantungan,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $otherDeductions,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $basic,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $allowance,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td style="border:1px solid #000;text-align:right;">
                            {{ number_format(
                                $pph21,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td
                            style="
                                border:1px solid #000;
                                text-align:right;
                                font-weight:800;
                            "
                        >
                            {{ number_format(
                                $gross,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                        <td
                            style="
                                border:1px solid #000;
                                text-align:right;
                                font-weight:800;
                            "
                        >
                            {{ number_format(
                                $net,
                                0,
                                ',',
                                '.'
                            ) }}
                        </td>

                    @else

                        {{-- This branch is only used if a future role exposes financial columns with a level limit. --}}

                        @for(
                            $financialColumn = 0;
                            $financialColumn < 9;
                            $financialColumn++
                        )

                            <td
                                style="
                                    border:1px solid #000;
                                    background:#ffffff;
                                "
                            ></td>

                        @endfor

                    @endif

                @endif

            </tr>

        @endforeach

        {{-- ========================================================
             DEPARTMENT TOTAL
        ========================================================= --}}

        <tr style="height:17px;">

            <td
                colspan="{{ $identityCount }}"
                style="
                    border:1px solid #000;
                    text-align:center;
                    font-weight:800;
                    background:#ffffff;
                "
            >
                TOTAL
            </td>

            @foreach(
                \Carbon\CarbonPeriod::create(
                    $startDate,
                    '1 day',
                    $endDate
                ) as $date
            )

                <td
                    style="
                        border:1px solid #000;
                        background:#f2f2f2;
                    "
                ></td>

            @endforeach

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['present'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    color:#ff0000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['unpaid'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    color:#008000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['paid'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['total'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    color:#ff0000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['mhb'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['cm'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    color:#008000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['normative'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['work_days'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['gantungan'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            <td
                style="
                    border:1px solid #000;
                    text-align:right;
                    font-weight:800;
                "
            >
                {{ number_format(
                    $departmentTotal['overtime_hours'],
                    1,
                    ',',
                    '.'
                ) }}
            </td>

            @if($showBpjsColumns)

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        font-weight:800;
                    "
                >
                    {{ number_format(
                        $departmentTotal['bpjs_tk'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td
                    style="
                        border:1px solid #000;
                        text-align:right;
                        font-weight:800;
                    "
                >
                    {{ number_format(
                        $departmentTotal['bpjs_ks'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

            @endif

            @if($showFinancialColumns)

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['incentive'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['cash_advance'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['previous_gantungan'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['other_deductions'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['basic_salary'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['allowance'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['pph21'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['gross'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

                <td style="border:1px solid #000;text-align:right;font-weight:800;">
                    {{ number_format(
                        $departmentTotal['net'],
                        0,
                        ',',
                        '.'
                    ) }}
                </td>

            @endif

        </tr>

    @endforeach

    {{-- ============================================================
         GRAND TOTAL
    ============================================================= --}}

    <tr style="height:18px;">

        <td
            colspan="{{ $identityCount }}"
            style="
                border:1px solid #000;
                background:#d9eaf7;
                font-weight:800;
                text-align:center;
            "
        >
            GRAND TOTAL
        </td>

        @foreach(
            \Carbon\CarbonPeriod::create(
                $startDate,
                '1 day',
                $endDate
            ) as $date
        )

            <td
                style="
                    border:1px solid #000;
                    background:#f2f2f2;
                "
            ></td>

        @endforeach

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['present'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;color:#ff0000;">
            {{ number_format(
                $grand['unpaid'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;color:#008000;">
            {{ number_format(
                $grand['paid'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['total'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;color:#ff0000;">
            {{ number_format(
                $grand['mhb'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['cm'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;color:#008000;">
            {{ number_format(
                $grand['normative'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['work_days'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['gantungan'],
                1,
                ',',
                '.'
            ) }}
        </td>

        <td style="border:1px solid #000;font-weight:800;text-align:right;">
            {{ number_format(
                $grand['overtime_hours'],
                1,
                ',',
                '.'
            ) }}
        </td>

        @if($showBpjsColumns)

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['bpjs_tk'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['bpjs_ks'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

        @endif

        @if($showFinancialColumns)

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['incentive'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['cash_advance'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['previous_gantungan'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['other_deductions'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['basic_salary'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['allowance'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['pph21'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['gross'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

            <td style="border:1px solid #000;font-weight:800;text-align:right;">
                {{ number_format(
                    $grand['net'],
                    0,
                    ',',
                    '.'
                ) }}
            </td>

        @endif

    </tr>

</table>
