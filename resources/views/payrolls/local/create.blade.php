@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian')

@section('page_title', 'Form Input Absensi & Komponen Variabel')

@push('styles')

<style>
    .attendance-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-image: none !important;
        padding: 0 !important;
        text-align: center !important;
        text-align-last: center !important;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
        font-weight: 800 !important;
        font-style: normal !important;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .status-bg-empty {
        background-color: #f8f9fa !important;
        color: #6c757d !important;
        font-weight: normal !important;
    }

    .status-bg-H {
        background-color: #ffffff !important;
        color: #198754 !important;
    }

    .status-bg-HB {
        background-color: #dcfce7 !important;
        color: #15803d !important;
        font-weight: 900 !important;
    }

    .status-bg-SKD {
        background-color: #e0f2fe !important;
        color: #0284c7 !important;
    }

    .status-bg-C {
        background-color: #fef3c7 !important;
        color: #d97706 !important;
    }

    .status-bg-CM {
        background-color: #f3e8ff !important;
        color: #7e22ce !important;
    }

    .status-bg-A {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
        font-weight: 900 !important;
    }

    .status-bg-H05 {
        background-color: #ffedd5 !important;
        color: #c2410c !important;
        font-weight: 900 !important;
    }

    .status-bg-I {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
        font-weight: 900 !important;
    }

    .status-bg-SUNDAY {
        background-color: #e9ecef !important;
        color: #6c757d !important;
        font-weight: 800 !important;
    }

    .attendance-select option {
        font-weight: bold;
        text-align: center;
        background-color: #ffffff;
        color: #333333;
    }

    .cell-editable-draft {
        background-color: #fffbe2 !important;
    }

    .cell-locked {
        background-color: #f1f3f5 !important;
    }

    .timesheet-container {
        max-width: 100%;
        overflow-x: auto;
        white-space: nowrap;
    }

    .payroll-action-sticky {
        position: sticky;
        top: 12px;
        z-index: 1030;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 14px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.10);
    }

    .payroll-action-sticky .action-status {
        font-size: 0.76rem;
    }

    .department-row td {
        background: linear-gradient(
            90deg,
            rgba(13, 110, 253, 0.10),
            rgba(13, 110, 253, 0.035)
        ) !important;
        border-top: 2px solid rgba(13, 110, 253, 0.20) !important;
        border-bottom: 1px solid rgba(13, 110, 253, 0.10) !important;
        padding: 0.75rem 1rem !important;
    }

    .department-toggle {
        cursor: pointer;
        user-select: none;
    }

    .department-toggle:hover {
        background-color: rgba(13, 110, 253, 0.06);
    }

    .department-chevron {
        transition: transform 0.2s ease;
    }

    .department-toggle[aria-expanded="false"] .department-chevron {
        transform: rotate(-90deg);
    }

    .summary-box {
        min-width: 64px;
        padding: 0.25rem 0.35rem;
        border-radius: 8px;
        background: #fff;
        text-align: center;
    }

    .summary-value {
        font-size: 0.82rem;
        font-weight: 800;
        line-height: 1.05;
    }

    .summary-label {
        font-size: 0.58rem;
        color: #6c757d;
        line-height: 1.05;
        margin-top: 2px;
    }

    .finance-masked {
        color: #adb5bd;
        background: #f8f9fa;
    }

    @media (max-width: 768px) {
        .payroll-action-sticky {
            top: 8px;
            padding: 0.65rem;
        }

        .payroll-action-sticky .action-status {
            width: 100%;
            margin-bottom: 0.25rem;
        }

        .payroll-action-sticky .action-buttons {
            width: 100%;
        }

        .payroll-action-sticky .action-buttons button {
            flex: 1;
        }
    }
</style>

@endpush

@section('content')

@php

$userRole = Auth::user()->role ?? '';

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

$showFinancialColumns =
    $isFinanceRole
    || $isHeadHrd;

$canManageCutoff =
    $isFinanceRole;

$lockedState =
    (bool) ($isLocked ?? false);

$cutoffDay =
    (int) (
        $cutoffDay
        ?? $savedCutoffDay
        ?? 26
    );

$currentPeriod =
    \Carbon\Carbon::parse(
        ($period ?? date('Y-m')) . '-01'
    );

$startDate =
    $currentPeriod
        ->copy()
        ->startOfMonth();

$endDate =
    $currentPeriod
        ->copy()
        ->endOfMonth();

$datePeriod =
    \Carbon\CarbonPeriod::create(
        $startDate,
        $endDate
    );

$totalDaysCount =
    iterator_count(
        \Carbon\CarbonPeriod::create(
            $startDate,
            $endDate
        )
    );

$employeesByDepartment =
    $employees->groupBy(
        function ($employee) {
            return $employee
                ->activeContract
                ?->department
                ?? 'Tanpa Department';
        }
    );

$totalEmployeeCount =
    $employees->count();


/*
|--------------------------------------------------------------------------
| HOLIDAY MASTER
|--------------------------------------------------------------------------
|
| Dibuat SEKALI untuk seluruh employee.
|
*/

$holidayData = [];

foreach ($holidays ?? [] as $holiday) {

    $holidayDate =
        $holiday->holiday_date;

    if (!$holidayDate) {
        continue;
    }

    $holidayDate =
        $holidayDate instanceof \Carbon\Carbon
            ? $holidayDate
            : \Carbon\Carbon::parse(
                $holidayDate
            );

    if (
        $holidayDate->format('Y-m')
        !== $currentPeriod->format('Y-m')
    ) {
        continue;
    }

    $holidayData[
        $holidayDate->format('Y-m-d')
    ] = [
        'name' =>
            $holiday->name,

        'type' =>
            $holiday->type,
    ];
}


/*
|--------------------------------------------------------------------------
| BPJS PREVIEW SETTING
|--------------------------------------------------------------------------
*/

$tkRatePreview =
    (float) \App\Models\CompanySetting::get(
        'bpjs_tk_employee_rate',
        2.0
    ) / 100;

$ksRatePreview =
    (float) \App\Models\CompanySetting::get(
        'bpjs_ks_employee_rate',
        1.0
    ) / 100;

$ksCapPreview =
    (float) \App\Models\CompanySetting::get(
        'bpjs_ks_max_cap',
        12000000
    );


$recapColspan = 8;

$bpjsColspan =
    $showFinancialColumns
        ? 2
        : 0;

$financeColspan =
    $showFinancialColumns
        ? 4
        : 0;

