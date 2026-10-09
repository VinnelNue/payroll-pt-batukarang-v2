@extends('layouts.app')

@section('title', 'Rekap Process Payroll Outer Island')

@section('page_title', 'Rekap Hasil Penggajian Outer Island')

@section('content')

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white outer-payroll-recap-card">

    @php

        /*

        |--------------------------------------------------------------------------

        | STATUS PAYROLL

        |--------------------------------------------------------------------------

        */

        $payrollSample = $payrolls->first();

        $isLocked =

            (bool) ($payrollSample?->is_locked ?? false);

        $unlockRequested =

            (bool) ($payrollSample?->unlock_requested ?? false);

        $unlockReason =

            $payrollSample?->unlock_reason ?? '';

        /*

        |--------------------------------------------------------------------------

        | ROLE

        |--------------------------------------------------------------------------

        */

        $userRole = (string) (Auth::user()->role ?? '');

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

        /*
        |--------------------------------------------------------------------------
        | ROLE ACCESS
        |--------------------------------------------------------------------------
        |
        | Finance / Super Admin: seluruh data.
        | Head HRD: finansial hanya untuk Level 1-13.
        | HRD: hanya data identitas dan kehadiran.
        |
        */
        $showBpjsColumns = $isFinanceRole || $isHeadHrd;
        $showFinancialColumns = $isFinanceRole || $isHeadHrd;

        $tableColumnCount =
            3
            + ($showBpjsColumns ? 2 : 0)
            + ($showFinancialColumns ? 4 : 0)
            + ($isFinanceRole ? 1 : 0);

        $getEmployeeLevel = static function ($employee): ?int {
            if (!$employee) {
                return null;
            }

            $activeContract = $employee->activeContract;

            $level =
                $activeContract?->level
                ?? $activeContract?->currentHistory?->level
                ?? $employee->contract?->currentHistory?->level;

            return is_numeric($level) ? (int) $level : null;
        };

        $canViewPayrollFinancial = static function ($payroll) use (
            $isFinanceRole,
            $isHeadHrd,
            $getEmployeeLevel
        ): bool {
            if ($isFinanceRole) {
                return true;
            }

            if (!$isHeadHrd) {
                return false;
            }

            $level = $getEmployeeLevel($payroll?->employee);

            // Level 13 terakhir terlihat; Level 14 ke atas disamarkan.
            return $level !== null && $level <= 13;
        };

        // Total ringkasan hanya menghitung payroll yang boleh terlihat.
        $visibleFinancialPayrolls = $payrolls
            ->filter(
                fn ($payroll) => $canViewPayrollFinancial($payroll)
            )
            ->values();

        $totalGross = 0;
        $totalNet = 0;
        $totalPph = 0;
        $totalBpjs = 0;

        if ($showFinancialColumns) {
            $totalGross = $visibleFinancialPayrolls->sum(
                fn ($payroll) => (float) ($payroll->gross_salary ?? 0)
            );

            $totalNet = $visibleFinancialPayrolls->sum(
                fn ($payroll) => (float) ($payroll->net_salary ?? 0)
            );

            $totalPph = $visibleFinancialPayrolls->sum(
                fn ($payroll) => (float) ($payroll->pph21_deduction ?? 0)
            );

            $totalBpjs = $visibleFinancialPayrolls->sum(
                fn ($payroll) =>
                    (float) ($payroll->bpjs_tk_deduction ?? 0)
                    + (float) ($payroll->bpjs_ks_deduction ?? 0)
            );
        }

        $payrollsByDepartment =

            $payrolls->groupBy(function ($payroll) {

                $department =

                    $payroll->employee?->activeContract?->department

                    ??

                    $payroll->employee?->activeContract?->division

                    ??

                    $payroll->employee?->activeContract?->divisi

                    ??

                    null;

                $department =

                    trim((string) $department);

                return $department !== ''

                    ? $department

                    : 'Tanpa Department';

            });

        /*

        |--------------------------------------------------------------------------

        | TOTAL EMPLOYEE

        |--------------------------------------------------------------------------

        */

        $totalEmployeeCount =

            $payrolls->count();

    @endphp

    {{-- ============================================================

         HEADER

    ============================================================= --}}

    <div

        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 mb-4 border-bottom"

    >

        <div>

            <div class="d-flex align-items-center gap-2 flex-wrap">

                <h4 class="fw-bold text-dark m-0">

                    Rekap Penggajian Outer Island

                </h4>

                <span

                    class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1"

                >

                    <i class="fa-solid fa-calendar-day me-1"></i>

                    {{ $period }}

                </span>

                @if($isLocked)

                    <span

                        class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1"

                    >

                        <i class="fa-solid fa-lock me-1"></i>

                        Locked

                    </span>

                @else

                    <span

                        class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1"

                    >

                        <i class="fa-solid fa-lock-open me-1"></i>

                        Open (Editable)

                    </span>

                @endif

            </div>

            <p class="text-muted small m-0 mt-1">

                Hasil kalkulasi Gaji Kotor, BPJS, PPh 21 TER,

                dan Take Home Pay karyawan Outer Island periode ini.

            </p>

            @if($isHeadHrd)
                <div class="role-access-note mt-3">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>
                        Akses finansial Head HRD dibatasi ke karyawan
                        <strong>Level 1–13</strong>. Level 14 ke atas disamarkan
                        dan tidak masuk total ringkasan.
                    </span>
                </div>
            @elseif($isHrd)
                <div class="role-access-note role-access-note-muted mt-3">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Mode HRD: tampilan difokuskan pada data kehadiran.</span>
                </div>
            @endif


        </div>

        {{-- ========================================================

             ACTION

        ========================================================= --}}

        <div class="d-flex flex-wrap align-items-center gap-2">

            {{-- FILTER PERIODE --}}

            <form

                action="{{ route('payrolls.outer_island.index') }}"

                method="GET"

                class="d-flex gap-2"

            >

                <input

                    type="month"

                    name="period"

                    class="form-control form-control-sm fw-bold border-secondary-subtle"

                    value="{{ $period }}"

                    onchange="this.form.submit()"

                >

            </form>

            {{-- EXPORT BCA --}}

            @if(

                $payrolls->count() > 0

                &&

                $isFinanceRole

            )

                <a

                    href="{{ route('payrolls.outer_island.export-bca', [

                        'period' => $period

                    ]) }}"

                    class="btn btn-outline-success btn-sm px-3 py-2 rounded-3 fw-semibold"

                >

                    <i class="fa-solid fa-file-csv me-1"></i>

                    Export BCA CSV

                </a>

            @endif

            {{-- EDIT ABSENSI --}}

            @if(!$isLocked)

                <a

                    href="{{ route('payrolls.outer_island.create', [

                        'period' => $period

                    ]) }}"

                    class="btn btn-primary btn-sm px-3 py-2 rounded-3 fw-semibold"

                >

                    <i class="fa-solid fa-pen-to-square me-1"></i>

                    Edit Absensi & Variabel

                </a>

            @endif

            {{-- LOCK / UNLOCK --}}

            @if(!$isLocked)

                @if(

                    $payrolls->count() > 0

                    &&

                    $isFinanceRole

                )

                    <form

                        action="{{ route('payrolls.outer_island.lock') }}"

                        method="POST"

                        class="d-inline"

                    >

                        @csrf

                        <input

                            type="hidden"

                            name="period"

                            value="{{ $period }}"

                        >

                        <button

                            type="submit"

                            class="btn btn-danger btn-sm px-3 py-2 rounded-3 fw-semibold"

                            onclick="return confirm('Kunci kalkulasi payroll Outer Island periode {{ $period }}? Data tidak bisa diubah setelah dikunci.')"

                        >

                            <i class="fa-solid fa-lock me-1"></i>

                            Kunci Payroll

                        </button>

                    </form>

                @endif

            @else

                @if($isFinanceRole)

                    <form

                        action="{{ route('payrolls.outer_island.unlock') }}"

                        method="POST"

                        class="d-inline"

                    >

                        @csrf

                        <input

                            type="hidden"

                            name="period"

                            value="{{ $period }}"

                        >

                        <button

                            type="submit"

                            class="btn btn-warning btn-sm px-3 py-2 rounded-3 fw-semibold"

                            onclick="return confirm('Buka kuncian payroll Outer Island periode {{ $period }}?')"

                        >

                            <i class="fa-solid fa-lock-open me-1"></i>

                            Buka Kuncian

                        </button>

                    </form>

                @else

                    @if(!$unlockRequested)

                        <button

                            type="button"

                            class="btn btn-warning btn-sm px-3 py-2 rounded-3 fw-semibold"

                            data-bs-toggle="modal"

                            data-bs-target="#requestUnlockModal"

                        >

                            <i class="fa-solid fa-key me-1"></i>

                            Request Unlock

                        </button>

                    @endif

                @endif

            @endif

        </div>

    </div>

    {{-- ============================================================

         UNLOCK REQUEST

    ============================================================= --}}

    @if(

        $isLocked

        &&

        $unlockRequested

    )

        <div

            class="alert alert-warning border-warning rounded-4 p-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 shadow-sm"

        >

            <div>

                <h6 class="fw-bold text-dark m-0 mb-1">

                    <i class="fa-solid fa-clock-rotate-left text-warning me-1"></i>

                    Permohonan Buka Kunci Payroll Outer Island

                </h6>

                <p class="mb-0 text-secondary small">

                    <strong>Alasan Revisi:</strong>

                    "{{ $unlockReason }}"

                </p>

            </div>

            @if($isFinanceRole)

                <div class="d-flex gap-2">

                    <form

                        action="{{ route('payrolls.outer_island.unlock') }}"

                        method="POST"

                        class="d-inline"

                    >

                        @csrf

                        <input

                            type="hidden"

                            name="period"

                            value="{{ $period }}"

                        >

                        <button

                            type="submit"

                            class="btn btn-success btn-sm px-3 rounded-3 fw-semibold"

                            onclick="return confirm('Setujui dan buka kuncian payroll Outer Island?')"

                        >

                            <i class="fa-solid fa-check me-1"></i>

                            Setujui & Buka

                        </button>

                    </form>

                    <form

                        action="{{ route('payrolls.outer_island.rejectUnlock') }}"

                        method="POST"

                        class="d-inline"

                    >

                        @csrf

                        <input

                            type="hidden"

                            name="period"

                            value="{{ $period }}"

                        >

                        <button

                            type="submit"

                            class="btn btn-outline-secondary btn-sm px-3 rounded-3 fw-semibold"

                        >

                            <i class="fa-solid fa-xmark me-1"></i>

                            Tolak

                        </button>

                    </form>

                </div>

            @else

                <span

                    class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-semibold"

                >

                    <i class="fa-solid fa-hourglass-half me-1"></i>

                    Menunggu Persetujuan Manager Keuangan / Super Admin

                </span>

            @endif

        </div>

    @endif

    {{-- ============================================================

         SUMMARY

    ============================================================= --}}

    @if($showFinancialColumns)

        
            @if($isHeadHrd)
                <div class="col-12">
                    <div class="role-access-note">
                        <i class="fa-solid fa-shield-halved mt-1"></i>
                        <span>
                            Ringkasan nominal hanya menghitung karyawan
                            <strong>Level 1–13</strong>. Data finansial Level 14 ke atas
                            disamarkan dan tidak ikut dijumlahkan.
                        </span>
                    </div>
                </div>
            @endif

