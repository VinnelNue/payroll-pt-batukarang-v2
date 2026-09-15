@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian')
@section('page_title', 'Form Input Absensi & Komponen Variabel')

@push('styles')
<style>
    /* RESET FONT ABSENSI */
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

    /* Styling status absensi */
    .status-bg-empty {
        background-color: #f8f9fa !important;
        color: #6c757d !important;
        font-weight: normal !important;
    }

    .status-bg-H {
        background-color: #ffffff !important;
        color: #198754 !important;
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

    .attendance-select option {
        font-weight: bold;
        text-align: center;
        background-color: #ffffff;
        color: #333333;
    }

    /* Visual pembeda untuk tanggal sisa (editable) */
    .cell-editable-draft {
        background-color: #fffbe2 !important;
    }

    .timesheet-container {
        max-width: 100%;
        overflow-x: auto;
        white-space: nowrap;
    }

    /* ============================================================
       STICKY ACTION BAR
       ============================================================ */
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

    /* ============================================================
       DEPARTMENT GROUP
       ============================================================ */
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

    .department-toggle[aria-expanded="true"] .department-chevron {
        transform: rotate(0deg);
    }

    .department-toggle[aria-expanded="false"] .department-chevron {
        transform: rotate(-90deg);
    }

    .department-count {
        font-size: 0.72rem;
    }

    /* ============================================================
       MOBILE
       ============================================================ */
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
    $isFinancialRole = in_array(Auth::user()->role ?? '', ['manager_keuangan', 'super_admin']);
    $lockedState     = (isset($isLocked) && $isLocked);

    // TANGGAL CUT-OFF / CLOSE PERIODE
    // Bisa diatur Manager Keuangan, Default: 26
    $cutoffDay = request('cutoff_day', $savedCutoffDay ?? 26);

    // TANGGAL KALENDER NORMAL BULAN BERJALAN
    $currentPeriod  = \Carbon\Carbon::parse(($period ?? date('Y-m')) . '-01');
    $startDate      = $currentPeriod->copy()->startOfMonth();
    $endDate        = $currentPeriod->copy()->endOfMonth();

    $datePeriod     = \Carbon\CarbonPeriod::create($startDate, $endDate);
    $totalDaysCount = iterator_count($datePeriod);

    /*
    |--------------------------------------------------------------------------
    | GROUPING DEPARTMENT
    |--------------------------------------------------------------------------
    | Department diambil langsung dari ACTIVE CONTRACT masing-masing employee.
    | Tidak ada daftar department hard-code.
    |--------------------------------------------------------------------------
    */
    $employeesByDepartment = $employees->groupBy(function ($employee) {
        return $employee->activeContract->department
            ?? 'Tanpa Department';
    });

    $totalEmployeeCount = $employees->count();
@endphp

<div class="container-fluid p-0">

    <!-- ============================================================
         TOP BAR CONTROL & HEADER
         ============================================================ -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
                <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-primary"></i>
                    <span>Input Absensi Harian & Variabel Bulanan</span>
                </h5>

                <p class="text-muted small m-0 mt-1">
                    Gunakan form di bawah untuk input absensi kalender normal.
                    Penutupan (*Close*) mengunci tanggal 01 s/d {{ $cutoffDay }}.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">

                @if($isFinancialRole)
                    <a href="{{ route('payrolls.local.export-bca', ['period' => $period ?? date('Y-m')]) }}"
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

        <!-- ========================================================
             CONTROL PANEL
             ======================================================== -->
        <div class="row g-3">

            <!-- IMPORT LOG MESIN -->
            <div class="col-lg-5">

                <div class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">

                    <div class="d-flex align-items-center gap-2 mb-2">

                        <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center"
                             style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-file-zipper"></i>
                        </div>

                        <h6 class="fw-bold text-success m-0">
                            Import Log Mesin Fingerprint
                        </h6>

                    </div>

                    <p class="text-muted small mb-3">
                        Upload file <strong>.ZIP</strong> atau Excel absensi mesin.
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
                                   accept=".zip,.rar,.xlsx,.xls,.csv"
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

            <!-- SETTING PERIODE & TANGGAL CLOSE -->
            <div class="col-lg-7">

                <div class="p-3 rounded-4 bg-light border border-secondary border-opacity-10 h-100 d-flex flex-column justify-content-center">

                    <div class="d-flex justify-content-between align-items-center mb-2">

                        <label class="form-label fw-bold text-dark small text-uppercase tracking-wider m-0">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>
                            Periode Bulan & Tanggal Close
                        </label>

                        @if($lockedState)

                            <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill px-3 py-1 fw-bold">
                                <i class="fa-solid fa-lock me-1"></i>
                                Closed s/d Tgl {{ $cutoffDay }}
                            </span>

                        @else

                            <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-1 fw-bold">
                                <i class="fa-solid fa-lock-open me-1"></i>
                                Open (Semua Tgl Editable)
                            </span>

                        @endif

                    </div>

                    <div class="row g-2 align-items-center">

                        <!-- SELECT BULAN -->
                        <div class="col-md-6">

                            <div class="input-group input-group-sm">

                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fa-regular fa-calendar"></i>
                                </span>

                                <input type="month"
                                       name="period_month"
                                       form="mainPayrollForm"
                                       class="form-control border-start-0 fw-bold text-dark"
                                       value="{{ old('period_month', $period ?? date('Y-m')) }}"
                                       onchange="window.location.href='{{ route('payrolls.local.create') }}?period='+this.value+'&cutoff_day={{ $cutoffDay }}'"
                                       required>

                            </div>

                        </div>

                        <!-- DROPDOWN TANGGAL CLOSE -->
                        <div class="col-md-6">

                            <div class="input-group input-group-sm">

                                <span class="input-group-text bg-primary bg-opacity-10 border-end-0 text-primary fw-bold">
                                    Close Tgl
                                </span>

                                <select name="cutoff_day"
                                        form="mainPayrollForm"
                                        class="form-select border-start-0 fw-bold text-primary"
                                        onchange="window.location.href='{{ route('payrolls.local.create') }}?period={{ $period ?? date('Y-m') }}&cutoff_day='+this.value"
                                        {{ (!$isFinancialRole || $lockedState) ? 'disabled' : '' }}>

                                    @for($day = 20; $day <= 28; $day++)

                                        <option value="{{ $day }}"
                                            {{ (int)$cutoffDay === $day ? 'selected' : '' }}>
                                            Tanggal {{ $day }}
                                            {{ (int)$cutoffDay === $day ? '(Aktif)' : '' }}
                                        </option>

                                    @endfor

                                </select>

                            </div>

                        </div>

                    </div>

                    <div class="d-flex align-items-center gap-1 text-muted small mt-2"
                         style="font-size: 0.76rem;">

                        <i class="fa-solid fa-circle-info text-primary"></i>

                        <span>
                            Jika dikunci:
                            <strong>01 s/d {{ $cutoffDay }}</strong> terkunci.
                            Tanggal
                            <strong>{{ $cutoffDay + 1 }} s/d {{ $endDate->format('d') }}</strong>
                            tetap bisa diisi.
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         MAIN PAYROLL FORM
         ============================================================ -->
    <form id="mainPayrollForm"
          action="{{ route('payrolls.local.store') }}"
          method="POST">

        @csrf

        <input type="hidden"
               name="cutoff_day_submit"
               value="{{ $cutoffDay }}">


        <!-- ========================================================
             STICKY ACTION BAR
             ======================================================== -->
        <div class="payroll-action-sticky">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <!-- STATUS -->
                <div class="action-status d-flex align-items-center gap-3 text-muted">

                    <div class="d-flex align-items-center gap-1">
                        <i class="fa-solid fa-users text-primary"></i>
                        <strong>{{ $totalEmployeeCount }}</strong>
                        Employee
                    </div>

                    <div>
                        <span class="badge bg-secondary opacity-25 me-1">&nbsp;</span>
                        01 - {{ $cutoffDay }}:
                        <strong>{{ $lockedState ? 'Locked' : 'Open' }}</strong>
                    </div>

                    <div class="d-none d-lg-block">
                        <span class="badge bg-warning me-1">&nbsp;</span>
                        {{ $cutoffDay + 1 }} - {{ $endDate->format('d') }}:
                        <strong>Draft</strong>
                    </div>

                </div>


                <!-- ACTION BUTTONS -->
                <div class="action-buttons d-flex align-items-center gap-2">

                    @if($lockedState)

                        @if($isFinancialRole)

                            <button type="submit"
                                    form="formUnlockPayroll"
                                    class="btn btn-outline-warning fw-bold px-3 py-2 rounded-3 shadow-sm">

                                <i class="fa-solid fa-lock-open me-1"></i>
                                Unlock Period

                            </button>

                        @endif

                        <button type="submit"
                                class="btn btn-primary px-3 py-2 fw-bold rounded-3 shadow-sm">

                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Simpan Perubahan

                        </button>

                    @else

                        <button type="submit"
                                class="btn btn-success px-3 py-2 fw-bold rounded-3 shadow-sm">

                            <i class="fa-solid fa-calculator me-1"></i>
                            Simpan Absensi

                        </button>

                        <button type="submit"
                                form="formLockPayroll"
                                class="btn btn-dark px-3 py-2 fw-bold rounded-3 shadow-sm"
                                onclick="return confirm('Apakah Anda yakin ingin mengunci (Close) absensi s/d tanggal {{ $cutoffDay }}?');">

                            <i class="fa-solid fa-lock me-1"></i>
                            Close / Lock

                        </button>

                    @endif

                </div>

            </div>

        </div>


        <!-- ========================================================
             SPREADSHEET TABLE
             ======================================================== -->
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">

            <div class="table-responsive timesheet-container">

                <table class="table table-hover align-middle mb-0 text-nowrap table-bordered fs-7">

                    <thead>

                        <!-- HEADER UTAMA -->
                        <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">

                            <th rowspan="2"
                                class="py-3 px-2 bg-dark text-white"
                                style="width: 45px;">
                                No
                            </th>

                            <th rowspan="2"
                                class="py-3 px-3 text-start bg-dark text-white"
                                style="min-width: 250px;">
                                Karyawan & Jabatan
                            </th>

                            <th rowspan="2"
                                class="py-3 px-3 text-start bg-dark text-white"
                                style="min-width: 170px;">
                                Level, Kat & TER
                            </th>

                            <th colspan="{{ $totalDaysCount }}"
                                class="py-2 bg-primary bg-gradient text-white fw-bold">

                                <i class="fa-solid fa-calendar-days me-1"></i>
                                Kalender Absensi {{ $currentPeriod->format('F Y') }}

                            </th>

                            <th colspan="2"
                                class="py-2 bg-info bg-gradient text-dark fw-bold">
                                Rekap Jam/Hari
                            </th>

                            @if($isFinancialRole)

                                <th colspan="5"
                                    class="py-2 bg-success bg-gradient text-white fw-bold">

                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>
                                    Variabel Finansial (Manager Keuangan Only)

                                </th>

                            @endif

                        </tr>


                        <!-- SUB HEADER TANGGAL -->
                        <tr class="text-center align-middle small fw-bold">

                            @foreach($datePeriod as $dt)

                                @php
                                    $dayNum = (int)$dt->format('d');
                                    $isAfterCutoff = $dayNum > $cutoffDay;
                                @endphp

                                <th class="p-1 {{ $isAfterCutoff ? 'bg-warning bg-opacity-25 text-dark' : 'bg-light text-dark' }}"
                                    style="width: 38px; font-size: 0.72rem;">

                                    <div>
                                        {{ $dt->format('d') }}
                                    </div>

                                    <div class="{{ $isAfterCutoff ? 'text-dark fw-semibold' : 'text-muted fw-normal' }}"
                                         style="font-size: 0.6rem;">
                                        {{ $dt->format('M') }}
                                    </div>

                                </th>

                            @endforeach

                            <th class="bg-danger bg-opacity-10 text-danger"
                                style="width: 75px;">
                                Alpha (A)
                            </th>

                            <th class="bg-primary bg-opacity-10 text-primary"
                                style="width: 75px;">
                                Lembur(J)
                            </th>

                            @if($isFinancialRole)

                                <th class="bg-success bg-opacity-10 text-success"
                                    style="min-width: 150px;">
                                    Cuti Melahirkan
                                </th>

                                <th class="bg-success bg-opacity-10 text-success"
                                    style="min-width: 150px;">
                                    Bonus / Insentif
                                </th>

                                <th class="bg-success bg-opacity-10 text-danger"
                                    style="min-width: 170px;">
                                    Kasbon
                                </th>

                                <th class="bg-success bg-opacity-10 text-danger"
                                    style="min-width: 170px;">
                                    Potongan Gantungan
                                </th>

                                <th class="bg-success bg-opacity-10 text-danger"
                                    style="min-width: 150px;">
                                    Potongan Lain
                                </th>

                            @endif

                        </tr>

                    </thead>


                    <tbody>

                        @forelse($employeesByDepartment as $department => $departmentEmployees)

                            @php
                                $departmentId = 'department_' . md5($department);
                            @endphp


                            <!-- ====================================================
                                 DEPARTMENT HEADER
                                 ==================================================== -->
                            <tr class="department-row">

                                <td colspan="{{ 5 + $totalDaysCount + ($isFinancialRole ? 5 : 0) }}"
                                    class="p-0">

                                    <div class="department-toggle d-flex justify-content-between align-items-center px-3 py-2"
                                         data-bs-toggle="collapse"
                                         data-bs-target="#{{ $departmentId }}"
                                         aria-expanded="true"
                                         aria-controls="{{ $departmentId }}">

                                        <div class="d-flex align-items-center gap-2">

                                            <i class="fa-solid fa-chevron-down department-chevron text-primary"></i>

                                            <i class="fa-solid fa-building text-primary"></i>

                                            <span class="fw-bold text-dark">
                                                {{ $department }}
                                            </span>

                                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill department-count">
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


                            <!-- ====================================================
                                 EMPLOYEE GROUP
                                 ==================================================== -->
                            <tbody id="{{ $departmentId }}"
                                   class="collapse show department-body">

                            @foreach($departmentEmployees as $emp)

                                @php

                                    $contract = $emp->activeContract;

                                    $existing = $emp->payrolls
                                        ->where('period_month', $period)
                                        ->first();

                                    $dailyData = [];

                                    if ($existing && $existing->daily_attendance) {

                                        $dailyData = is_array($existing->daily_attendance)
                                            ? $existing->daily_attendance
                                            : (json_decode($existing->daily_attendance, true) ?? []);

                                    }

                                    $jabatan = strtolower($contract->job_title ?? '');

                                    $isHighLevel =
                                        str_contains($jabatan, 'manager') ||
                                        str_contains($jabatan, 'kepala') ||
                                        str_contains($jabatan, 'hrd') ||
                                        str_contains($jabatan, 'direktur');

                                    $canViewLevel =
                                        $isFinancialRole ||
                                        !$isHighLevel;

                                @endphp


                                <tr>

                                    <!-- NOMOR -->
                                    <td class="text-center fw-bold text-muted">
                                        {{ $loop->parent->iteration }}.{{ $loop->iteration }}
                                    </td>


                                    <!-- NAMA & JABATAN -->
                                    <td>

                                        <div class="fw-bold text-dark mb-0">
                                            {{ $emp->full_name }}
                                        </div>

                                        <div class="text-primary fw-semibold"
                                             style="font-size: 0.78rem;">
                                            {{ $contract->job_title ?? 'Staff' }}
                                        </div>

                                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-0 mt-1"
                                              style="font-size: 0.68rem;">
                                            NIK: {{ $emp->nik_ktp }}
                                        </span>

                                    </td>


                                    <!-- LEVEL, KAT & TER -->
                                    <td>

                                        @if($canViewLevel)

                                            <small class="text-muted d-block"
                                                   style="font-size: 0.73rem;">

                                                Kat:
                                                <strong>
                                                    {{ $contract->category ?? '-' }}
                                                </strong>

                                                |

                                                Lvl:
                                                <strong>
                                                    {{ $contract->level ?? '-' }}
                                                </strong>

                                            </small>

                                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill mt-1"
                                                  style="font-size: 0.68rem;">

                                                PTKP:
                                                {{ $contract->ptkp_status ?? 'TK/0' }}

                                            </span>

                                        @else

                                            <small class="text-danger fw-bold d-block"
                                                   style="font-size: 0.73rem;">

                                                <i class="fa-solid fa-lock"
                                                   style="font-size: 10px;"></i>

                                                Kat: *** | Lvl: ***

                                            </small>

                                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill mt-1"
                                                  style="font-size: 0.68rem;">

                                                <i class="fa-solid fa-lock"
                                                   style="font-size: 9px;"></i>

                                                PTKP: ***

                                            </span>

                                        @endif

                                    </td>


                                    <!-- LOOPING TANGGAL KALENDER -->
                                    @foreach($datePeriod as $dt)

                                        @php

                                            $dateFormatted = $dt->format('Y-m-d');

                                            $dayNum = (int)$dt->format('d');

                                            $dayStatus =
                                                $dailyData[$dateFormatted]
                                                ?? $dailyData[$dayNum]
                                                ?? '';

                                            $bgClass =
                                                $dayStatus !== ''
                                                ? 'status-bg-' . str_replace('.', '', $dayStatus)
                                                : 'status-bg-empty';

                                            // LOGIKA LOCKING PRESISI
                                            if ($dayNum <= $cutoffDay) {

                                                $isCellLocked = $lockedState;

                                            } else {

                                                $isCellLocked = $isNextPeriodLocked;

                                            }

                                            $isDraftCell = $dayNum > $cutoffDay;

                                        @endphp


                                        <td class="p-0 text-center {{ $isDraftCell ? 'cell-editable-draft' : '' }}">

                                            <select name="payrolls[{{ $emp->id_employee }}][daily_attendance][{{ $dateFormatted }}]"
                                                    class="form-select form-select-sm border-0 attendance-select {{ $bgClass }}"
                                                    style="font-size: 0.75rem; height: 32px;"
                                                    onchange="updateStatusColor(this); recalculateSummary(this);"
                                                    {{ $isCellLocked ? 'disabled' : '' }}>

                                                <option value=""
                                                        class="text-muted"
                                                        {{ (string)$dayStatus === '' ? 'selected' : '' }}>
                                                    -
                                                </option>

                                                <option value="H"
                                                        class="text-success"
                                                        {{ (string)$dayStatus === 'H' ? 'selected' : '' }}>
                                                    H
                                                </option>

                                                <option value="H0.5"
                                                        class="text-warning fw-bold"
                                                        {{ (string)$dayStatus === 'H0.5' ? 'selected' : '' }}>
                                                    H0.5
                                                </option>

                                                <option value="SKD"
                                                        class="text-info"
                                                        {{ (string)$dayStatus === 'SKD' ? 'selected' : '' }}>
                                                    SKD
                                                </option>

                                                <option value="C"
                                                        class="text-primary"
                                                        {{ (string)$dayStatus === 'C' ? 'selected' : '' }}>
                                                    C
                                                </option>

                                                <option value="CM"
                                                        class="text-purple"
                                                        {{ (string)$dayStatus === 'CM' ? 'selected' : '' }}>
                                                    CM
                                                </option>

                                                <option value="A"
                                                        class="text-danger"
                                                        {{ (string)$dayStatus === 'A' ? 'selected' : '' }}>
                                                    A
                                                </option>

                                            </select>

                                        </td>

                                    @endforeach


                                    <!-- REKAP ALPHA / UNPAID LEAVE -->
                                    <td class="p-1">

                                        <input type="number"
                                               step="0.5"
                                               name="payrolls[{{ $emp->id_employee }}][unpaid_leave]"
                                               class="form-control form-control-sm text-center fw-bold text-danger border-danger border-opacity-25"
                                               value="{{ old('payrolls.'.$emp->id_employee.'.unpaid_leave', $existing->unpaid_leave ?? 0) }}"
                                               {{ $lockedState ? 'readonly' : '' }}>

                                    </td>


                                    <!-- REKAP LEMBUR -->
                                    <td class="p-1">

                                        <input type="number"
                                               step="0.5"
                                               name="payrolls[{{ $emp->id_employee }}][overtime_hours]"
                                               class="form-control form-control-sm text-center fw-bold border-secondary border-opacity-25"
                                               value="{{ old('payrolls.'.$emp->id_employee.'.overtime_hours', $existing->overtime_hours ?? 0) }}"
                                               {{ $lockedState ? 'readonly' : '' }}>

                                    </td>


                                    <!-- BAGIAN FINANSIAL -->
                                    @if($isFinancialRole)

                                        <!-- CUTI MELAHIRKAN -->
                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-light border-end-0 text-muted">
                                                    Rp
                                                </span>

                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]"
                                                       class="form-control border-start-0 fw-bold currency-input"
                                                       value="{{ number_format($existing->maternity_leave_pay ?? 0, 0, ',', '.') }}"
                                                       {{ $lockedState ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        <!-- BONUS / INSENTIF -->
                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-success bg-opacity-10 border-end-0 text-success fw-bold">
                                                    Rp
                                                </span>

                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][incentive]"
                                                       class="form-control border-start-0 fw-bold text-success currency-input"
                                                       value="{{ number_format($existing->incentive ?? 0, 0, ',', '.') }}"
                                                       {{ $lockedState ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        <!-- KASBON -->
                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">
                                                    Rp
                                                </span>

                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][cash_advance]"
                                                       class="form-control border-start-0 fw-bold text-danger currency-input"
                                                       value="{{ number_format($existing->cash_advance ?? 0, 0, ',', '.') }}"
                                                       {{ $lockedState ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        <!-- POTONGAN GANTUNGAN -->
                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">
                                                    Rp
                                                </span>

                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][potongan_gantungan]"
                                                       class="form-control border-start-0 fw-bold text-danger currency-input"
                                                       value="{{ number_format($existing->potongan_gantungan ?? 0, 0, ',', '.') }}"
                                                       {{ $lockedState ? 'readonly' : '' }}>

                                            </div>

                                        </td>


                                        <!-- POTONGAN LAIN -->
                                        <td class="p-1">

                                            <div class="input-group input-group-sm">

                                                <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">
                                                    Rp
                                                </span>

                                                <input type="text"
                                                       name="payrolls[{{ $emp->id_employee }}][other_deductions]"
                                                       class="form-control border-start-0 fw-bold text-danger currency-input"
                                                       value="{{ number_format($existing->other_deductions ?? 0, 0, ',', '.') }}"
                                                       {{ $lockedState ? 'readonly' : '' }}>

                                            </div>

                                        </td>

                                    @else

                                        <input type="hidden"
                                               name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]"
                                               value="{{ $existing->maternity_leave_pay ?? 0 }}">

                                        <input type="hidden"
                                               name="payrolls[{{ $emp->id_employee }}][incentive]"
                                               value="{{ $existing->incentive ?? 0 }}">

                                        <input type="hidden"
                                               name="payrolls[{{ $emp->id_employee }}][cash_advance]"
                                               value="{{ $existing->cash_advance ?? 0 }}">

                                        <input type="hidden"
                                               name="payrolls[{{ $emp->id_employee }}][potongan_gantungan]"
                                               value="{{ $existing->potongan_gantungan ?? 0 }}">

                                        <input type="hidden"
                                               name="payrolls[{{ $emp->id_employee }}][other_deductions]"
                                               value="{{ $existing->other_deductions ?? 0 }}">

                                    @endif

                                </tr>

                            @endforeach

                            </tbody>

                        @empty

                            <tr>

                                <td colspan="{{ 5 + $totalDaysCount + ($isFinancialRole ? 5 : 0) }}"
                                    class="text-center py-5 text-muted">

                                    <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 d-block mb-3"></i>

                                    <span>
                                        Belum ada data karyawan aktif untuk diproses gajian.
                                    </span>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            <!-- ====================================================
                 ACTION FOOTER
                 Tetap dipertahankan seperti sebelumnya.
                 ==================================================== -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-bottom-4 border-top">

                <div class="text-muted small d-flex align-items-center gap-3">

                    <div>
                        <span class="badge bg-secondary opacity-25 me-1">&nbsp;</span>
                        Tanggal 01 - {{ $cutoffDay }}:
                        <strong>
                            {{ $lockedState ? 'Locked' : 'Open' }}
                        </strong>
                    </div>

                    <div>
                        <span class="badge bg-warning me-1">&nbsp;</span>
                        Tanggal {{ $cutoffDay + 1 }} - Akhir Bulan:
                        <strong>
                            Editable (Draft Next Period)
                        </strong>
                    </div>

                </div>


                <div class="d-flex align-items-center gap-2">

                    @if($lockedState)

                        @if($isFinancialRole)

                            <button type="submit"
                                    form="formUnlockPayroll"
                                    class="btn btn-outline-warning fw-bold px-4 py-2 rounded-3 shadow-sm">

                                <i class="fa-solid fa-lock-open me-1"></i>
                                Unlock Period

                            </button>

                        @endif

                        <button type="submit"
                                class="btn btn-primary px-4 py-2 fw-bold rounded-3 shadow-sm">

                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Simpan Perubahan Sisa Tanggal

                        </button>

                    @else

                        <button type="submit"
                                class="btn btn-success px-4 py-2 fw-bold rounded-3 shadow-sm">

                            <i class="fa-solid fa-calculator me-1"></i>
                            Simpan Absensi

                        </button>

                        <button type="submit"
                                form="formLockPayroll"
                                class="btn btn-dark px-4 py-2 fw-bold rounded-3 shadow-sm"
                                onclick="return confirm('Apakah Anda yakin ingin mengunci (Close) absensi s/d tanggal {{ $cutoffDay }}?');">

                            <i class="fa-solid fa-lock me-1"></i>
                            Close / Lock (Tgl 01-{{ $cutoffDay }})

                        </button>

                    @endif

                </div>

            </div>

        </div>

    </form>


    <!-- ============================================================
         FORM LOCK
         ============================================================ -->
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


    <!-- ============================================================
         FORM UNLOCK
         ============================================================ -->
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
        'status-bg-SKD',
        'status-bg-C',
        'status-bg-CM',
        'status-bg-A',
        'status-bg-empty'
    );

    if (selectEl.value === '') {

        selectEl.classList.add('status-bg-empty');

    } else {

        let cleanVal = selectEl.value.replace('.', '');

        selectEl.classList.add('status-bg-' + cleanVal);

    }
}