$tableColspan =
    3
    + $totalDaysCount
    + $recapColspan
    + $bpjsColspan
    + $financeColspan;

@endphp


<div class="container-fluid p-0">

{{-- ============================================================
     HEADER
============================================================ --}}

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>

            <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">

                <i class="fa-solid fa-calendar-check text-primary"></i>

                <span>
                    Input Absensi Harian & Rekap Payroll
                </span>

            </h5>

            <p class="text-muted small m-0 mt-1">

                Cut-off berlaku per periode.

                Tanggal 01 s/d {{ $cutoffDay }}
                masuk payroll bulan berjalan;

                tanggal setelah cut-off tetap dicatat dan bila
                A/H0.5 menjadi gantungan bulan berikutnya.

            </p>

        </div>


        <div class="d-flex align-items-center gap-2">

            @if($isFinanceRole)

                <a href="{{ route(
                    'payrolls.local.export-bca',
                    ['period' => $period ?? date('Y-m')]
                ) }}"
                   class="btn btn-outline-success btn-sm px-3 py-2 rounded-3 fw-semibold">

                    <i class="fa-solid fa-file-csv me-1"></i>

                    Export CSV BCA

                </a>

            @endif


            <a href="{{ route('payrolls.local.index') }}"
               class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-medium">

                <i class="fa-solid fa-arrow-left me-1"></i>

                Kembali ke Rekap

            </a>

        </div>

    </div>


    <hr class="my-3 opacity-10">


    <div class="row g-3">

        {{-- IMPORT --}}

        <div class="col-lg-5">

            <div class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">

                <div class="d-flex align-items-center gap-2 mb-2">

                    <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center"
                         style="width:32px;height:32px;">

                        <i class="fa-solid fa-file-zipper"></i>

                    </div>

                    <h6 class="fw-bold text-success m-0">

                        Import Log Mesin Fingerprint

                    </h6>

                </div>


                <p class="text-muted small mb-3">

                    Upload
                    <strong>.ZIP</strong>
                    atau file Excel/CSV absensi mesin.

                </p>


                <form action="{{ route('payrolls.local.import') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="row g-2">

                    @csrf

                    <input type="hidden"
                           name="period_month"
                           value="{{ $period ?? date('Y-m') }}">


                    <div class="col-7">

                        <input type="file"
                               name="file"
                               accept=".zip,.xlsx,.xls,.csv"
                               required
                               class="form-control form-control-sm bg-white border-success border-opacity-25 rounded-3">

                    </div>


                    <div class="col-5">

                        <button type="submit"
                                class="btn btn-success btn-sm fw-bold w-100 rounded-3 shadow-sm">

                            <i class="fa-solid fa-file-import me-1"></i>

                            Import Log

                        </button>

                    </div>

                </form>

            </div>

        </div>


        {{-- PERIOD --}}

        <div class="col-lg-7">

            <div class="p-3 rounded-4 bg-light border border-secondary border-opacity-10 h-100 d-flex flex-column justify-content-center">

                <div class="d-flex justify-content-between align-items-center mb-2">

                    <label class="form-label fw-bold text-dark small text-uppercase tracking-wider m-0">

                        <i class="fa-regular fa-calendar-days text-primary me-1"></i>

                        Periode & Cut-off

                    </label>


                    @if($lockedState)

                        <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill px-3 py-1 fw-bold">

                            <i class="fa-solid fa-lock me-1"></i>

                            Closed s/d Tgl {{ $cutoffDay }}

                        </span>

                    @else

                        <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-1 fw-bold">

                            <i class="fa-solid fa-lock-open me-1"></i>

                            Open

                        </span>

                    @endif

                </div>


                <div class="row g-2 align-items-center">

                    <div class="col-md-6">

                        <form id="periodForm"
                              method="GET"
                              action="{{ route('payrolls.local.create') }}">

                            <div class="input-group input-group-sm">

                                <span class="input-group-text bg-white border-end-0 text-muted">

                                    <i class="fa-regular fa-calendar"></i>

                                </span>


                                <input type="month"
                                       name="period"
                                       class="form-control border-start-0 fw-bold text-dark"
                                       value="{{ old(
                                            'period',
                                            $period ?? date('Y-m')
                                       ) }}"
                                       onchange="this.form.submit()"
                                       required>

                            </div>

                        </form>

                    </div>


                    <div class="col-md-6">

                        @if($canManageCutoff)

                            <form id="cutoffForm"
                                  method="POST"
                                  action="{{ route('payrolls.local.cutoff.update') }}">

                                @csrf

                                <input type="hidden"
                                       name="period"
                                       value="{{ $period ?? date('Y-m') }}">


                                <div class="input-group input-group-sm">

                                    <span class="input-group-text bg-primary bg-opacity-10 border-end-0 text-primary fw-bold">

                                        Close Tgl

                                    </span>


                                    <select name="cutoff_day"
                                            class="form-select border-start-0 fw-bold text-primary"
                                            onchange="this.form.submit()"
                                            {{ $lockedState ? 'disabled' : '' }}>

                                        @for($day = 20; $day <= 28; $day++)

                                            <option value="{{ $day }}"
                                                {{ $cutoffDay == $day ? 'selected' : '' }}>

                                                Tanggal {{ $day }}

                                            </option>

                                        @endfor

                                    </select>

                                </div>

                            </form>

                        @else

                            <div class="input-group input-group-sm">

                                <span class="input-group-text bg-secondary bg-opacity-10 border-end-0 text-secondary fw-bold">

                                    Cut-off

                                </span>


                                <input type="text"
                                       class="form-control border-start-0 fw-bold text-secondary"
                                       value="Tanggal {{ $cutoffDay }}"
                                       readonly>

                            </div>

                        @endif

                    </div>

                </div>


                <div class="d-flex align-items-center gap-1 text-muted small mt-2"
                     style="font-size:.76rem;">

                    <i class="fa-solid fa-circle-info text-primary"></i>

                    <span>

                        <strong>
                            01–{{ $cutoffDay }}
                        </strong>
                        = payroll bulan ini.

                        <strong>
                            {{ $cutoffDay + 1 }}–{{ $endDate->format('d') }}
                        </strong>
                        = attendance lanjutan;

                        A/H0.5 pada rentang ini menjadi gantungan
                        bulan berikutnya.

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


{{-- ============================================================
     MAIN FORM
============================================================ --}}

