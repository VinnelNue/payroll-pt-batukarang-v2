@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian - Outer Island')

@section('page_title', 'Form Input Absensi & Komponen Variabel - Outer Island')

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
        font-family: system-ui, -apple-system, BlinkMacSystemFont,
                     "Segoe UI", Roboto, Arial, sans-serif !important;
        font-weight: 800 !important;
        font-style: normal !important;
        cursor: pointer;
        transition: all .2s ease-in-out;
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

    .status-bg-S {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
    }

    .status-bg-C {
        background-color: #fef3c7 !important;
        color: #d97706 !important;
    }

    .status-bg-CM {
        background-color: #f3e8ff !important;
        color: #7e22ce !important;
    }

    .status-bg-MHB {
        background-color: #f3e8ff !important;
        color: #7e22ce !important;
        font-weight: 900 !important;
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
        background: rgba(255,255,255,.96);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 14px;
        padding: .75rem 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 .35rem 1rem rgba(0,0,0,.10);
    }

    .payroll-action-sticky .action-status {
        font-size: .76rem;
    }

    .summary-box {
        min-width: 64px;
        padding: .25rem .35rem;
        border-radius: 8px;
        background: #fff;
        text-align: center;
    }

    .summary-value {
        font-size: .82rem;
        font-weight: 800;
        line-height: 1.05;
    }

    .summary-label {
        font-size: .58rem;
        color: #6c757d;
        line-height: 1.05;
        margin-top: 2px;
    }

    .finance-masked {
        color: #adb5bd;
        background: #f8f9fa;
    }

    .outer-badge {
        font-size: .60rem;
        letter-spacing: .02em;
    }

    .bpjs-auto {
        font-size: .58rem;
        color: #6c757d;
    }

    .bpjs-override {
        font-size: .58rem;
    }

    .employee-id-badge {
        font-size: .58rem;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT STATUS
    |--------------------------------------------------------------------------
    */

    .contract-status-badge {
        font-size: .60rem;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .contract-expired-cell {
        background-color: #fff1f2 !important;
    }

    .contract-expired-select {
        background-color: #fff1f2 !important;
        color: #dc2626 !important;
        cursor: not-allowed !important;
        opacity: .90;
    }

    .contract-before-cell {
        background-color: #fff8e1 !important;
    }

    .contract-before-select {
        background-color: #fff8e1 !important;
        color: #b45309 !important;
        cursor: not-allowed !important;
        opacity: .90;
    }

    @media (max-width: 768px) {

        .payroll-action-sticky {
            top: 8px;
            padding: .65rem;
        }

        .payroll-action-sticky .action-status {
            width: 100%;
            margin-bottom: .25rem;
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

/*
|--------------------------------------------------------------------------
| ROLE
|--------------------------------------------------------------------------
*/

$userRole = strtolower(
    trim(Auth::user()->role ?? '')
);

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

$canManageCutoff = $isFinanceRole;

$lockedState = (bool) ($isLocked ?? false);

$isNextPeriodLocked = (bool) ($isNextPeriodLocked ?? false);

$cutoffDay = (int) (
    $cutoffDay
    ?? $savedCutoffDay
    ?? 26
);


/*
|--------------------------------------------------------------------------
| PERIOD
|--------------------------------------------------------------------------
*/

$currentPeriod = \Carbon\Carbon::parse(
    ($period ?? date('Y-m')) . '-01'
);

$startDate = $currentPeriod->copy()->startOfMonth();

$endDate = $currentPeriod->copy()->endOfMonth();

$totalDaysCount = iterator_count(
    \Carbon\CarbonPeriod::create(
        $startDate,
        $endDate
    )
);


/*
|--------------------------------------------------------------------------
| EMPLOYEE ORDER
|--------------------------------------------------------------------------
*/

$employees = $employees
    ->sortBy('id_employee_outer_island')
    ->values();

$totalEmployeeCount = $employees->count();


/*
|--------------------------------------------------------------------------
| HOLIDAY DATA
|--------------------------------------------------------------------------
*/

$holidayData = [];

foreach ($holidays ?? [] as $holiday) {

    $holidayDate = $holiday->holiday_date;

    if (!$holidayDate) {
        continue;
    }

    $holidayDate =
        $holidayDate instanceof \Carbon\Carbon
            ? $holidayDate
            : \Carbon\Carbon::parse($holidayDate);

    if (
        $holidayDate->format('Y-m')
        !== $currentPeriod->format('Y-m')
    ) {
        continue;
    }

    $holidayData[
        $holidayDate->format('Y-m-d')
    ] = [
        'name' => $holiday->name,
        'type' => $holiday->type,
    ];
}


/*
|--------------------------------------------------------------------------
| BPJS SETTINGS
|--------------------------------------------------------------------------
*/

$tkRatePreview = (
    $bpjsTkEmployeeRate !== null
        ? (float) $bpjsTkEmployeeRate
        : 2.0
) / 100;

$ksRatePreview = (
    $bpjsKsEmployeeRate !== null
        ? (float) $bpjsKsEmployeeRate
        : 1.0
) / 100;

$ksCapPreview =
    $bpjsKsMaxCap !== null
        ? (float) $bpjsKsMaxCap
        : 12000000;


/*
|--------------------------------------------------------------------------
| TABLE COLSPAN
|--------------------------------------------------------------------------
*/

$recapColspan = 8;

$bpjsColspan =
    ($isFinanceRole || $isHeadHrd)
        ? 2
        : 0;

$financeColspan =
    ($isFinanceRole || $isHeadHrd)
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

                    <i class="fa-solid fa-plane-departure text-primary"></i>

                    <span>
                        Input Absensi & Payroll Outer Island
                    </span>

                </h5>

                <p class="text-muted small m-0 mt-1">

                    Klik
                    <strong>Generate Absensi Bulan Ini</strong>
                    untuk membuat absensi otomatis ke database.

                    Setelah generate:
                    <strong>H</strong> untuk hari kerja,
                    <strong>-</strong> untuk Minggu,
                    <strong>HB</strong> untuk hari libur.

                    Data absensi yang belum digenerate akan tetap kosong.

                </p>

            </div>


            <div class="d-flex align-items-center gap-2">

                @if($isFinanceRole || $isHeadHrd)

                    <a
                        href="{{ route(
                            'payrolls.outer_island.export-bca',
                            [
                                'period' =>
                                    $period
                                    ?? date('Y-m')
                            ]
                        ) }}"
                        class="btn btn-outline-success btn-sm px-3 py-2 rounded-3 fw-semibold"
                    >

                        <i class="fa-solid fa-file-csv me-1"></i>

                        Export CSV BCA

                    </a>

                @endif


                <a
                    href="{{ route(
                        'payrolls.outer_island.index'
                    ) }}"
                    class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-medium"
                >

                    <i class="fa-solid fa-arrow-left me-1"></i>

                    Kembali ke Rekap

                </a>

            </div>

        </div>


        <hr class="my-3 opacity-10">


        @if(session('success'))

            <div class="alert alert-success border-0 rounded-3 mb-3 py-2 px-3 small">

                <i class="fa-solid fa-circle-check me-1"></i>

                {{ session('success') }}

            </div>

        @endif


        @if(session('error'))

            <div class="alert alert-danger border-0 rounded-3 mb-3 py-2 px-3 small">

                <i class="fa-solid fa-circle-exclamation me-1"></i>

                {{ session('error') }}

            </div>

        @endif


        @if($errors->any())

            <div class="alert alert-danger border-0 rounded-3 mb-3 py-2 px-3 small">

                <div class="fw-bold mb-1">

                    <i class="fa-solid fa-triangle-exclamation me-1"></i>

                    Terjadi kesalahan:

                </div>

                @foreach($errors->all() as $error)

                    <div>
                        {{ $error }}
                    </div>

                @endforeach

            </div>

        @endif


        <div class="row g-3">


            {{-- ATTENDANCE INFO --}}

            <div class="col-lg-4">

                <div class="p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 h-100">

                    <div class="d-flex align-items-center gap-2 mb-2">

                        <div
                            class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center"
                            style="width:32px;height:32px;"
                        >

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>

                        <h6 class="fw-bold text-primary m-0">
                            Absensi Outer Island
                        </h6>

                    </div>


                    <p class="text-muted small mb-2">

                        Absensi tidak dibuat saat import karyawan.

                        Klik
                        <strong>Generate Absensi Bulan Ini</strong>
                        untuk membuat record absensi ke database.

                    </p>


                    <div class="d-flex flex-wrap gap-1">

                        <span class="badge bg-success-subtle text-success border border-success rounded-pill">
                            H = Hari Kerja
                        </span>

                        <span class="badge bg-secondary-subtle text-secondary border border-secondary rounded-pill">
                            - = Minggu
                        </span>

                        <span class="badge bg-success-subtle text-success border border-success rounded-pill">
                            HB = Hari Libur
                        </span>

                    </div>


                    <div
                        class="mt-2 text-muted"
                        style="font-size:.70rem;"
                    >

                        Sebelum generate, database absensi tetap kosong.

                        Setelah generate, status dapat diganti menjadi
                        SKD, S, C, CM, M/HB, H0.5, A atau I.

                    </div>

                </div>

            </div>


            {{-- PERIOD + CUTOFF --}}

            <div class="col-lg-8">

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


                        {{-- PERIOD --}}

                        <div class="col-md-4">

                            <form
                                id="periodForm"
                                method="GET"
                                action="{{ route(
                                    'payrolls.outer_island.create'
                                ) }}"
                            >

                                <div class="input-group input-group-sm">

                                    <span class="input-group-text bg-white border-end-0 text-muted">

                                        <i class="fa-regular fa-calendar"></i>

                                    </span>


                                    <input
                                        type="month"
                                        name="period"
                                        class="form-control border-start-0 fw-bold text-dark"
                                        value="{{ old(
                                            'period',
                                            $period
                                                ?? date('Y-m')
                                        ) }}"
                                        onchange="this.form.submit()"
                                        required
                                    >

                                </div>

                            </form>

                        </div>


                        {{-- GENERATE --}}

                        <div class="col-md-4">

                            @if(!$lockedState && $isFinanceRole)

                                <form
                                    id="generateAttendanceForm"
                                    method="POST"
                                    action="{{ route(
                                        'payrolls.outer_island.generate_attendance'
                                    ) }}"
                                >

                                    @csrf

                                    <input
                                        type="hidden"
                                        name="period"
                                        value="{{ $period ?? date('Y-m') }}"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-sm w-100 fw-bold rounded-3"
                                        onclick="return confirm(
                                            'Generate absensi otomatis untuk periode {{ $period ?? date('Y-m') }}?\n\nData absensi yang sudah ada TIDAK akan ditimpa.'
                                        );"
                                    >

                                        <i class="fa-solid fa-calendar-check me-1"></i>

                                        Generate Absensi Bulan Ini

                                    </button>

                                </form>

                            @elseif(!$lockedState)

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm w-100 fw-bold rounded-3"
                                    disabled
                                >

                                    <i class="fa-solid fa-lock me-1"></i>

                                    Generate oleh Finance

                                </button>

                            @else

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm w-100 fw-bold rounded-3"
                                    disabled
                                >

                                    <i class="fa-solid fa-lock me-1"></i>

                                    Periode Sudah Di-lock

                                </button>

                            @endif

                        </div>


                        {{-- CUTOFF --}}

                        <div class="col-md-4">

                            @if($canManageCutoff)

                                <form
                                    id="cutoffForm"
                                    method="POST"
                                    action="{{ route(
                                        'payrolls.outer_island.cutoff.update'
                                    ) }}"
                                >

                                    @csrf

                                    <input
                                        type="hidden"
                                        name="period"
                                        value="{{ $period ?? date('Y-m') }}"
                                    >

                                    <div class="input-group input-group-sm">

                                        <span class="input-group-text bg-primary bg-opacity-10 border-end-0 text-primary fw-bold">

                                            Close Tgl

                                        </span>


                                        <select
                                            name="cutoff_day"
                                            class="form-select border-start-0 fw-bold text-primary"
                                            onchange="this.form.submit()"
                                            {{ $lockedState ? 'disabled' : '' }}
                                        >

                                            @for($day = 20; $day <= 28; $day++)

                                                <option
                                                    value="{{ $day }}"
                                                    {{ $cutoffDay == $day ? 'selected' : '' }}
                                                >

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


                                    <input
                                        type="text"
                                        class="form-control border-start-0 fw-bold text-secondary"
                                        value="Tanggal {{ $cutoffDay }}"
                                        readonly
                                    >

                                </div>

                            @endif

                        </div>

                    </div>


                    <div
                        class="d-flex align-items-center gap-1 text-muted small mt-2"
                        style="font-size:.76rem;"
                    >

                        <i class="fa-solid fa-circle-info text-primary"></i>

                        <span>

                            <strong>
                                01–{{ $cutoffDay }}
                            </strong>

                            = payroll bulan ini.

                            <strong>
                                {{ $cutoffDay + 1 }}–{{ $endDate->format('d') }}
                            </strong>

                            = attendance lanjutan.

                            A/H0.5 pada rentang ini menjadi gantungan.

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
         MAIN FORM
    ============================================================ --}}

    <form
        id="mainPayrollForm"
        action="{{ route(
            'payrolls.outer_island.store'
        ) }}"
        method="POST"
    >

        @csrf

        <input
            type="hidden"
            name="period"
            value="{{ $period ?? date('Y-m') }}"
        >

        <input
            type="hidden"
            name="cutoff_day"
            value="{{ $cutoffDay }}"
        >


        {{-- ACTION BAR --}}

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

                        <button
                            type="submit"
                            form="formUnlockPayroll"
                            class="btn btn-outline-warning fw-bold px-3 py-2 rounded-3 shadow-sm"
                        >

                            <i class="fa-solid fa-lock-open me-1"></i>

                            Unlock Period

                        </button>

                    @endif


                    <button
                        type="submit"
                        class="btn {{ $lockedState ? 'btn-primary' : 'btn-success' }} px-3 py-2 fw-bold rounded-3 shadow-sm"
                    >

                        <i class="fa-solid fa-floppy-disk me-1"></i>

                        {{ $lockedState
                            ? 'Simpan Sisa Tanggal'
                            : 'Simpan Absensi'
                        }}

                    </button>


                    @if(!$lockedState && $isFinanceRole)

                        <button
                            type="submit"
                            form="formLockPayroll"
                            class="btn btn-dark px-3 py-2 fw-bold rounded-3 shadow-sm"
                            onclick="return confirm(
                                'Close periode {{ $period }} sampai tanggal {{ $cutoffDay }}? Tanggal setelah cutoff tetap dapat diisi.'
                            );"
                        >

                            <i class="fa-solid fa-lock me-1"></i>

                            Close / Lock 01–{{ $cutoffDay }}

                        </button>

                    @endif

                </div>

            </div>

        </div>


        {{-- ========================================================
             TABLE
        ========================================================= --}}

        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">

            <div class="table-responsive timesheet-container">

                <table class="table table-hover align-middle mb-0 text-nowrap table-bordered fs-7">

                    <thead>

                        <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">

                            <th
                                rowspan="2"
                                class="py-3 px-2 bg-dark text-white"
                                style="width:45px;"
                            >
                                No
                            </th>


                            <th
                                rowspan="2"
                                class="py-3 px-3 text-start bg-dark text-white"
                                style="min-width:280px;"
                            >
                                Karyawan & Jabatan
                            </th>


                            <th
                                rowspan="2"
                                class="py-3 px-3 text-start bg-dark text-white"
                                style="min-width:170px;"
                            >
                                Level, Kat & TER
                            </th>


                            <th
                                colspan="{{ $totalDaysCount }}"
                                class="py-2 bg-primary bg-gradient text-white fw-bold"
                            >

                                <i class="fa-solid fa-calendar-days me-1"></i>

                                Kalender Absensi
                                {{ $currentPeriod->format('F Y') }}

                            </th>


                            <th
                                colspan="{{ $recapColspan }}"
                                class="py-2 bg-info bg-gradient text-dark fw-bold"
                            >

                                Rekap Absensi

                            </th>


                            @if($isFinanceRole || $isHeadHrd)

                                <th
                                    colspan="2"
                                    class="py-2 bg-warning bg-gradient text-dark fw-bold"
                                >
                                    BPJS
                                </th>


                                <th
                                    colspan="{{ $financeColspan }}"
                                    class="py-2 bg-success bg-gradient text-white fw-bold"
                                >

                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>

                                    Variabel Finansial

                                </th>

                            @endif

                        </tr>


                        <tr class="text-center align-middle small fw-bold">

                            @foreach(
                                \Carbon\CarbonPeriod::create(
                                    $startDate,
                                    $endDate
                                ) as $dt
                            )

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


                                <th
                                    class="p-1 {{
                                        $isAfterCutoff
                                            ? 'bg-warning bg-opacity-25 text-dark'
                                            : 'bg-light text-dark'
                                    }}"
                                    style="width:38px;font-size:.72rem;"
                                    @if($isHolidayHeader)
                                        title="{{ $holidayData[$headerDate]['name'] }}"
                                    @endif
                                >

                                    <div>
                                        {{ $dt->format('d') }}
                                    </div>


                                    <div
                                        class="{{
                                            $isAfterCutoff
                                                ? 'text-dark fw-semibold'
                                                : 'text-muted fw-normal'
                                        }}"
                                        style="font-size:.6rem;"
                                    >

                                        {{ $dt->format('M') }}

                                    </div>


                                    @if($isHolidayHeader)

                                        <div
                                            class="text-success fw-bold"
                                            style="font-size:.52rem;"
                                        >
                                            HB
                                        </div>

                                    @endif

                                </th>

                            @endforeach


                            <th
                                class="bg-success bg-opacity-10 text-success"
                                style="min-width:72px;"
                            >
                                Hadir
                            </th>


                            <th
                                class="bg-danger bg-opacity-10 text-danger"
                                style="min-width:85px;"
                            >
                                Absen Tdk Dibayar
                            </th>


                            <th
                                class="bg-success bg-opacity-10 text-success"
                                style="min-width:80px;"
                            >
                                Absen Dibayar
                            </th>


                            <th
                                class="bg-primary bg-opacity-10 text-primary"
                                style="min-width:70px;"
                            >
                                Σ Absen
                            </th>


                            <th
                                class="bg-secondary bg-opacity-10 text-secondary"
                                style="min-width:65px;"
                            >
                                M/HB
                            </th>


                            <th
                                class="bg-info bg-opacity-10 text-info"
                                style="min-width:75px;"
                            >
                                Normatif
                            </th>


                            <th
                                class="bg-warning bg-opacity-10 text-dark"
                                style="min-width:78px;"
                            >
                                Gantungan
                            </th>


                            <th
                                class="bg-primary bg-opacity-10 text-primary"
                                style="min-width:90px;"
                            >
                                Lembur (Jam)
                            </th>


                            @if($isFinanceRole || $isHeadHrd)

                                <th
                                    class="bg-warning bg-opacity-10 text-dark"
                                    style="min-width:120px;"
                                >
                                    BPJS TK
                                </th>


                                <th
                                    class="bg-warning bg-opacity-10 text-dark"
                                    style="min-width:120px;"
                                >
                                    BPJS KES
                                </th>


                                <th
                                    class="bg-success bg-opacity-10 text-success"
                                    style="min-width:150px;"
                                >
                                    Bonus / Insentif
                                </th>


                                <th
                                    class="bg-success bg-opacity-10 text-danger"
                                    style="min-width:150px;"
                                >
                                    Kasbon
                                </th>


                                <th
                                    class="bg-success bg-opacity-10 text-danger"
                                    style="min-width:160px;"
                                >
                                    Potongan Gantungan
                                </th>


                                <th
                                    class="bg-success bg-opacity-10 text-danger"
                                    style="min-width:150px;"
                                >
                                    Potongan Lain
                                </th>

                            @endif

                        </tr>

                    </thead>


                    <tbody>

                    @forelse($employees as $emp)

                        @php

                            /*
                            |--------------------------------------------------------------------------
                            | CONTRACT OUTER ISLAND
                            |--------------------------------------------------------------------------
                            */

                            $contract =
                                $emp->contractMaster?->currentHistory;


                            /*
                            |--------------------------------------------------------------------------
                            | CONTRACT STATUS
                            |--------------------------------------------------------------------------
                            |
                            | Status berasal dari controller.
                            | Tidak membuat kolom baru di database.
                            */

                            $contractStatus =
                                $emp->contract_status
                                ?? 'Tidak Ada Kontrak';

                            $contractIsActive =
                                (bool) (
                                    $emp->contract_status_active
                                    ?? false
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | CONTRACT STATUS CLASS
                            |--------------------------------------------------------------------------
                            */

                            $contractStatusClass = match (
                                $contractStatus
                            ) {

                                'Aktif'
                                    => 'bg-success-subtle text-success border-success',

                                'Kontrak Habis'
                                    => 'bg-danger-subtle text-danger border-danger',

                                'Belum Masuk Periode Kontrak'
                                    => 'bg-warning-subtle text-warning-emphasis border-warning',

                                'Tidak Ada Kontrak'
                                    => 'bg-secondary-subtle text-secondary border-secondary',

                                'Karyawan Tidak Aktif'
                                    => 'bg-dark-subtle text-dark border-dark',

                                default
                                    => 'bg-secondary-subtle text-secondary border-secondary',
                            };


                            /*
                            |--------------------------------------------------------------------------
                            | PAYROLL PERIODE SAAT INI
                            |--------------------------------------------------------------------------
                            */

                            $existing =
                                $emp->payroll
                                ?? $payrolls->get(
                                    $emp->id_employee_outer_island
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | ATTENDANCE
                            |--------------------------------------------------------------------------
                            */

                            $attendanceData = [];

                            foreach (
                                ($emp->attendance ?? collect())
                                as $attendanceRecord
                            ) {

                                $recordDate =
                                    $attendanceRecord->attendance_date;

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
                            | LEVEL / CATEGORY
                            |--------------------------------------------------------------------------
                            */

                            $level =
                                $contract?->level;

                            $category =
                                $contract?->category;


                            /*
                            |--------------------------------------------------------------------------
                            | FINANCIAL ACCESS
                            |--------------------------------------------------------------------------
                            */

                            $canViewFinancial =
                                $isFinanceRole
                                ||
                                (
                                    $isHeadHrd
                                    && $level !== null
                                    && (int) $level <= 13
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | LEVEL ACCESS
                            |--------------------------------------------------------------------------
                            */

                            $canViewLevel =
                                $isFinanceRole
                                ||
                                (
                                    $level !== null
                                    && (int) $level <= 13
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | ATTENDANCE SUMMARY
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
                                    $rowDate->format('Y-m-d');

                                $storedStatus =
                                    $attendanceData[
                                        $dateKey
                                    ] ?? '';

                                $status =
                                    $storedStatus;


                                switch ($status) {

                                    case 'H':

                                        $presentDays += 1;

                                        break;


                                    case 'H0.5':

                                        $presentDays += .5;

                                        $unpaidDays += .5;

                                        if (
                                            (int) $rowDate->format('d')
                                            <= $cutoffDay
                                        ) {

                                            $currentUnpaidDays += .5;

                                        } else {

                                            $gantunganDays += .5;

                                        }

                                        break;


                                    case 'A':
                                    case 'I':

                                        $unpaidDays += 1;

                                        if (
                                            (int) $rowDate->format('d')
                                            <= $cutoffDay
                                        ) {

                                            $currentUnpaidDays += 1;

                                        } else {

                                            $gantunganDays += 1;

                                        }

                                        break;


                                    case 'SKD':
                                    case 'S':
                                    case 'C':
                                    case 'CM':
                                    case 'M/HB':

                                        $paidAbsenceDays += 1;

                                        if (
                                            $status === 'CM'
                                            ||
                                            $status === 'M/HB'
                                        ) {

                                            $normativeDays += 1;

                                        }

                                        break;


                                    case 'HB':

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
                                        ?->previous_gantungan_deduction
                                    ?? 0
                                );

                            $nextGantungan =
                                (float) (
                                    $existing
                                        ?->gantungan_deduction
                                    ?? 0
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | BPJS
                            |--------------------------------------------------------------------------
                            */

                            $isManualBpjs = (int) (
                                $contract?->use_manual_bpjs ?? 0
                            ) === 1;


                            $isBpjsTkActive = (bool) (
                                $contract?->is_bpjstk_active ?? false
                            );

                            $isBpjsKsActive = (bool) (
                                $contract?->is_bpjs_health_active ?? false
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | BASIC SALARY
                            |--------------------------------------------------------------------------
                            */

                            $basicSalaryPreview = (float) (
                                $contract?->basic_salary ?? 0
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | MANUAL BPJS
                            |--------------------------------------------------------------------------
                            */

                            $manualBpjsTk = (float) (
                                $contract?->manual_bpjs_tk_employee ?? 0
                            );

                            $manualBpjsKs = (float) (
                                $contract?->manual_bpjs_ks_employee ?? 0
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | BPJS TK
                            |--------------------------------------------------------------------------
                            */

                            if ($isManualBpjs) {

                                $bpjsTkPreview =
                                    $isBpjsTkActive
                                        ? $manualBpjsTk
                                        : 0;

                            } else {

                                $bpjsTkPreview =
                                    $isBpjsTkActive
                                        ? (
                                            $basicSalaryPreview
                                            * $tkRatePreview
                                        )
                                        : 0;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | BPJS KESEHATAN
                            |--------------------------------------------------------------------------
                            */

                            if ($isManualBpjs) {

                                $bpjsKsPreview =
                                    $isBpjsKsActive
                                        ? $manualBpjsKs
                                        : 0;

                            } else {

                                $bpjsKsPreview =
                                    $isBpjsKsActive
                                        ? (
                                            min(
                                                $basicSalaryPreview,
                                                $ksCapPreview
                                            )
                                            * $ksRatePreview
                                        )
                                        : 0;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | MODE BPJS
                            |--------------------------------------------------------------------------
                            */

                            $bpjsMode =
                                $isManualBpjs
                                    ? 'OVERRIDE'
                                    : 'AUTO';

                        @endphp


                        {{-- ====================================================
                             EMPLOYEE ROW
                        ===================================================== --}}

                        <tr
                            class="employee-row"
                            data-employee-id="{{ $emp->id_employee_outer_island }}"
                            data-cutoff-day="{{ $cutoffDay }}"
                            data-contract-status="{{ $contractStatus }}"
                        >


                            {{-- NO --}}

                            <td class="text-center fw-bold text-muted">

                                {{ $loop->iteration }}

                                <div
                                    class="employee-id-badge text-muted mt-1"
                                >

                                    ID:
                                    {{ $emp->id_employee_outer_island }}

                                </div>

                            </td>


                            {{-- EMPLOYEE --}}

                            <td>

                                <div class="fw-bold text-dark mb-0">

                                    {{ $emp->full_name_outer }}

                                </div>


                                <div
                                    class="text-primary fw-semibold"
                                    style="font-size:.78rem;"
                                >

                                    {{ $contract?->job_title ?? 'Staff' }}

                                </div>


                                <span
                                    class="badge bg-light text-secondary border rounded-pill px-2 py-0 mt-1"
                                    style="font-size:.68rem;"
                                >

                                    NIK:
                                    {{ $emp->nik_ktp_outer ?? '-' }}

                                </span>


                                <span
                                    class="badge bg-primary-subtle text-primary border border-primary rounded-pill px-2 py-0 mt-1 outer-badge"
                                >

                                    OUTER ISLAND

                                </span>


                                {{-- CONTRACT STATUS --}}

                                <div class="mt-1">

                                    <span
                                        class="badge {{ $contractStatusClass }} border rounded-pill contract-status-badge px-2 py-1"
                                    >

                                        @if($contractStatus === 'Aktif')

                                            <i class="fa-solid fa-circle-check me-1"></i>

                                        @elseif($contractStatus === 'Kontrak Habis')

                                            <i class="fa-solid fa-circle-xmark me-1"></i>

                                        @elseif($contractStatus === 'Belum Masuk Periode Kontrak')

                                            <i class="fa-solid fa-clock me-1"></i>

                                        @elseif($contractStatus === 'Tidak Ada Kontrak')

                                            <i class="fa-solid fa-file-circle-xmark me-1"></i>

                                        @else

                                            <i class="fa-solid fa-circle-exclamation me-1"></i>

                                        @endif

                                        {{ $contractStatus }}

                                    </span>

                                </div>


                                {{-- CONTRACT DATE --}}

                                @if($contract)

                                    <div
                                        class="text-muted mt-1"
                                        style="font-size:.58rem;"
                                    >

                                        <i class="fa-regular fa-calendar me-1"></i>

                                        {{ $contract->start_date
                                            ? \Carbon\Carbon::parse(
                                                $contract->start_date
                                            )->format('d/m/Y')
                                            : '-'
                                        }}

                                        s/d

                                        {{ $contract->end_date
                                            ? \Carbon\Carbon::parse(
                                                $contract->end_date
                                            )->format('d/m/Y')
                                            : 'Tidak ditentukan'
                                        }}

                                    </div>

                                @endif

                            </td>


                            {{-- LEVEL / CATEGORY / PTKP --}}

                            <td>

                                @if($canViewLevel)

                                    <small
                                        class="text-muted d-block"
                                        style="font-size:.73rem;"
                                    >

                                        Kat:

                                        <strong>
                                            {{ $category ?? '-' }}
                                        </strong>

                                        |

                                        Lvl:

                                        <strong>
                                            {{ $level ?? '-' }}
                                        </strong>

                                    </small>


                                    <span
                                        class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill mt-1"
                                        style="font-size:.68rem;"
                                    >

                                        PTKP:
                                        {{ $contract?->ptkp_status ?? 'TK/0' }}

                                    </span>

                                @else

                                    <small
                                        class="text-danger fw-bold d-block"
                                        style="font-size:.73rem;"
                                    >

                                        <i
                                            class="fa-solid fa-lock"
                                            style="font-size:10px;"
                                        ></i>

                                        Kat: *** | Lvl: ***

                                    </small>


                                    <span
                                        class="badge bg-danger-subtle text-danger border border-danger rounded-pill mt-1"
                                        style="font-size:.68rem;"
                                    >

                                        <i
                                            class="fa-solid fa-lock"
                                            style="font-size:9px;"
                                        ></i>

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


                                    /*
                                    |--------------------------------------------------------------------------
                                    | CONTRACT DATE CHECK
                                    |--------------------------------------------------------------------------
                                    */

                                    $dateIsBeforeContract =
                                        $contract?->start_date
                                        &&
                                        $dt->copy()->startOfDay()->lt(
                                            \Carbon\Carbon::parse(
                                                $contract->start_date
                                            )->startOfDay()
                                        );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | IMPORTANT:
                                    | END DATE ITSELF IS EXPIRED
                                    |--------------------------------------------------------------------------
                                    */

                                    $dateIsAfterContract =
                                        $contract?->end_date
                                        &&
                                        $dt->copy()->startOfDay()->greaterThanOrEqualTo(
                                            \Carbon\Carbon::parse(
                                                $contract->end_date
                                            )->startOfDay()
                                        );


                                    $dateOutsideContract =
                                        !$contractIsActive
                                        ||
                                        $dateIsBeforeContract
                                        ||
                                        $dateIsAfterContract;


                                    /*
                                    |--------------------------------------------------------------------------
                                    | IMPORTANT:
                                    | $dateOutsideContract hanya menjadi indikator visual.
                                    | Jangan dipakai untuk disable input attendance.
                                    |
                                    | Aturan kontrak:
                                    | - Generate Attendance => mengikuti periode kontrak.
                                    | - Input/Edit manual   => tetap boleh selama payroll belum locked.
                                    |--------------------------------------------------------------------------
                                    */


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

                                    $dayStatus =
                                        $storedStatus;


                                    /*
                                    |--------------------------------------------------------------------------
                                    | STATUS CLASS
                                    |--------------------------------------------------------------------------
                                    */

                                    $normalizedStatusClass =
                                        str_replace(
                                            ['.', '/'],
                                            ['', ''],
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
                                                ? 'status-bg-' .
                                                    $normalizedStatusClass
                                                : 'status-bg-empty';

                                    }


                                    /*
                                    |--------------------------------------------------------------------------
                                    | LOCK
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


                                    /*
                                    |--------------------------------------------------------------------------
                                    | CONTRACT CELL CLASS
                                    |--------------------------------------------------------------------------
                                    */

                                    $contractCellClass = '';

                                    if ($dateIsAfterContract) {

                                        $contractCellClass =
                                            'contract-expired-cell';

                                    } elseif ($dateIsBeforeContract) {

                                        $contractCellClass =
                                            'contract-before-cell';

                                    }

                                @endphp


                                <td
                                    class="p-0 text-center
                                        {{ $isDraftCell ? 'cell-editable-draft' : '' }}
                                        {{ $isCellLocked ? 'cell-locked' : '' }}
                                        {{ $contractCellClass }}"
                                    @if($isHoliday)
                                        title="{{ $holidayName }}"
                                    @elseif($dateIsAfterContract)
                                        title="Di luar periode kontrak — input manual tetap diperbolehkan"
                                    @elseif($dateIsBeforeContract)
                                        title="Sebelum periode kontrak — input manual tetap diperbolehkan"
                                    @endif
                                >


                                    <select
                                        name="attendance[{{ $emp->id_employee_outer_island }}][{{ $dateFormatted }}]"
                                        class="form-select form-select-sm border-0 attendance-select
                                            {{ $bgClass }}
                                            {{
                                                $dateIsAfterContract
                                                    ? 'contract-expired-select'
                                                    : ''
                                            }}
                                            {{
                                                $dateIsBeforeContract
                                                    ? 'contract-before-select'
                                                    : ''
                                            }}"
                                        style="font-size:.75rem;height:32px;"
                                        data-date="{{ $dateFormatted }}"
                                        onchange="updateStatusColor(this); recalculateSummary(this);"
                                        {{
                                            $isCellLocked
                                                ? 'disabled'
                                                : ''
                                        }}
                                    >


                                        <option
                                            value=""
                                            {{ $dayStatus === '' ? 'selected' : '' }}
                                        >
                                            -
                                        </option>


                                        <option
                                            value="H"
                                            {{ $dayStatus === 'H' ? 'selected' : '' }}
                                        >
                                            H
                                        </option>


                                        <option
                                            value="H0.5"
                                            {{ $dayStatus === 'H0.5' ? 'selected' : '' }}
                                        >
                                            H0.5
                                        </option>


                                        @if(
                                            $isHoliday
                                            ||
                                            $dayStatus === 'HB'
                                        )

                                            <option
                                                value="HB"
                                                {{ $dayStatus === 'HB' ? 'selected' : '' }}
                                            >
                                                HB
                                            </option>

                                        @endif


                                        <option
                                            value="SKD"
                                            {{ $dayStatus === 'SKD' ? 'selected' : '' }}
                                        >
                                            SKD
                                        </option>


                                        <option
                                            value="S"
                                            {{ $dayStatus === 'S' ? 'selected' : '' }}
                                        >
                                            S
                                        </option>


                                        <option
                                            value="C"
                                            {{ $dayStatus === 'C' ? 'selected' : '' }}
                                        >
                                            C
                                        </option>


                                        <option
                                            value="CM"
                                            {{ $dayStatus === 'CM' ? 'selected' : '' }}
                                        >
                                            CM
                                        </option>


                                        <option
                                            value="M/HB"
                                            {{ $dayStatus === 'M/HB' ? 'selected' : '' }}
                                        >
                                            M/HB
                                        </option>


                                        <option
                                            value="I"
                                            {{ $dayStatus === 'I' ? 'selected' : '' }}
                                        >
                                            I
                                        </option>


                                        <option
                                            value="A"
                                            {{ $dayStatus === 'A' ? 'selected' : '' }}
                                        >
                                            A
                                        </option>

                                    </select>

                                </td>

                            @endforeach


                            {{-- HADIR --}}

                            <td class="p-1">

                                <div class="summary-box">

                                    <div
                                        class="summary-value text-success"
                                        data-summary="present"
                                    >

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


                            {{-- UNPAID --}}

                            <td class="p-1">

                                <div class="summary-box">

                                    <div
                                        class="summary-value text-danger"
                                        data-summary="unpaid"
                                    >

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


                                    <div
                                        class="summary-label text-muted"
                                        data-summary="current-unpaid"
                                    >

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


                            {{-- PAID --}}

                            <td class="p-1">

                                <div class="summary-box">

                                    <div
                                        class="summary-value text-success"
                                        data-summary="paid"
                                    >

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


                            {{-- TOTAL --}}

                            <td class="p-1">

                                <div class="summary-box">

                                    <div
                                        class="summary-value text-primary"
                                        data-summary="total"
                                    >

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

                                    <div
                                        class="summary-value text-secondary"
                                        data-summary="holiday"
                                    >

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


                            {{-- NORMATIVE --}}

                            <td class="p-1">

                                <div class="summary-box">

                                    <div
                                        class="summary-value text-info"
                                        data-summary="normative"
                                    >

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

                                    <div
                                        class="summary-value text-dark"
                                        data-summary="carryover"
                                    >

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

                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="744"
                                    name="payrolls[{{ $emp->id_employee_outer_island }}][overtime_hours]"
                                    class="form-control form-control-sm text-center fw-bold border-primary border-opacity-25"
                                    value="{{ old(
                                        'payrolls.'
                                        . $emp->id_employee_outer_island
                                        . '.overtime_hours',
                                        $existing?->overtime_hours ?? 0
                                    ) }}"
                                    {{ $lockedState ? 'readonly' : '' }}
                                >

                            </td>


                            {{-- =================================================
                                 FINANCIAL
                            ================================================== --}}

                            @if($isFinanceRole || $isHeadHrd)

                                @if($canViewFinancial)

                                    {{-- BPJS TK --}}

                                    <td class="p-1 text-center">

                                        <div class="small fw-bold text-dark">

                                            Rp
                                            {{ number_format(
                                                $isManualBpjs
                                                    ? $manualBpjsTk
                                                    : $bpjsTkPreview,
                                                0,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        @if($isManualBpjs)

                                            <span
                                                class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill mt-1 bpjs-override"
                                            >

                                                <i class="fa-solid fa-sliders me-1"></i>

                                                OVERRIDE

                                            </span>


                                            <div
                                                class="text-muted"
                                                style="font-size:.55rem;"
                                            >

                                                Manual:

                                                Rp
                                                {{ number_format(
                                                    $manualBpjsTk,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>

                                        @else

                                            <span
                                                class="badge bg-success-subtle text-success-emphasis border border-success rounded-pill mt-1"
                                            >

                                                <i class="fa-solid fa-calculator me-1"></i>

                                                AUTO

                                            </span>


                                            <div
                                                class="text-muted"
                                                style="font-size:.55rem;"
                                            >

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
                                                $isManualBpjs
                                                    ? $manualBpjsKs
                                                    : $bpjsKsPreview,
                                                0,
                                                ',',
                                                '.'
                                            ) }}

                                        </div>


                                        @if($isManualBpjs)

                                            <span
                                                class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill mt-1 bpjs-override"
                                            >

                                                <i class="fa-solid fa-sliders me-1"></i>

                                                OVERRIDE

                                            </span>


                                            <div
                                                class="text-muted"
                                                style="font-size:.55rem;"
                                            >

                                                Manual:

                                                Rp
                                                {{ number_format(
                                                    $manualBpjsKs,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}

                                            </div>

                                        @else

                                            <span
                                                class="badge bg-success-subtle text-success-emphasis border border-success rounded-pill mt-1"
                                            >

                                                <i class="fa-solid fa-calculator me-1"></i>

                                                AUTO

                                            </span>


                                            <div
                                                class="text-muted"
                                                style="font-size:.55rem;"
                                            >

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


                                            <input
                                                type="text"
                                                name="payrolls[{{ $emp->id_employee_outer_island }}][incentive]"
                                                class="form-control border-start-0 fw-bold text-success currency-input"
                                                value="{{ number_format(
                                                    (float) (
                                                        $existing?->incentive
                                                        ?? 0
                                                    ),
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}"
                                                {{
                                                    (
                                                        $lockedState
                                                        ||
                                                        !$isFinanceRole
                                                    )
                                                        ? 'readonly'
                                                        : ''
                                                }}
                                            >

                                        </div>

                                    </td>


                                    {{-- CASH ADVANCE --}}

                                    <td class="p-1">

                                        <div class="input-group input-group-sm">

                                            <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">
                                                Rp
                                            </span>


                                            <input
                                                type="text"
                                                name="payrolls[{{ $emp->id_employee_outer_island }}][cash_advance]"
                                                class="form-control border-start-0 fw-bold text-danger currency-input"
                                                value="{{ number_format(
                                                    (float) (
                                                        $existing?->cash_advance
                                                        ?? 0
                                                    ),
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}"
                                                {{
                                                    (
                                                        $lockedState
                                                        ||
                                                        !$isFinanceRole
                                                    )
                                                        ? 'readonly'
                                                        : ''
                                                }}
                                            >

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


                                        <div
                                            class="text-muted"
                                            style="font-size:.62rem;"
                                        >

                                            potong bulan ini

                                        </div>


                                        <div
                                            class="text-muted"
                                            style="font-size:.62rem;"
                                        >

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


                                            <input
                                                type="text"
                                                name="payrolls[{{ $emp->id_employee_outer_island }}][other_deductions]"
                                                class="form-control border-start-0 fw-bold text-danger currency-input"
                                                value="{{ number_format(
                                                    (float) (
                                                        $existing?->other_deductions
                                                        ?? 0
                                                    ),
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}"
                                                {{
                                                    (
                                                        $lockedState
                                                        ||
                                                        !$isFinanceRole
                                                    )
                                                        ? 'readonly'
                                                        : ''
                                                }}
                                            >

                                        </div>

                                    </td>

                                @else

                                    <td
                                        colspan="6"
                                        class="text-center finance-masked"
                                    >

                                        <i class="fa-solid fa-lock me-1"></i>

                                        Data finansial dibatasi
                                        untuk Level &gt; 13

                                    </td>

                                @endif

                            @endif


                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="{{ $tableColspan }}"
                                class="text-center py-5 text-muted"
                            >

                                <i
                                    class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 d-block mb-3"
                                ></i>

                                Belum ada data karyawan Outer Island aktif
                                untuk diproses.

                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>


            {{-- FOOTER --}}

            <div
                class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-bottom-4 border-top"
            >

                <div
                    class="text-muted small d-flex align-items-center gap-3 flex-wrap"
                >

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


                    <div>

                        <span class="badge bg-danger me-1">
                            &nbsp;
                        </span>

                        <strong>
                            Kontrak Habis
                        </strong>

                    </div>

                </div>


                <div>

                    @if(!$lockedState && $isFinanceRole)

                        <button
                            type="submit"
                            form="formLockPayroll"
                            class="btn btn-dark px-4 py-2 fw-bold rounded-3 shadow-sm"
                            onclick="return confirm(
                                'Close periode {{ $period }} sampai tanggal {{ $cutoffDay }}?'
                            );"
                        >

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

    <form
        id="formLockPayroll"
        action="{{ route(
            'payrolls.outer_island.lock'
        ) }}"
        method="POST"
        class="d-none"
    >

        @csrf

        <input
            type="hidden"
            name="period"
            value="{{ $period ?? date('Y-m') }}"
        >

        <input
            type="hidden"
            name="cutoff_day"
            value="{{ $cutoffDay }}"
        >

    </form>


    {{-- ================================================================
         UNLOCK FORM
    ================================================================= --}}

    <form
        id="formUnlockPayroll"
        action="{{ route(
            'payrolls.outer_island.unlock'
        ) }}"
        method="POST"
        class="d-none"
    >

        @csrf

        <input
            type="hidden"
            name="period"
            value="{{ $period ?? date('Y-m') }}"
        >

    </form>

</div>

@endsection


@push('scripts')
<script>

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS COLOR
    |--------------------------------------------------------------------------
    */

    function updateStatusColor(selectEl) {

        if (!selectEl) {
            return;
        }


        selectEl.classList.remove(
            'status-bg-H',
            'status-bg-H05',
            'status-bg-HB',
            'status-bg-SKD',
            'status-bg-S',
            'status-bg-C',
            'status-bg-CM',
            'status-bg-MHB',
            'status-bg-A',
            'status-bg-I',
            'status-bg-SUNDAY',
            'status-bg-empty'
        );


        const cleanVal =
            selectEl.value
                ? selectEl.value
                    .replace('.', '')
                    .replace('/', '')
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


        selects.forEach(
            (select) => {

                const status =
                    select.value;


                const dateString =
                    select.dataset.date || '';


                const day =
                    parseInt(
                        dateString.slice(-2),
                        10
                    );


                if (status === 'H') {

                    present += 1;

                }

                else if (status === 'H0.5') {

                    present += .5;

                    unpaid += .5;


                    if (day <= cutoff) {

                        currentUnpaid += .5;

                    } else {

                        carryover += .5;

                    }

                }

                else if (
                    status === 'A'
                    ||
                    status === 'I'
                ) {

                    unpaid += 1;


                    if (day <= cutoff) {

                        currentUnpaid += 1;

                    } else {

                        carryover += 1;

                    }

                }

                else if (
                    [
                        'SKD',
                        'S',
                        'C',
                        'CM',
                        'M/HB',
                        'HB'
                    ].includes(status)
                ) {

                    paid += 1;


                    if (
                        status === 'CM'
                        ||
                        status === 'M/HB'
                    ) {

                        normative += 1;

                    }


                    if (status === 'HB') {

                        holiday += 1;

                    }

                }

            }
        );


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
    | DOM READY
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function () {


            /*
            |--------------------------------------------------------------------------
            | STATUS COLOR
            |--------------------------------------------------------------------------
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


            /*
            |--------------------------------------------------------------------------
            | INITIAL SUMMARY
            |--------------------------------------------------------------------------
            */

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