function recalculateSummary(selectEl) {

    const row = selectEl.closest('tr');

    const selects = row.querySelectorAll('.attendance-select');

    let alphaCount = 0;

    selects.forEach(s => {

        if (s.value === 'A') {

            alphaCount += 1;

        } else if (s.value === 'H0.5') {

            alphaCount += 0.5;

        }

    });

    const unpaidInput =
        row.querySelector('input[name*="[unpaid_leave]"]');

    if (unpaidInput) {

        unpaidInput.value = alphaCount;

    }
}


document.addEventListener('DOMContentLoaded', function () {

    const currencyInputs =
        document.querySelectorAll('.currency-input');


    const formatCurrency = (val) => {

        let clean =
            val.toString().replace(/[^0-9]/g, '');

        return clean
            ? new Intl.NumberFormat('id-ID').format(clean)
            : '0';

    };


    currencyInputs.forEach(function (input) {

        if (input.value) {

            input.value =
                formatCurrency(input.value);

        }


        input.addEventListener('input', function () {

            this.value =
                formatCurrency(this.value);

        });


        input.addEventListener('focus', function () {

            if (this.value === '0') {

                this.value = '';

            }

        });


        input.addEventListener('blur', function () {

            if (this.value === '') {

                this.value = '0';

            }

        });

    });


    const mainForm =
        document.getElementById('mainPayrollForm');


    if (mainForm) {

        mainForm.addEventListener('submit', function () {

            currencyInputs.forEach(function (input) {

                input.value =
                    input.value.replace(/\./g, '');

            });

        });

    }


    /*
    |--------------------------------------------------------------------------
    | DEPARTMENT COLLAPSE
    |--------------------------------------------------------------------------
    | Bootstrap collapse sudah menangani buka/tutup department.
    | Tidak ada perubahan terhadap data/input payroll.
    |--------------------------------------------------------------------------
    */

});

</script>
@endpush