<form id="mainPayrollForm"
      action="{{ route('payrolls.local.store') }}"
      method="POST">

    @csrf


    <input type="hidden"
           name="period_month"
           value="{{ $period ?? date('Y-m') }}">


    <input type="hidden"
           name="cutoff_day_submit"
           value="{{ $cutoffDay }}">


    {{-- ========================================================
         STICKY ACTION
    ======================================================== --}}

    <div class="payroll-action-sticky">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div class="action-status d-flex align-items-center gap-3 text-muted flex-wrap">

                <div class="d-flex align-items-center gap-1">

                    <i class="fa-solid fa-users text-primary"></i>

                    <strong>
                        {{ $totalEmployeeCount }}
                    </strong>

                    Employee

                </div>


                <div>

                    <span class="badge bg-secondary opacity-25 me-1">
                        &nbsp;
                    </span>

                    01–{{ $cutoffDay }}:

                    <strong>
                        {{ $lockedState ? 'Locked' : 'Open' }}
                    </strong>

                </div>


                <div>

                    <span class="badge bg-warning me-1">
                        &nbsp;
                    </span>

                    {{ $cutoffDay + 1 }}–{{ $endDate->format('d') }}:

                    <strong>
                        {{ $isNextPeriodLocked
                            ? 'Locked by next period'
                            : 'Draft'
                        }}
                    </strong>

                </div>

            </div>


            <div class="action-buttons d-flex align-items-center gap-2">

                @if($lockedState && $isFinanceRole)

                    <button type="submit"
                            form="formUnlockPayroll"
                            class="btn btn-outline-warning fw-bold px-3 py-2 rounded-3 shadow-sm">

                        <i class="fa-solid fa-lock-open me-1"></i>

                        Unlock Period

                    </button>

                @endif


                <button type="submit"
                        class="btn {{ $lockedState ? 'btn-primary' : 'btn-success' }} px-3 py-2 fw-bold rounded-3 shadow-sm">

                    <i class="fa-solid fa-floppy-disk me-1"></i>

                    {{ $lockedState
                        ? 'Simpan Sisa Tanggal'
                        : 'Simpan Absensi'
                    }}

                </button>


                @if(!$lockedState && $isFinanceRole)

                    <button type="submit"
                            form="formLockPayroll"
                            class="btn btn-dark px-3 py-2 fw-bold rounded-3 shadow-sm"
                            onclick="return confirm('Close periode {{ $period }} sampai tanggal {{ $cutoffDay }}? Tanggal setelah cutoff tetap dapat diisi.');">

                        <i class="fa-solid fa-lock me-1"></i>

                        Close / Lock 01–{{ $cutoffDay }}

                    </button>

                @endif

            </div>

        </div>

    </div>


    {{-- ========================================================
         TABLE
    ======================================================== --}}

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">

        <div class="table-responsive timesheet-container">

            <table class="table table-hover align-middle mb-0 text-nowrap table-bordered fs-7">

                <thead>

                    <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">

                        <th rowspan="2"
                            class="py-3 px-2 bg-dark text-white"
                            style="width:45px;">

                            No

                        </th>


                        <th rowspan="2"
                            class="py-3 px-3 text-start bg-dark text-white"
                            style="min-width:250px;">

                            Karyawan & Jabatan

                        </th>


                        <th rowspan="2"
                            class="py-3 px-3 text-start bg-dark text-white"
                            style="min-width:170px;">

                            Level, Kat & TER

                        </th>


                        <th colspan="{{ $totalDaysCount }}"
                            class="py-2 bg-primary bg-gradient text-white fw-bold">

                            <i class="fa-solid fa-calendar-days me-1"></i>

                            Kalender Absensi
                            {{ $currentPeriod->format('F Y') }}

                        </th>


                        <th colspan="{{ $recapColspan }}"
                            class="py-2 bg-info bg-gradient text-dark fw-bold">

                            Rekap Absensi

                        </th>


                        @if($showFinancialColumns)

                            <th colspan="2"
                                class="py-2 bg-warning bg-gradient text-dark fw-bold">

                                BPJS

                            </th>


                            <th colspan="{{ $financeColspan }}"
                                class="py-2 bg-success bg-gradient text-white fw-bold">

                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>

                                Variabel Finansial

                            </th>

                        @endif

                    </tr>


                    <tr class="text-center align-middle small fw-bold">

                        @foreach(\Carbon\CarbonPeriod::create($startDate, $endDate) as $dt)

                            @php

                                $dayNum =
                                    (int) $dt->format('d');

                                $isAfterCutoff =
                                    $dayNum > $cutoffDay;

                                $headerDate =
                                    $dt->format('Y-m-d');

                                $isHolidayHeader =
                                    isset(
                                        $holidayData[$headerDate]
                                    );

                            @endphp


                            <th class="p-1 {{ $isAfterCutoff ? 'bg-warning bg-opacity-25 text-dark' : 'bg-light text-dark' }}"
                                style="width:38px;font-size:.72rem;"
                                @if($isHolidayHeader)
                                    title="{{ $holidayData[$headerDate]['name'] }}"
                                @endif>

                                <div>
                                    {{ $dt->format('d') }}
                                </div>


                                <div class="{{ $isAfterCutoff ? 'text-dark fw-semibold' : 'text-muted fw-normal' }}"
                                     style="font-size:.6rem;">

                                    {{ $dt->format('M') }}

                                </div>


                                @if($isHolidayHeader)

                                    <div class="text-success fw-bold"
                                         style="font-size:.52rem;">

                                        HB

                                    </div>

                                @endif

                            </th>

                        @endforeach


                        <th class="bg-success bg-opacity-10 text-success"
                            style="min-width:72px;">

                            Hadir

                        </th>


                        <th class="bg-danger bg-opacity-10 text-danger"
                            style="min-width:85px;">

                            Absen Tdk Dibayar

                        </th>


                        <th class="bg-success bg-opacity-10 text-success"
                            style="min-width:80px;">

                            Absen Dibayar

                        </th>


                        <th class="bg-primary bg-opacity-10 text-primary"
                            style="min-width:70px;">

                            Σ Absen

                        </th>


                        <th class="bg-secondary bg-opacity-10 text-secondary"
                            style="min-width:65px;">

                            M/HB

                        </th>


                        <th class="bg-info bg-opacity-10 text-info"
                            style="min-width:75px;">

                            Normatif

                        </th>


                        <th class="bg-warning bg-opacity-10 text-dark"
                            style="min-width:78px;">

                            Gantungan

                        </th>


                        <th class="bg-primary bg-opacity-10 text-primary"
                            style="min-width:90px;">

                            Lembur (Jam)

                        </th>


                        @if($showFinancialColumns)

                            <th class="bg-warning bg-opacity-10 text-dark"
                                style="min-width:120px;">

                                BPJS TK

                            </th>


                            <th class="bg-warning bg-opacity-10 text-dark"
                                style="min-width:120px;">

                                BPJS KES

                            </th>


                            <th class="bg-success bg-opacity-10 text-success"
                                style="min-width:150px;">

                                Bonus / Insentif

                            </th>


                            <th class="bg-success bg-opacity-10 text-danger"
                                style="min-width:150px;">

                                Kasbon

                            </th>


                            <th class="bg-success bg-opacity-10 text-danger"
                                style="min-width:160px;">

                                Potongan Gantungan

                            </th>


                            <th class="bg-success bg-opacity-10 text-danger"
                                style="min-width:150px;">

                                Potongan Lain

                            </th>

                        @endif

                    </tr>

                </thead>


                @forelse($employeesByDepartment as $department => $departmentEmployees)

                    @php

                        $departmentId =
                            'department_'
                            . md5($department);

                    @endphp


                    <tbody id="{{ $departmentId }}"
                           class="department-section">

                        {{-- DEPARTMENT HEADER --}}

                        <tr class="department-row">

                            <td colspan="{{ $tableColspan }}"
                                class="p-0">

                                <div class="department-toggle d-flex justify-content-between align-items-center px-3 py-2"
                                     data-target="{{ $departmentId }}"
                                     aria-expanded="true"
                                     role="button">

                                    <div class="d-flex align-items-center gap-2">

                                        <i class="fa-solid fa-chevron-down department-chevron text-primary"></i>

                                        <i class="fa-solid fa-building text-primary"></i>

                                        <span class="fw-bold text-dark">

                                            {{ $department }}

                                        </span>


                                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill">

                                            {{ $departmentEmployees->count() }}

                                            Employee

                                        </span>

                                    </div>


                                    <div class="text-muted small d-flex align-items-center gap-2">

                                        <span class="d-none d-md-inline">

                                            Klik untuk buka / tutup

                                        </span>

                                        <i class="fa-solid fa-up-down-left-right text-primary"></i>

                                    </div>

                                </div>

                            </td>

                        </tr>


                        {{-- EMPLOYEE --}}

                        @foreach($departmentEmployees as $emp)

                            @php

                                $contract =
                                    $emp->activeContract;

                                $existing =
                                    $emp->payrolls->first();


                                /*
                                |--------------------------------------------------------------------------
                                | ATTENDANCE RECORDS
                                |--------------------------------------------------------------------------
                                */

                                $attendanceData = [];


                                foreach (
                                    $emp->attendanceRecords
                                    as $attendanceRecord
                                ) {

                                    $recordDate =
                                        $attendanceRecord
                                            ->attendance_date;

                                    if (!$recordDate) {
                                        continue;
                                    }


                                    $recordDate =
                                        $recordDate instanceof \Carbon\Carbon
                                            ? $recordDate
                                            : \Carbon\Carbon::parse(
                                                $recordDate
                                            );


                                    if (
                                        $recordDate->format('Y-m')
                                        !== $currentPeriod->format('Y-m')
                                    ) {
                                        continue;
                                    }


                                    $attendanceData[
                                        $recordDate->format('Y-m-d')
                                    ] =
                                        strtoupper(
                                            trim(
                                                (string)
                                                $attendanceRecord->status
                                            )
                                        );
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | JOB / ACCESS
                                |--------------------------------------------------------------------------
                                */

                                $jabatan =
                                    strtolower(
                                        $contract?->job_title
                                        ?? ''
                                    );


                                $isHighLevel =
                                    str_contains(
                                        $jabatan,
                                        'manager'
                                    )
                                    || str_contains(
                                        $jabatan,
                                        'kepala'
                                    )
                                    || str_contains(
                                        $jabatan,
                                        'hrd'
                                    )
                                    || str_contains(
                                        $jabatan,
                                        'direktur'
                                    );


                                $canViewLevel =
                                    $isFinanceRole
                                    || !$isHighLevel;


                                $level =
                                    $contract?->level;


                                $canViewFinancial =
                                    $isFinanceRole
                                    || (
                                        $isHeadHrd
                                        && $level !== null
                                        && (int) $level <= 13
                                    );


                                /*
                                |--------------------------------------------------------------------------
                                | REKAP ABSENSI
                                |--------------------------------------------------------------------------
                                */

                                $presentDays = 0.0;
                                $unpaidDays = 0.0;
                                $paidAbsenceDays = 0.0;
                                $normativeDays = 0.0;
                                $holidayDays = 0.0;
                                $currentUnpaidDays = 0.0;
                                $gantunganDays = 0.0;


                                foreach (
                                    \Carbon\CarbonPeriod::create(
                                        $startDate,
                                        $endDate
                                    ) as $rowDate
                                ) {

                                    $dateKey =
                                        $rowDate->format(
                                            'Y-m-d'
                                        );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | EFFECTIVE STATUS
                                    |--------------------------------------------------------------------------
                                    |
                                    | 1. Attendance record
                                    | 2. Holiday => HB
                                    | 3. Empty
                                    |
                                    */

                                    $storedStatus =
                                        $attendanceData[
                                            $dateKey
                                        ] ?? '';


                                    if (
                                        $storedStatus !== ''
                                    ) {

                                        $status =
                                            $storedStatus;

                                    } elseif (
                                        isset(
                                            $holidayData[
                                                $dateKey
                                            ]
                                        )
                                    ) {

                                        $status =
                                            'HB';

                                    } else {

                                        $status =
                                            '';
                                    }


                                    /*
                                    |--------------------------------------------------------------------------
                                    | MINGGU TANPA RECORD
                                    |--------------------------------------------------------------------------
                                    */

                                    if (
                                        $rowDate->isSunday()
                                        && $status === ''
                                    ) {

                                        continue;
                                    }


                                    switch ($status) {

                                        case 'H':

                                            $presentDays += 1;

                                            break;


                                        case 'H0.5':

                                            $presentDays += 0.5;
                                            $unpaidDays += 0.5;


                                            if (
                                                (int)
                                                $rowDate->format('d')
                                                <= $cutoffDay
                                            ) {

                                                $currentUnpaidDays
                                                    += 0.5;

                                            } else {

                                                $gantunganDays
                                                    += 0.5;
                                            }

                                            break;


                                        case 'A':
                                        case 'I':

                                            $unpaidDays += 1;


                                            if (
                                                (int)
                                                $rowDate->format('d')
                                                <= $cutoffDay
                                            ) {

                                                $currentUnpaidDays
                                                    += 1;

                                            } else {

                                                $gantunganDays
                                                    += 1;
                                            }

                                            break;


                                        case 'SKD':
                                        case 'C':
                                        case 'CM':

                                            $paidAbsenceDays += 1;


                                            if (
                                                $status === 'CM'
                                            ) {

                                                $normativeDays += 1;
                                            }

                                            break;


                                        case 'HB':

                                            /*
                                            | Hari Besar / Hari Libur
                                            | dibayar penuh.
                                            */

                                            $paidAbsenceDays += 1;

                                            $holidayDays += 1;

                                            break;
                                    }

                                }


                                $totalAttendanceDays =
                                    $presentDays
                                    + $unpaidDays
                                    + $paidAbsenceDays;


                                /*
                                |--------------------------------------------------------------------------
                                | GANTUNGAN
                                |--------------------------------------------------------------------------
                                */

                                $previousGantungan =
                                    (float) (
                                        $existing
                                            ->previous_gantungan_deduction
                                        ?? 0
                                    );


                                $nextGantungan =
                                    (float) (
                                        $existing
                                            ->gantungan_deduction
                                        ?? 0
                                    );


                                /*
                                |--------------------------------------------------------------------------
                                | BPJS PREVIEW
                                |--------------------------------------------------------------------------
                                */

                                $isManualBpjs =
                                    (bool) (
                                        $contract
                                            ?->use_manual_bpjs
                                        ?? false
                                    );


                                $existingBpjsTk =
                                    (float) (
                                        $existing
                                            ?->bpjs_tk_deduction
                                        ?? 0
                                    );


                                $existingBpjsKs =
                                    (float) (
                                        $existing
                                            ?->bpjs_ks_deduction
                                        ?? 0
                                    );


                                $hasSavedPayrollBpjs =
                                    $existingBpjsTk > 0
                                    || $existingBpjsKs > 0;


                                $bpjsTkPreview = 0.0;
                                $bpjsKsPreview = 0.0;


                                if ($isManualBpjs) {

                                    $bpjsTkPreview =
                                        (float) (
                                            $contract
                                                ?->manual_bpjs_tk_employee
                                            ?? 0
                                        );


                                    $bpjsKsPreview =
                                        (float) (
                                            $contract
                                                ?->manual_bpjs_ks_employee
                                            ?? 0
                                        );

                                } elseif (
                                    $hasSavedPayrollBpjs
                                ) {

                                    $bpjsTkPreview =
                                        $existingBpjsTk;

                                    $bpjsKsPreview =
                                        $existingBpjsKs;

                                } else {

                                    $basicSalaryPreview =
                                        (float) (
                                            $contract
                                                ?->basic_salary
                                            ?? 0
                                        );


                                    $bpjsTkPreview =
                                        (
                                            $contract
                                                ?->is_bpjstk_active
                                            ?? false
                                        )
                                            ? (
                                                $basicSalaryPreview
                                                * $tkRatePreview
                                            )
                                            : 0;


                                    $bpjsKsPreview =
                                        (
                                            $contract
                                                ?->is_bpjs_health_active
                                            ?? false
                                        )
                                            ? (
                                                min(
                                                    $basicSalaryPreview,
                                                    $ksCapPreview
                                                )
                                                * $ksRatePreview
                                            )
                                            : 0;
                                }

                            @endphp


                            <tr class="employee-row"
                                data-department-id="{{ $departmentId }}"
                                data-cutoff-day="{{ $cutoffDay }}">

                                {{-- NO --}}

                                <td class="text-center fw-bold text-muted">

                                    {{ $loop->parent->iteration }}.{{ $loop->iteration }}

                                </td>


                                {{-- EMPLOYEE --}}

                                <td>

                                    <div class="fw-bold text-dark mb-0">

                                        {{ $emp->full_name }}

                                    </div>


                                    <div class="text-primary fw-semibold"
                                         style="font-size:.78rem;">

                                        {{ $contract?->job_title ?? 'Staff' }}

                                    </div>


                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0 mt-1"
                                          style="font-size:.68rem;">

                                        NIK:
                                        {{ $emp->nik_ktp }}

                                    </span>

                                </td>


                                {{-- LEVEL --}}

                                <td>

                                    @if($canViewLevel)

                                        <small class="text-muted d-block"
                                               style="font-size:.73rem;">

                                            Kat:

                                            <strong>
                                                {{ $contract?->category ?? '-' }}
                                            </strong>

                                            |

                                            Lvl:

                                            <strong>
                                                {{ $contract?->level ?? '-' }}
                                            </strong>

                                        </small>


                                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill mt-1"
                                              style="font-size:.68rem;">

                                            PTKP:
                                            {{ $contract?->ptkp_status ?? 'TK/0' }}

                                        </span>

                                    @else

                                        <small class="text-danger fw-bold d-block"
                                               style="font-size:.73rem;">

                                            <i class="fa-solid fa-lock"
                                               style="font-size:10px;"></i>

                                            Kat: *** | Lvl: ***

                                        </small>


                                        <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill mt-1"
                                              style="font-size:.68rem;">

                                            <i class="fa-solid fa-lock"
                                               style="font-size:9px;"></i>

                                            PTKP: ***

                                        </span>

                                    @endif

                                </td>


                                {{-- =================================================
                                     CALENDAR
                                ================================================== --}}

                                @foreach(
                                    \Carbon\CarbonPeriod::create(
                                        $startDate,
                                        $endDate
                                    ) as $dt
                                )

                                    @php

                                        $dateFormatted =
                                            $dt->format('Y-m-d');

                                        $dayNum =
                                            (int) $dt->format('d');

                                        $isSunday =
                                            $dt->isSunday();


                                        $storedStatus =
                                            $attendanceData[
                                                $dateFormatted
                                            ] ?? '';


                                        $isHoliday =
                                            isset(
                                                $holidayData[
                                                    $dateFormatted
                                                ]
                                            );


                                        $holidayName =
                                            $holidayData[
                                                $dateFormatted
                                            ]['name']
                                            ?? null;


                                        /*
                                        |--------------------------------------------------------------------------
                                        | STATUS PRIORITY
                                        |--------------------------------------------------------------------------
                                        |
                                        | Attendance record > Holiday > Empty
                                        |
                                        */

                                        if (
                                            $storedStatus !== ''
                                        ) {

                                            $dayStatus =
                                                $storedStatus;

                                        } elseif (
                                            $isHoliday
                                        ) {

                                            $dayStatus =
                                                'HB';

                                        } else {

                                            $dayStatus =
                                                '';
                                        }


                                        $normalizedStatusClass =
                                            str_replace(
                                                '.',
                                                '',
                                                $dayStatus
                                            );


                                        if (
                                            $isSunday
                                            && $dayStatus === ''
                                        ) {

                                            $bgClass =
                                                'status-bg-SUNDAY';

                                        } else {

                                            $bgClass =
                                                $dayStatus !== ''
                                                    ? 'status-bg-'
                                                        . $normalizedStatusClass
                                                    : 'status-bg-empty';
                                        }


                                        /*
                                        |--------------------------------------------------------------------------
                                        | LOCK RULE
                                        |--------------------------------------------------------------------------
                                        */

                                        if (
                                            $dayNum <= $cutoffDay
                                        ) {

                                            $isCellLocked =
                                                $lockedState;

                                        } else {

                                            $isCellLocked =
                                                $isNextPeriodLocked;
                                        }


                                        $isDraftCell =
                                            $dayNum > $cutoffDay;

                                    @endphp


                                    <td class="p-0 text-center
                                        {{ $isDraftCell ? 'cell-editable-draft' : '' }}
                                        {{ $isCellLocked ? 'cell-locked' : '' }}"
                                        @if($isHoliday)
                                            title="{{ $holidayName }}"
                                        @endif>

                                        <select
                                            name="payrolls[{{ $emp->id_employee }}][daily_attendance][{{ $dateFormatted }}]"
                                            class="form-select form-select-sm border-0 attendance-select {{ $bgClass }}"
                                            style="font-size:.75rem;height:32px;"
                                            data-date="{{ $dateFormatted }}"
                                            onchange="updateStatusColor(this); recalculateSummary(this);"
                                            {{ $isCellLocked ? 'disabled' : '' }}>


                                            @if(
                                                $isSunday
                                                && $dayStatus === ''
                                            )

                                                <option value=""
                                                        selected>
                                                    -
                                                </option>

                                            @else

                                                <option value=""
                                                    {{ $dayStatus === '' ? 'selected' : '' }}>

                                                    -

                                                </option>


                                                <option value="H"
                                                    {{ $dayStatus === 'H' ? 'selected' : '' }}>

                                                    H

                                                </option>


                                                <option value="H0.5"
                                                    {{ $dayStatus === 'H0.5' ? 'selected' : '' }}>

                                                    H0.5

                                                </option>


                                                @if($isHoliday)

                                                    <option value="HB"
                                                        {{ $dayStatus === 'HB' ? 'selected' : '' }}>

                                                        HB

                                                    </option>

                                                @endif


                                                <option value="SKD"
                                                    {{ $dayStatus === 'SKD' ? 'selected' : '' }}>

                                                    SKD

                                                </option>


                                                <option value="C"
                                                    {{ $dayStatus === 'C' ? 'selected' : '' }}>

                                                    C

                                                </option>


                                                <option value="CM"
                                                    {{ $dayStatus === 'CM' ? 'selected' : '' }}>

                                                    CM

                                                </option>


                                                <option value="I"
                                                    {{ $dayStatus === 'I' ? 'selected' : '' }}>

                                                    I

                                                </option>


                                                <option value="A"
                                                    {{ $dayStatus === 'A' ? 'selected' : '' }}>

                                                    A

                                                </option>

                                            @endif

                                        </select>

                                    </td>

                                @endforeach


                                {{-- =================================================
                                     SUMMARY
                                ================================================== --}}

                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-success"
                                             data-summary="present">

                                            {{ number_format(
                                                $presentDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            Hadir

                                        </div>

                                    </div>

                                </td>


                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-danger"
                                             data-summary="unpaid">

                                            {{ number_format(
                                                $unpaidDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            Tdk Dibayar

                                        </div>


                                        <div class="summary-label text-muted"
                                             data-summary="current-unpaid">

                                            Now:

                                            {{ number_format(
                                                $currentUnpaidDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>

                                    </div>

                                </td>


                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-success"
                                             data-summary="paid">

                                            {{ number_format(
                                                $paidAbsenceDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            Dibayar

                                        </div>

                                    </div>

                                </td>


                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-primary"
                                             data-summary="total">

                                            {{ number_format(
                                                $totalAttendanceDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            Σ Absen

                                        </div>

                                    </div>

                                </td>


                                {{-- M/HB --}}

                                <td class="p-1 text-center">

                                    <div class="summary-box">

                                        <div class="summary-value text-secondary"
                                             data-summary="holiday">

                                            {{ number_format(
                                                $holidayDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            M/HB

                                        </div>

                                    </div>

                                </td>


                                {{-- NORMATIF --}}

                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-info"
                                             data-summary="normative">

                                            {{ number_format(
                                                $normativeDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            CM / Normatif

                                        </div>

                                    </div>

                                </td>


                                {{-- GANTUNGAN --}}

                                <td class="p-1">

                                    <div class="summary-box">

                                        <div class="summary-value text-dark"
                                             data-summary="carryover">

                                            {{ number_format(
                                                $gantunganDays,
                                                1,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        <div class="summary-label">

                                            Ke Bulan Depan

                                        </div>

                                    </div>

                                </td>


                                {{-- OVERTIME --}}

                                <td class="p-1">

                                    <input type="number"
                                           step="0.5"
                                           min="0"
                                           max="744"
                                           name="payrolls[{{ $emp->id_employee }}][overtime_hours]"
                                           class="form-control form-control-sm text-center fw-bold border-primary border-opacity-25"
                                           value="{{ old(
                                                'payrolls.'
                                                . $emp->id_employee
                                                . '.overtime_hours',
                                                $existing->overtime_hours ?? 0
                                           ) }}"
                                           {{ $lockedState ? 'readonly' : '' }}>

                                </td>


                                {{-- =================================================
                                     FINANCIAL
                                ================================================== --}}

                                @if($showFinancialColumns)

                                    @if($canViewFinancial)

                                        @php

                                            $isManualBpjs =
                                                (bool) (
                                                    $contract
                                                        ?->use_manual_bpjs
                                                    ?? false
                                                );

                                        @endphp


                                        {{-- BPJS TK --}}

                                        <td class="p-1 text-center">

                                            <div class="small fw-bold text-dark">

                                                Rp

                                                {{ number_format(
                                                    $bpjsTkPreview,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>


                                            @if($isManualBpjs)

                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill mt-1"
                                                      style="font-size:.58rem;">

                                                    <i class="fa-solid fa-sliders me-1"></i>

                                                    MANUAL

                                                </span>

                                            @else

                                                <div class="text-muted"
                                                     style="font-size:.58rem;">

                                                    {{ number_format(
                                                        $tkRatePreview * 100,
                                                        2,
                                                        ',',
                                                        '.'
                                                    ) }}%

                                                </div>

                                            @endif

                                        </td>


                                        {{-- BPJS KES --}}

                                        <td class="p-1 text-center">

                                            <div class="small fw-bold text-dark">

                                                Rp

                                                {{ number_format(
                                                    $bpjsKsPreview,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>


                                            @if($isManualBpjs)

                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill mt-1"
                                                      style="font-size:.58rem;">

                                                    <i class="fa-solid fa-sliders me-1"></i>

                                                    MANUAL

                                                </span>

                                            @else

                                                <div class="text-muted"
                                                     style="font-size:.58rem;">

                                                    {{ number_format(
                                                        $ksRatePreview * 100,
                                                        2,
                                                        ',',
                                                        '.'
                                                    ) }}%

                                                </div>

                                            @endif

                                        </td>


                                        {{-- INCENTIVE --}}

                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-success bg-opacity-10 border-end-0 text-success fw-bold">

                                                    Rp

                                                </span>


                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][incentive]"
                                                       class="form-control border-start-0 fw-bold text-success currency-input"
                                                       value="{{ number_format(
                                                            (float) (
                                                                $existing->incentive ?? 0
                                                            ),
                                                            0,
                                                            ',',
                                                            '.'
                                                       ) }}"
                                                       {{ (
                                                            $lockedState
                                                            || !$isFinanceRole
                                                       ) ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        {{-- CASH ADVANCE --}}

                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">

                                                    Rp

                                                </span>


                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][cash_advance]"
                                                       class="form-control border-start-0 fw-bold text-danger currency-input"
                                                       value="{{ number_format(
                                                            (float) (
                                                                $existing->cash_advance ?? 0
                                                            ),
                                                            0,
                                                            ',',
                                                            '.'
                                                       ) }}"
                                                       {{ (
                                                            $lockedState
                                                            || !$isFinanceRole
                                                       ) ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        {{-- GANTUNGAN --}}

                                        <td class="p-1">

                                            <div class="small fw-bold text-danger">

                                                Rp

                                                {{ number_format(
                                                    $previousGantungan,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>


                                            <div class="text-muted"
                                                 style="font-size:.62rem;">

                                                potong bulan ini

                                            </div>


                                            <div class="text-muted"
                                                 style="font-size:.62rem;">

                                                next:

                                                Rp

                                                {{ number_format(
                                                    $nextGantungan,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>

                                        </td>


                                        {{-- OTHER DEDUCTIONS --}}

                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">

                                                    Rp

                                                </span>


                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][other_deductions]"
                                                       class="form-control border-start-0 fw-bold text-danger currency-input"
                                                       value="{{ number_format(
                                                            (float) (
                                                                $existing->other_deductions ?? 0
                                                            ),
                                                            0,
                                                            ',',
                                                            '.'
                                                       ) }}"
                                                       {{ (
                                                            $lockedState
                                                            || !$isFinanceRole
                                                       ) ? 'readonly' : '' }}>

                                            </div>

                                        </td>

                                    @else

                                        <td colspan="6"
                                            class="text-center finance-masked">

                                            <i class="fa-solid fa-lock me-1"></i>

                                            Data finansial dibatasi
                                            untuk Level > 13

                                        </td>

                                    @endif

                                @endif

                            </tr>

                        @endforeach

                    </tbody>

                @empty

                    <tbody>

                        <tr>

                            <td colspan="{{ $tableColspan }}"
                                class="text-center py-5 text-muted">

                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 d-block mb-3"></i>

                                Belum ada data karyawan aktif
                                untuk diproses.

                            </td>

                        </tr>

                    </tbody>

                @endforelse

            </table>

        </div>


        {{-- ========================================================
             FOOTER
        ======================================================== --}}

        <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-bottom-4 border-top">

            <div class="text-muted small d-flex align-items-center gap-3 flex-wrap">

                <div>

                    <span class="badge bg-secondary opacity-25 me-1">
                        &nbsp;
                    </span>

                    01–{{ $cutoffDay }}:

                    <strong>
                        {{ $lockedState ? 'Locked' : 'Open' }}
                    </strong>

                </div>


                <div>

                    <span class="badge bg-warning me-1">
                        &nbsp;
                    </span>

                    {{ $cutoffDay + 1 }}–Akhir:

                    <strong>
                        {{ $isNextPeriodLocked
                            ? 'Locked by next period'
                            : 'Editable / Gantungan'
                        }}
                    </strong>

                </div>

            </div>


            <div>

                @if(!$lockedState && $isFinanceRole)

                    <button type="submit"
                            form="formLockPayroll"
                            class="btn btn-dark px-4 py-2 fw-bold rounded-3 shadow-sm"
                            onclick="return confirm('Close periode {{ $period }} sampai tanggal {{ $cutoffDay }}?');">

                        <i class="fa-solid fa-lock me-1"></i>

                        Close / Lock

                    </button>

                @endif

            </div>

        </div>

    </div>

</form>


{{-- ================================================================
     LOCK FORM
================================================================= --}}

<form id="formLockPayroll"
      action="{{ route('payrolls.local.lock') }}"
      method="POST"
      class="d-none">

    @csrf

    <input type="hidden"
           name="period"
           value="{{ $period ?? date('Y-m') }}">


    <input type="hidden"
           name="cutoff_day"
           value="{{ $cutoffDay }}">

</form>


{{-- ================================================================
     UNLOCK FORM
================================================================= --}}

<form id="formUnlockPayroll"
      action="{{ route('payrolls.local.unlock') }}"
      method="POST"
      class="d-none">

    @csrf

    <input type="hidden"
           name="period"
           value="{{ $period ?? date('Y-m') }}">

</form>


</div>

@endsection


@push('scripts')

<script>

function updateStatusColor(selectEl) {

    selectEl.classList.remove(
        'status-bg-H',
        'status-bg-H05',
        'status-bg-HB',
        'status-bg-SKD',
        'status-bg-C',
        'status-bg-CM',
        'status-bg-A',
        'status-bg-I',
        'status-bg-SUNDAY',
        'status-bg-empty'
    );


    const cleanVal =
        selectEl.value
            ? selectEl.value.replace('.', '')
            : 'empty';


    selectEl.classList.add(
        'status-bg-' + cleanVal
    );
}


/*
|--------------------------------------------------------------------------
| RECALCULATE SUMMARY
|--------------------------------------------------------------------------
*/

function recalculateSummary(selectEl) {

    const row =
        selectEl.closest(
            '.employee-row'
        );


    if (!row) {
        return;
    }


    const cutoff =
        parseInt(
            row.dataset.cutoffDay || '26',
            10
        );


    const selects =
        row.querySelectorAll(
            '.attendance-select'
        );


    let present = 0;
    let unpaid = 0;
    let paid = 0;
    let holiday = 0;
    let normative = 0;
    let currentUnpaid = 0;
    let carryover = 0;


    selects.forEach((select) => {

        const status =
            select.value;


        const dateString =
            select.dataset.date || '';


        const day =
            parseInt(
                dateString.slice(-2),
                10
            );


        /*
        |--------------------------------------------------------------------------
        | HADIR
        |--------------------------------------------------------------------------
        */

        if (status === 'H') {

            present += 1;

        }


        /*
        |--------------------------------------------------------------------------
        | HALF DAY
        |--------------------------------------------------------------------------
        */

        else if (
            status === 'H0.5'
        ) {

            present += 0.5;

            unpaid += 0.5;


            if (day <= cutoff) {

                currentUnpaid += 0.5;

            } else {

                carryover += 0.5;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | TIDAK DIBAYAR
        |--------------------------------------------------------------------------
        */

        else if (
            status === 'A'
            || status === 'I'
        ) {

            unpaid += 1;


            if (day <= cutoff) {

                currentUnpaid += 1;

            } else {

                carryover += 1;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | ABSEN DIBAYAR
        |--------------------------------------------------------------------------
        */

        else if (
            ['SKD', 'C', 'CM', 'HB']
                .includes(status)
        ) {

            paid += 1;


            if (status === 'CM') {

                normative += 1;

            }


            if (status === 'HB') {

                holiday += 1;

            }

        }

    });


    const total =
        present
        + unpaid
        + paid;


    const set =
        (
            key,
            value
        ) => {

            const el =
                row.querySelector(
                    `[data-summary="${key}"]`
                );


            if (!el) {
                return;
            }


            el.textContent =
                Number(value).toLocaleString(
                    'id-ID',
                    {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1
                    }
                );
        };


    set(
        'present',
        present
    );


    set(
        'unpaid',
        unpaid
    );


    set(
        'paid',
        paid
    );


    set(
        'total',
        total
    );


    set(
        'holiday',
        holiday
    );


    set(
        'normative',
        normative
    );


    set(
        'current-unpaid',
        currentUnpaid
    );


    set(
        'carryover',
        carryover
    );
}


/*
|--------------------------------------------------------------------------
| DEPARTMENT TOGGLE
|--------------------------------------------------------------------------
*/

function toggleDepartment(button) {

    const targetId =
        button.dataset.target;


    const target =
        document.getElementById(
            targetId
        );


    if (!target) {
        return;
    }


    const rows =
        target.querySelectorAll(
            '.employee-row'
        );


    const shouldShow =
        target.dataset.collapsed !== '1';


    rows.forEach(
        row => {

            row.style.display =
                shouldShow
                    ? 'none'
                    : '';

        }
    );


    target.dataset.collapsed =
        shouldShow
            ? '1'
            : '0';


    button.setAttribute(
        'aria-expanded',
        shouldShow
            ? 'false'
            : 'true'
    );
}


/*
|--------------------------------------------------------------------------
| DOM READY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | DEPARTMENT TOGGLE
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.department-toggle'
            )
            .forEach(
                button => {

                    button.addEventListener(
                        'click',
                        function () {

                            toggleDepartment(
                                this
                            );

                        }
                    );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | INITIAL SUMMARY
        |--------------------------------------------------------------------------
        |
        | Penting:
        | Saat halaman pertama kali dibuka,
        | summary langsung dihitung ulang dari select.
        |
        */

        document
            .querySelectorAll(
                '.attendance-select'
            )
            .forEach(
                select => {

                    updateStatusColor(
                        select
                    );

                }
            );


        document
            .querySelectorAll(
                '.employee-row'
            )
            .forEach(
                row => {

                    const firstSelect =
                        row.querySelector(
                            '.attendance-select'
                        );


                    if (firstSelect) {

                        recalculateSummary(
                            firstSelect
                        );

                    }

                }
            );


        /*
        |--------------------------------------------------------------------------
        | CURRENCY INPUT
        |--------------------------------------------------------------------------
        */

        const currencyInputs =
            document.querySelectorAll(
                '.currency-input'
            );


        const formatCurrency =
            (val) => {

                const clean =
                    val
                        .toString()
                        .replace(
                            /[^0-9]/g,
                            ''
                        );


                return clean
                    ? new Intl.NumberFormat(
                        'id-ID'
                    ).format(clean)
                    : '0';
            };


        currencyInputs.forEach(
            input => {

                input.value =
                    formatCurrency(
                        input.value || '0'
                    );


                if (!input.readOnly) {

                    input.addEventListener(
                        'input',
                        function () {

                            this.value =
                                formatCurrency(
                                    this.value
                                );

                        }
                    );


                    input.addEventListener(
                        'focus',
                        function () {

                            if (
                                this.value === '0'
                            ) {

                                this.value = '';

                            }

                        }
                    );


                    input.addEventListener(
                        'blur',
                        function () {

                            if (
                                this.value === ''
                            ) {

                                this.value = '0';

                            }

                        }
                    );

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | BEFORE SUBMIT
        |--------------------------------------------------------------------------
        |
        | "1.500.000" -> "1500000"
        |
        */

        const mainForm =
            document.getElementById(
                'mainPayrollForm'
            );


        if (mainForm) {

            mainForm.addEventListener(
                'submit',
                function () {

                    currencyInputs.forEach(
                        input => {

                            input.value =
                                input.value.replace(
                                    /\./g,
                                    ''
                                );

                        }
                    );

                }
            );

        }

    }
);

</script>

@endpush