<div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="summary-card summary-net">

                    <small>

                        Total Take Home Pay (BCA)

                    </small>

                    <h4>

                        Rp {{ number_format($totalNet, 0, ',', '.') }}

                    </h4>

                    <i class="fa-solid fa-building-columns summary-icon"></i>

                </div>

            </div>

            <div class="col-md-3">

                <div class="summary-card summary-pph">

                    <small>

                        Setoran PPh 21 (CORTAX)

                    </small>

                    <h4>

                        Rp {{ number_format($totalPph, 0, ',', '.') }}

                    </h4>

                    <i class="fa-solid fa-calculator summary-icon"></i>

                </div>

            </div>

            <div class="col-md-3">

                <div class="summary-card summary-bpjs">

                    <small>

                        Total Iuran BPJS

                    </small>

                    <h4>

                        Rp {{ number_format($totalBpjs, 0, ',', '.') }}

                    </h4>

                    <i class="fa-solid fa-shield-halved summary-icon"></i>

                </div>

            </div>

            <div class="col-md-3">

                <div class="summary-card summary-gross">

                    <small>

                        Total Pengeluaran Bruto

                    </small>

                    <h4>

                        Rp {{ number_format($totalGross, 0, ',', '.') }}

                    </h4>

                    <i class="fa-solid fa-money-bill-wave summary-icon"></i>

                </div>

            </div>

        </div>

    @endif

    {{-- ============================================================

         EMPLOYEE COUNT

    ============================================================= --}}

    @if($totalEmployeeCount > 0)

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div class="text-muted small">

                <i class="fa-solid fa-users me-1"></i>

                Total payroll:

                <strong class="text-dark">

                    {{ $totalEmployeeCount }}

                </strong>

                karyawan

            </div>

            <div class="text-muted small">

                {{ $payrollsByDepartment->count() }}

                department

            </div>

        </div>

    @endif

    {{-- ============================================================

         TABLE

    ============================================================= --}}

    <div class="table-responsive rounded-4 border border-light-subtle shadow-sm outer-payroll-table-wrap">

        <table class="table table-hover align-middle mb-0 text-nowrap outer-payroll-table">

            <thead class="bg-light border-bottom">

                <tr class="text-secondary small fw-bold text-uppercase">

                    <th

                        class="py-3 px-3 text-center"

                        style="width:40px;"

                    >

                        No

                    </th>

                    <th class="py-3 px-3">

                        Karyawan

                    </th>

                    <th class="py-3 px-3 text-center">

                        Kehadiran

                    </th>

                    @if($showBpjsColumns)
<th class="py-3 px-3 text-end">

                        BPJS TK

                    </th>

                    <th class="py-3 px-3 text-end">

                        BPJS KS

                    </th>
@endif


                    @if($showFinancialColumns)

                        <th class="py-3 px-3 text-end">

                            Gaji Bruto

                        </th>

                        <th class="py-3 px-3 text-end">

                            PPh 21 TER

                        </th>

                        <th class="py-3 px-3 text-end">

                            Potongan / Kasbon

                        </th>

                        <th

                            class="py-3 px-3 text-end bg-success bg-opacity-10 text-success fw-bolder"

                        >

                            Take Home Pay

                        </th>

                        @if($isFinanceRole)

                            <th

                                class="py-3 px-3 text-center"

                                style="width:80px;"

                            >

                                Slip

                            </th>

                        @endif

                    @endif

                </tr>

            </thead>

            @forelse(

                $payrollsByDepartment

                as $department => $departmentPayrolls

            )

                @php

                    $departmentId =

                        'department_' .

                        md5((string) $department);

                    $departmentCount =

                        $departmentPayrolls->count();

                    $columnCount = $tableColumnCount;

                @endphp

                <tbody

                    id="{{ $departmentId }}"

                    class="department-section"

                    data-collapsed="0"

                >

                    {{-- DEPARTMENT HEADER --}}

                    <tr class="department-row">

                        <td

                            colspan="{{ $columnCount }}"

                            class="p-0"

                        >

                            <div

                                class="department-toggle d-flex justify-content-between align-items-center px-3 py-3"

                                data-target="{{ $departmentId }}"

                                aria-expanded="true"

                                role="button"

                                tabindex="0"

                            >

                                <div class="d-flex align-items-center gap-2">

                                    <i class="fa-solid fa-chevron-down department-chevron text-primary"></i>

                                    <i class="fa-solid fa-building text-primary"></i>

                                    <span class="fw-bold text-dark">

                                        {{ $department }}

                                    </span>

                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">

                                        {{ $departmentCount }}

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

                    {{-- =================================================

                         EMPLOYEE

                    ================================================== --}}

                    @foreach($departmentPayrolls as $pay)

                        @php

                            /*

                            |--------------------------------------------------------------------------

                            | EMPLOYEE

                            |--------------------------------------------------------------------------

                            */

                            $employee =

                                $pay->employee;

                            /*

                            |--------------------------------------------------------------------------

                            | CONTRACT

                            |--------------------------------------------------------------------------

                            */

                            $contract = $employee?->activeContract;

                            $level =
                                $contract?->level
                                ?? $contract?->currentHistory?->level
                                ?? $employee?->contract?->currentHistory?->level;

                            $level = is_numeric($level) ? (int) $level : null;

                            $canViewFinancial =
                                $isFinanceRole
                                || (
                                    $isHeadHrd
                                    && $level !== null
                                    && $level <= 13
                                );

                            $canViewBpjsNominal = $canViewFinancial;

                            /*

                            |--------------------------------------------------------------------------

                            | BPJS OVERRIDE STATUS

                            |--------------------------------------------------------------------------

                            */

                            $isBpjsOverride =

                                (bool)

                                ($pay->is_bpjs_override ?? false);

                            /*

                            |--------------------------------------------------------------------------

                            | RAW PAYROLL BPJS

                            |--------------------------------------------------------------------------

                            */

                            $payrollBpjsTk =

                                (float)

                                ($pay->bpjs_tk_deduction ?? 0);

                            $payrollBpjsKs =

                                (float)

                                ($pay->bpjs_ks_deduction ?? 0);

                            /*

                            |--------------------------------------------------------------------------

                            | MANUAL BPJS DARI CONTRACT

                            |--------------------------------------------------------------------------

                            |

                            | Digunakan sebagai fallback jika payroll sudah

                            | ditandai Override tetapi nominal deduction payroll

                            | masih 0.

                            |

                            */

                            $manualBpjsTk =

                                (float)

                                ($contract?->manual_bpjs_tk_employee ?? 0);

                            $manualBpjsKs =

                                (float)

                                ($contract?->manual_bpjs_ks_employee ?? 0);

                            /*

                            |--------------------------------------------------------------------------

                            | BPJS TK FINAL DISPLAY

                            |--------------------------------------------------------------------------

                            |

                            | AUTO:

                            |   ambil hasil payroll.

                            |

                            | OVERRIDE:

                            |   jika payroll sudah memiliki nominal > 0,

                            |   gunakan nominal payroll.

                            |

                            |   jika payroll masih 0,

                            |   fallback ke manual contract.

                            |

                            */

                            if ($isBpjsOverride) {

                                $bpjsTkDisplay =

                                    $payrollBpjsTk > 0

                                        ? $payrollBpjsTk

                                        : $manualBpjsTk;

                            } else {

                                $bpjsTkDisplay =

                                    $payrollBpjsTk;

                            }

                            /*

                            |--------------------------------------------------------------------------

                            | BPJS KS FINAL DISPLAY

                            |--------------------------------------------------------------------------

                            */

                            if ($isBpjsOverride) {

                                $bpjsKsDisplay =

                                    $payrollBpjsKs > 0

                                        ? $payrollBpjsKs

                                        : $manualBpjsKs;

                            } else {

                                $bpjsKsDisplay =

                                    $payrollBpjsKs;

                            }

                            /*

                            |--------------------------------------------------------------------------

                            | DIVISION

                            |--------------------------------------------------------------------------

                            */

                            $division =

                                $contract?->division

                                ??

                                $contract?->divisi

                                ??

                                null;

                            /*

                            |--------------------------------------------------------------------------

                            | OTHER DEDUCTIONS

                            |--------------------------------------------------------------------------

                            */

                            $cashAdvance =

                                (float)

                                ($pay->cash_advance ?? 0);

                            $otherDeductions =

                                (float)

                                ($pay->other_deductions ?? 0);

                            $previousGantungan =

                                (float)

                                ($pay->previous_gantungan_deduction ?? 0);

                            $totalAdditionalDeduction =

                                $cashAdvance

                                +

                                $otherDeductions

                                +

                                $previousGantungan;

                        @endphp

                        <tr class="employee-row">

                            {{-- NO --}}

                            <td class="text-center fw-bold text-muted px-3">

                                {{ $loop->parent->iteration }}.{{ $loop->iteration }}

                            </td>

                            {{-- =================================================

                                 KARYAWAN

                            ================================================== --}}

                            <td class="px-3">

                                <div class="fw-bold text-dark">

                                    {{ $employee?->full_name_outer ?? '-' }}

                                </div>

                                @if($division)

                                    <div class="small text-primary fw-semibold mt-1">

                                        <i class="fa-solid fa-sitemap me-1"></i>

                                        {{ $division }}

                                    </div>

                                @endif

                                @if($isFinanceRole || $isHeadHrd)

                                    <span

                                        class="badge bg-light text-secondary border small fw-normal mt-1"

                                    >

                                        {{ $contract?->job_title ?? '-' }}

                                    </span>

                                @else

                                    <span

                                        class="badge bg-danger-subtle text-danger border border-danger-subtle small fw-normal mt-1"

                                    >

                                        <i

                                            class="fa-solid fa-lock"

                                            style="font-size:10px;"

                                        ></i>

                                        Posisi Dirahasiakan

                                    </span>

                                @endif

                            </td>

                            {{-- =================================================

                                 KEHADIRAN

                            ================================================== --}}

                            <td class="text-center px-3">

                                <div class="fw-semibold text-dark">

                                    {{ number_format(

                                        (float) ($pay->work_days ?? 0),

                                        1,

                                        ',',

                                        '.'

                                    ) }}

                                    Hari

                                </div>

                                @if(

                                    (float) ($pay->unpaid_leave ?? 0) > 0

                                )

                                    <small class="text-danger fw-semibold">

                                        (

                                        {{ number_format(

                                            (float) $pay->unpaid_leave,

                                            1,

                                            ',',

                                            '.'

                                        ) }}

                                        Hari Absen)

                                    </small>

                                @endif

                                @if(

                                    (float) ($pay->gantungan_days ?? 0) > 0

                                )

                                    <small

                                        class="text-warning-emphasis fw-semibold d-block mt-1"

                                    >

                                        <i class="fa-solid fa-clock me-1"></i>

                                        Gantungan:

                                        {{ number_format(

                                            (float) $pay->gantungan_days,

                                            1,

                                            ',',

                                            '.'

                                        ) }}

                                    </small>

                                @endif

                            </td>

                            @if($showBpjsColumns)
{{-- =================================================

                                 BPJS TK

                            ================================================== --}}

                            <td class="text-end px-3">

                                @if($canViewBpjsNominal)

                                    <div

                                        class="text-muted small fw-semibold mb-1"

                                    >

                                        Rp

                                        {{ number_format(

                                            $bpjsTkDisplay,

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                    </div>

                                @else

                                    <div

                                        class="text-danger small fw-bold mb-1 opacity-75"

                                    >

                                        <i class="fa-solid fa-lock"></i>

                                        ***

                                    </div>

                                @endif

                                @if($canViewBpjsNominal)
@if($isBpjsOverride)

                                    <span

                                        class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2"

                                        style="font-size:.65rem;"

                                        title="BPJS TK menggunakan nilai Override / Manual"

                                    >

                                        <i class="fa-solid fa-pen-to-square me-1"></i>

                                        Override

                                    </span>

                                @else

                                    <span

                                        class="badge bg-success-subtle text-success border border-success-subtle px-2"

                                        style="font-size:.65rem;"

                                        title="BPJS TK dihitung otomatis"

                                    >

                                        <i class="fa-solid fa-robot me-1"></i>

                                        Auto

                                    </span>

                                @endif
@endif

                            </td>

                            {{-- =================================================

                                 BPJS KS

                            ================================================== --}}

                            <td class="text-end px-3">

                                @if($canViewBpjsNominal)

                                    <div

                                        class="text-muted small fw-semibold mb-1"

                                    >

                                        Rp

                                        {{ number_format(

                                            $bpjsKsDisplay,

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                    </div>

                                @else

                                    <div

                                        class="text-danger small fw-bold mb-1 opacity-75"

                                    >

                                        <i class="fa-solid fa-lock"></i>

                                        ***

                                    </div>

                                @endif

                                @if($canViewBpjsNominal)
@if($isBpjsOverride)

                                    <span

                                        class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2"

                                        style="font-size:.65rem;"

                                        title="BPJS KS menggunakan nilai Override / Manual"

                                    >

                                        <i class="fa-solid fa-pen-to-square me-1"></i>

                                        Override

                                    </span>

                                @else

                                    <span

                                        class="badge bg-success-subtle text-success border border-success-subtle px-2"

                                        style="font-size:.65rem;"

                                        title="BPJS KS dihitung otomatis"

                                    >

                                        <i class="fa-solid fa-robot me-1"></i>

                                        Auto

                                    </span>

                                @endif
@endif

                            </td>

                            
@endif
{{-- =================================================

                                 FINANCIAL

                            ================================================== --}}

                            @if($showFinancialColumns)

                                {{-- GROSS --}}

                                <td

                                    class="text-end fw-semibold text-dark px-3"

                                >

                                    @if($canViewFinancial)

                                        Rp

                                        {{ number_format(

                                            (float) ($pay->gross_salary ?? 0),

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                    @else

                                        <span

                                            class="text-danger fw-bold opacity-75"

                                        >

                                            <i class="fa-solid fa-lock me-1"></i>

                                            ***

                                        </span>

                                    @endif

                                </td>

                                {{-- PPH --}}

                                <td

                                    class="text-end text-danger fw-semibold px-3"

                                >

                                    @if($canViewFinancial)

                                        Rp

                                        {{ number_format(

                                            (float) ($pay->pph21_deduction ?? 0),

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                    @else

                                        <span

                                            class="text-danger fw-bold opacity-75"

                                        >

                                            <i class="fa-solid fa-lock me-1"></i>

                                            ***

                                        </span>

                                    @endif

                                </td>

                                {{-- POTONGAN --}}

                                <td

                                    class="text-end text-danger small px-3"

                                >

                                    @if($canViewFinancial)

                                        Rp

                                        {{ number_format(

                                            $totalAdditionalDeduction,

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                        @if($previousGantungan > 0)

                                            <div

                                                class="small text-muted mt-1"

                                            >

                                                Gantungan:

                                                Rp

                                                {{ number_format(

                                                    $previousGantungan,

                                                    0,

                                                    ',',

                                                    '.'

                                                ) }}

                                            </div>

                                        @endif

                                    @else

                                        <span

                                            class="text-danger fw-bold opacity-75"

                                        >

                                            <i class="fa-solid fa-lock me-1"></i>

                                            ***

                                        </span>

                                    @endif

                                </td>

                                {{-- NET --}}

                                <td

                                    class="text-end fw-bolder text-success bg-success bg-opacity-10 fs-6 px-3"

                                >

                                    @if($canViewFinancial)

                                        Rp

                                        {{ number_format(

                                            (float) ($pay->net_salary ?? 0),

                                            0,

                                            ',',

                                            '.'

                                        ) }}

                                    @else

                                        <span

                                            class="text-danger fw-bold opacity-75"

                                        >

                                            <i class="fa-solid fa-lock me-1"></i>

                                            ***

                                        </span>

                                    @endif

                                </td>

                                {{-- SLIP --}}

                                @if($isFinanceRole)

                                    <td class="text-center px-3">

                                        <div

                                            class="d-flex justify-content-center gap-1"

                                        >

                                            @if($employee)

                                                <a

                                                    href="{{ route('payrolls.outer_island.print-pdf', [

                                                        'uuid' => $employee->uuid,

                                                        'period' => $period

                                                    ]) }}"

                                                    target="_blank"

                                                    class="btn btn-light btn-sm border rounded-3 text-danger"

                                                    title="Cetak Slip PDF"

                                                >

                                                    <i class="fa-solid fa-file-pdf fs-6"></i>

                                                </a>

                                                <a

                                                    href="{{ route('payrolls.outer_island.send-email', [

                                                        'uuid' => $employee->uuid,

                                                        'period' => $period

                                                    ]) }}"

                                                    class="btn btn-light btn-sm border rounded-3 text-primary"

                                                    title="Kirim Email Slip Gaji"

                                                    onclick="return confirm('Kirim slip gaji ke email karyawan?')"

                                                >

                                                    <i class="fa-solid fa-paper-plane fs-6"></i>

                                                </a>

                                            @else

                                                <span class="text-muted small">

                                                    -

                                                </span>

                                            @endif

                                        </div>

                                    </td>

                                @endif

                            @endif

                        </tr>

                    @endforeach

                </tbody>

            @empty

                <tbody>

                    <tr>

                        <td

                            colspan="{{ $tableColumnCount }}"

                            class="text-center py-5 text-muted"

                        >

                            <i

                                class="fa-solid fa-file-circle-xmark fs-2 d-block mb-2 text-secondary opacity-50"

                            ></i>

                            Belum ada data payroll Outer Island

                            diproses untuk periode

                            <strong>

                                {{ $period }}

                            </strong>.

                            <div class="mt-3">

                                @if(!$isLocked)

                                    <a

                                        href="{{ route('payrolls.outer_island.create', [

                                            'period' => $period

                                        ]) }}"

                                        class="btn btn-sm btn-primary px-3 rounded-3"

                                    >

                                        <i class="fa-solid fa-plus me-1"></i>

                                        Input Absensi

                                    </a>

                                @endif

                            </div>

                        </td>

                    </tr>

                </tbody>

            @endforelse

        </table>

    </div>

</div>

{{-- ================================================================

     MODAL REQUEST UNLOCK

================================================================ --}}

@if(

    $isLocked

    &&

    !$isFinanceRole

)

    <div

        class="modal fade"

        id="requestUnlockModal"

        tabindex="-1"

        aria-hidden="true"

    >

        <div class="modal-dialog modal-dialog-centered">

            <form

                action="{{ route('payrolls.outer_island.requestUnlock') }}"

                method="POST"

            >

                @csrf

                <input

                    type="hidden"

                    name="period"

                    value="{{ $period }}"

                >

                <div class="modal-content rounded-4 border-0 shadow">

                    <div class="modal-header border-bottom-0 pb-0">

                        <h5 class="modal-title fw-bold text-dark">

                            <i class="fa-solid fa-key text-warning me-2"></i>

                            Pengajuan Buka Kunci Payroll Outer Island

                        </h5>

                        <button

                            type="button"

                            class="btn-close"

                            data-bs-dismiss="modal"

                            aria-label="Close"

                        ></button>

                    </div>

                    <div class="modal-body text-start pt-3">

                        <p class="text-muted small">

                            Kalkulasi payroll Outer Island periode

                            <strong>

                                {{ $period }}

                            </strong>

                            saat ini terkunci.

                            Silakan tuliskan alasan revisi untuk

                            mengajukan pembukaan kunci ke Manager Keuangan /

                            Super Admin.

                        </p>

                        <div class="mb-3">

                            <label

                                class="form-label fw-semibold text-dark small"

                            >

                                Alasan Revisi / Buka Kunci

                                <span class="text-danger">

                                    *

                                </span>

                            </label>

                            <textarea

                                name="reason"

                                class="form-control rounded-3"

                                rows="3"

                                required

                                placeholder="Contoh: Ada perbaikan data lembur untuk Karyawan Outer Island..."

                            ></textarea>

                        </div>

                    </div>

                    <div class="modal-footer border-top-0 pt-0">

                        <button

                            type="button"

                            class="btn btn-light rounded-3 px-3 fw-semibold"

                            data-bs-dismiss="modal"

                        >

                            Batal

                        </button>

                        <button

                            type="submit"

                            class="btn btn-warning rounded-3 px-3 fw-semibold"

                        >

                            <i class="fa-solid fa-paper-plane me-1"></i>

                            Kirim Pengajuan

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

@endif

{{-- ================================================================

     STYLE

================================================================ --}}

@push('styles')

<style>

    .summary-card {

        position: relative;

        overflow: hidden;

        height: 100%;

        padding: 1rem;

        border-radius: 1rem;

    }

    .summary-card small {

        display: block;

        font-weight: 700;

        text-transform: uppercase;

        letter-spacing: .04em;

        margin-bottom: .25rem;

    }

    .summary-card h4 {

        margin: 0;

        font-weight: 800;

    }

    .summary-icon {

        position: absolute;

        right: .75rem;

        bottom: .5rem;

        font-size: 2.5rem;

        opacity: .10;

    }

    .summary-net {

        background: rgba(25, 135, 84, .10);

        border: 1px solid rgba(25, 135, 84, .25);

    }

    .summary-net small,

    .summary-net h4,

    .summary-net .summary-icon {

        color: #198754;

    }

    .summary-pph {

        background: rgba(220, 53, 69, .10);

        border: 1px solid rgba(220, 53, 69, .25);

    }

    .summary-pph small,

    .summary-pph h4,

    .summary-pph .summary-icon {

        color: #dc3545;

    }

    .summary-bpjs {

        background: rgba(255, 193, 7, .10);

        border: 1px solid rgba(255, 193, 7, .25);

    }

    .summary-bpjs small,

    .summary-bpjs h4,

    .summary-bpjs .summary-icon {

        color: #997404;

    }

    .summary-gross {

        background: rgba(13, 110, 253, .10);

        border: 1px solid rgba(13, 110, 253, .25);

    }

    .summary-gross small,

    .summary-gross h4,

    .summary-gross .summary-icon {

        color: #0d6efd;

    }

    .department-row td {

        background:

            linear-gradient(

                90deg,

                rgba(13, 110, 253, 0.10),

                rgba(13, 110, 253, 0.035)

            ) !important;

        border-top:

            2px solid rgba(13, 110, 253, 0.20) !important;

        border-bottom:

            1px solid rgba(13, 110, 253, 0.10) !important;

        padding:

            0.75rem 1rem !important;

    }

    .department-toggle {

        cursor: pointer;

        user-select: none;

        transition:

            background-color .15s ease;

    }

    .department-toggle:hover {

        background-color:

            rgba(13, 110, 253, 0.06);

    }

    .department-toggle:focus {

        outline:

            2px solid rgba(13, 110, 253, .25);

        outline-offset:

            -2px;

    }

    .department-chevron {

        transition:

            transform .2s ease;

    }

    .department-toggle[aria-expanded="false"]

    .department-chevron {

        transform:

            rotate(-90deg);

    }

    .employee-row {

        transition:

            background-color .12s ease;

    }

    .employee-row:hover {

        background-color:

            rgba(13, 110, 253, .025);

    }

    .bpjs-badge {

        font-size:

            .65rem;

    }


    /* =============================================================
       OUTER ISLAND PAYROLL — ADVANCED RECAP UI
    ============================================================= */
    .outer-payroll-recap-card {
        border-top: 4px solid #2563eb !important;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, .045), transparent 28%),
            #fff !important;
    }

    .outer-payroll-recap-card .btn {
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .outer-payroll-recap-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 .35rem .8rem rgba(15, 23, 42, .08);
    }

    .outer-payroll-recap-card .form-control {
        min-height: 38px;
        border-radius: .7rem;
    }

    .role-access-note {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        padding: .75rem .9rem;
        border: 1px solid #bfdbfe;
        border-radius: .85rem;
        color: #1e40af;
        background: #eff6ff;
        font-size: .82rem;
        line-height: 1.45;
    }

    .role-access-note-muted {
        color: #475569;
        border-color: #e2e8f0;
        background: #f8fafc;
    }

    .outer-payroll-table-wrap {
        max-height: 70vh;
        overflow: auto;
        background: #fff;
    }

    .outer-payroll-table {
        min-width: 820px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .outer-payroll-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background-color: #f8fafc;
        white-space: nowrap;
        box-shadow: inset 0 -1px 0 rgba(15, 23, 42, .08);
    }

    .outer-payroll-table tbody td {
        vertical-align: middle;
    }

    .outer-payroll-table .employee-row:hover td {
        background-color: #f8fbff;
    }

    .outer-payroll-table .financial-masked {
        background: #f8fafc;
        color: #64748b;
    }

    .outer-payroll-table .department-row td {
        position: sticky;
        left: 0;
        z-index: 2;
    }

    .summary-card {
        box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .045);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .55rem 1.5rem rgba(15, 23, 42, .085);
    }

    @media (max-width: 768px) {
        .outer-payroll-recap-card {
            padding: 1rem !important;
        }

        .outer-payroll-table-wrap {
            max-height: 65vh;
        }

        .role-access-note {
            width: 100%;
        }
    }

</style>

@endpush

{{-- ================================================================

     JAVASCRIPT DEPARTMENT COLLAPSE

================================================================ --}}

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    function toggleDepartment(button) {

        const targetId =

            button.dataset.target;

        const target =

            document.getElementById(targetId);

        if (!target) {

            return;

        }

        const rows =

            target.querySelectorAll('.employee-row');

        const isCollapsed =

            target.dataset.collapsed === '1';

        if (isCollapsed) {

            rows.forEach(function (row) {

                row.style.display = '';

            });

            target.dataset.collapsed =

                '0';

            button.setAttribute(

                'aria-expanded',

                'true'

            );

        } else {

            rows.forEach(function (row) {

                row.style.display =

                    'none';

            });

            target.dataset.collapsed =

                '1';

            button.setAttribute(

                'aria-expanded',

                'false'

            );

        }

    }

    document

        .querySelectorAll('.department-toggle')

        .forEach(function (button) {

            button.addEventListener(

                'click',

                function () {

                    toggleDepartment(this);

                }

            );

            button.addEventListener(

                'keydown',

                function (event) {

                    if (

                        event.key === 'Enter'

                        ||

                        event.key === ' '

                    ) {

                        event.preventDefault();

                        toggleDepartment(this);

                    }

                }

            );

        });

});

</script>

@endpush

@endsection
