@extends('layouts.app')

@section('title', 'Manajemen Penempatan & Kontrak (Local)')
@section('page_title', 'Manajemen Penempatan & Kontrak Kerja - Local')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | ROLE ACCESS
    |--------------------------------------------------------------------------
    */

    $userRole = Auth::user()->role ?? '';

    /*
    | Full financial access
    */
    $isManagerKeuangan = in_array(
        $userRole,
        ['super_admin', 'manager_keuangan'],
        true
    );

    /*
    | Financial role, limited by level <= 13
    */
    $isKeuangan = $userRole === 'keuangan';

    /*
    | Head HRD, limited by level <= 13
    */
    $isHeadHrd = in_array(
        $userRole,
        ['head_hrd', 'kepala_hrd'],
        true
    );

    /*
    | HRD has no financial access
    */
    $isHrd = $userRole === 'hrd';

    /*
    |--------------------------------------------------------------------------
    | FINANCIAL COLUMN VISIBILITY
    |--------------------------------------------------------------------------
    |
    | Manager/Super Admin:
    |   tampil penuh
    |
    | Keuangan / Head HRD:
    |   kolom tetap tampil, tetapi level >= 14 akan di-mask
    |
    | HRD:
    |   kolom finansial tidak ditampilkan
    |
    */

    $showFinancialColumns =
        $isManagerKeuangan ||
        $isKeuangan ||
        $isHeadHrd;
@endphp


<style>

    /*
    |--------------------------------------------------------------------------
    | CARD
    |--------------------------------------------------------------------------
    */

    .outer-contract-card {
        border: 1px solid #e9ecef;
        border-radius: 14px;
        background: #fff;
    }

    .outer-contract-header {
        padding-bottom: 18px;
        border-bottom: 1px solid #eef0f2;
    }

    .outer-contract-title {
        font-size: 18px;
        font-weight: 700;
        color: #212529;
    }

    .outer-contract-subtitle {
        font-size: 13px;
        color: #6c757d;
        margin-top: 4px;
    }


    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    .outer-contract-table {
        margin-bottom: 0;
    }

    .outer-contract-table thead th {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        color: #495057;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .2px;
        white-space: nowrap;
        vertical-align: middle;
    }

    .outer-contract-table tbody td {
        font-size: 13px;
        vertical-align: middle;
    }

    .outer-contract-table tbody tr {
        transition: background-color .15s ease;
    }

    .outer-contract-table tbody tr:hover {
        background-color: #fafbfc;
    }


    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE
    |--------------------------------------------------------------------------
    */

    .employee-name {
        font-weight: 700;
        color: #212529;
        line-height: 1.35;
    }

    .employee-nik {
        font-size: 11px;
        color: #6c757d;
        margin-top: 2px;
    }


    /*
    |--------------------------------------------------------------------------
    | JOB / DEPARTMENT
    |--------------------------------------------------------------------------
    */

    .job-title {
        font-weight: 600;
        color: #212529;
        line-height: 1.35;
    }

    .job-meta {
        font-size: 11px;
        color: #6c757d;
        line-height: 1.45;
    }

    .job-level {
        font-size: 11px;
        font-weight: 600;
        color: #0d6efd;
        line-height: 1.45;
    }


    /*
    |--------------------------------------------------------------------------
    | FINANCIAL
    |--------------------------------------------------------------------------
    */

    .salary-value {
        font-size: 13px;
        font-weight: 700;
        color: #212529;
        white-space: nowrap;
    }

    .salary-masked {
        color: #adb5bd;
        letter-spacing: 2px;
        font-weight: 700;
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS BADGES
    |--------------------------------------------------------------------------
    */

    .status-badge {
        font-size: 10px;
        font-weight: 600;
        padding: 5px 8px;
        border-radius: 50px;
        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | PTKP
    |--------------------------------------------------------------------------
    */

    .ptkp-value {
        font-size: 12px;
        font-weight: 700;
        color: #212529;
    }

    .ter-badge {
        display: inline-block;
        margin-top: 3px;
        padding: 3px 7px;
        font-size: 9px;
        font-weight: 600;
        border-radius: 50px;
    }


    /*
    |--------------------------------------------------------------------------
    | BPJS
    |--------------------------------------------------------------------------
    */

    .bpjs-status {
        width: 115px;
        margin: 0 auto;
    }

    .bpjs-status .badge {
        display: block;
        width: 82px;
        margin: 2px auto;
        padding: 5px 7px;
        font-size: 10px;
        font-weight: 600;
        border-radius: 50px;
        white-space: nowrap;
    }

    .bpjs-auto {
        background: #6c757d;
        color: #fff;
    }

    .bpjs-manual {
        background: #ffc107;
        color: #212529;
        border: 1px solid #ffc107;
    }


    /*
    |--------------------------------------------------------------------------
    | ACTION
    |--------------------------------------------------------------------------
    */

    .btn-edit-contract {
        min-width: 115px;
        font-size: 12px;
        font-weight: 600;
    }


    /*
    |--------------------------------------------------------------------------
    | EMPTY STATE
    |--------------------------------------------------------------------------
    */

    .empty-icon {
        font-size: 32px;
        color: #adb5bd;
        margin-bottom: 10px;
    }

    .empty-title {
        font-weight: 600;
        color: #495057;
    }

    .empty-description {
        font-size: 12px;
        color: #6c757d;
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1200px) {

        .outer-contract-table thead th,
        .outer-contract-table tbody td {
            font-size: 12px;
        }

        .btn-edit-contract {
            min-width: 95px;
            font-size: 11px;
        }
    }

</style>


<div class="outer-contract-card p-4 mb-4">

    {{-- ================================================================
         HEADER
    ================================================================= --}}

    <div class="outer-contract-header mb-3">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="outer-contract-title">

                    <i class="fa-solid fa-file-signature text-primary me-2"></i>

                    Daftar Penempatan & Gaji Acuan

                    <span class="text-muted">
                        (Local)
                    </span>

                </div>

                <div class="outer-contract-subtitle">

                    Kelola status hubungan kerja, jabatan, divisi,
                    area penempatan, gaji, tunjangan, PTKP dan BPJS.

                </div>

            </div>

        </div>

    </div>


    {{-- ================================================================
         TABLE
    ================================================================= --}}

    <div class="table-responsive">

        <table class="table table-hover align-middle outer-contract-table">

            <thead>

                <tr>

                    {{-- NO --}}
                    <th
                        class="text-center"
                        style="width: 55px;"
                    >
                        No
                    </th>


                    {{-- KARYAWAN --}}
                    <th style="min-width: 190px;">
                        Karyawan
                    </th>


                    {{-- JABATAN --}}
                    <th style="min-width: 230px;">
                        Jabatan, Divisi & Level
                    </th>


                    {{-- STATUS KERJA --}}
                    <th
                        class="text-center"
                        style="min-width: 120px;"
                    >
                        Status Kerja
                    </th>


                    {{-- FINANCIAL --}}
                    @if($showFinancialColumns)

                        <th
                            class="text-end"
                            style="min-width: 150px;"
                        >
                            Gaji Pokok
                        </th>

                        <th
                            class="text-end"
                            style="min-width: 140px;"
                        >
                            Tunjangan
                        </th>

                    @endif


                    {{-- PTKP --}}
                    <th
                        class="text-center"
                        style="min-width: 95px;"
                    >
                        PTKP
                    </th>


                    {{-- BPJS --}}
                    <th
                        class="text-center"
                        style="min-width: 125px;"
                    >
                        Status BPJS
                    </th>


                    {{-- ACTION --}}
                    <th
                        class="text-center"
                        style="min-width: 125px;"
                    >
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($employees as $index => $emp)

                    @php

                        /*
                        |--------------------------------------------------------------------------
                        | CURRENT CONTRACT HISTORY
                        |--------------------------------------------------------------------------
                        |
                        | SUMBER DATA UTAMA
                        |
                        | Employee
                        |     ↓
                        | contract
                        |     ↓
                        | currentHistory
                        |
                        | Semua data kontrak di index membaca dari object ini.
                        |
                        */

                        $contract = $emp->contract?->currentHistory;


                        /*
                        |--------------------------------------------------------------------------
                        | LEVEL
                        |--------------------------------------------------------------------------
                        */

                        $level = (
                            $contract?->level !== null &&
                            $contract?->level !== ''
                        )
                            ? (int) $contract->level
                            : null;


                        /*
                        |--------------------------------------------------------------------------
                        | LEVEL HIGH
                        |--------------------------------------------------------------------------
                        */

                        $levelHigh =
                            $level !== null &&
                            $level >= 14;


                        /*
                        |--------------------------------------------------------------------------
                        | FINANCIAL ACCESS
                        |--------------------------------------------------------------------------
                        */

                        if ($isManagerKeuangan) {

                            /*
                            | Super Admin / Manager Keuangan
                            | selalu dapat melihat data finansial.
                            */

                            $maskFinancial = false;

                        } elseif ($isKeuangan || $isHeadHrd) {

                            /*
                            | Keuangan / Head HRD
                            | hanya dapat melihat level <= 13.
                            */

                            $maskFinancial =
                                $level === null ||
                                $level >= 14;

                        } else {

                            /*
                            | HRD
                            | tidak dapat melihat data finansial.
                            */

                            $maskFinancial = true;

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | LEVEL / CATEGORY MASK
                        |--------------------------------------------------------------------------
                        */

                        if ($isManagerKeuangan) {

                            $maskLevelCategory = false;

                        } elseif ($isHeadHrd || $isKeuangan || $isHrd) {

                            $maskLevelCategory =
                                $level === null ||
                                $level >= 14;

                        } else {

                            $maskLevelCategory = true;

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PTKP
                        |--------------------------------------------------------------------------
                        |
                        | Tahap ini hanya menyimpan/menampilkan PTKP.
                        | TER dan kategori belum digunakan.
                        |
                        */

                        $ptkpRaw = strtoupper(
                            trim((string) $contract?->ptkp_status)
                        );

                        $ptkpClean = preg_replace(
                            '/\s+/',
                            '',
                            $ptkpRaw
                        ) ?? $ptkpRaw;

                        $ptkpClean = str_replace(
                            ['/', '-', '_'],
                            '',
                            $ptkpClean
                        );

                        $ptkpDisplay = $ptkpClean;

                        if (preg_match('/^(TK|K)(0|[1-4])$/', $ptkpClean, $ptkpMatch)) {
                            $ptkpDisplay =
                                $ptkpMatch[2] === '0'
                                    ? $ptkpMatch[1] . '0'
                                    : $ptkpMatch[1] . '0' . $ptkpMatch[2];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | BPJS
                        |--------------------------------------------------------------------------
                        */

                        $bpjsTkActive = (bool) (
                            $contract?->is_bpjstk_active ?? false
                        );

                        $bpjsKsActive = (bool) (
                            $contract?->is_bpjs_health_active ?? false
                        );

                        $manualBpjs = (bool) (
                            $contract?->use_manual_bpjs ?? false
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | EMPLOYMENT TYPE
                        |--------------------------------------------------------------------------
                        */

                        $terminationTypes = [
                            'PHK',
                            'Resign',
                            'Pensiun',
                            'End_Contract',
                        ];

                    @endphp


                    <tr>

                        {{-- ==========================================================
                             NO
                        =========================================================== --}}

                        <td class="text-center">

                            <span class="fw-semibold text-muted">

                                {{
                                    $employees->firstItem()
                                        ? $employees->firstItem() + $index
                                        : $index + 1
                                }}

                            </span>

                        </td>


                        {{-- ==========================================================
                             KARYAWAN
                        =========================================================== --}}

                        <td>

                            <div class="employee-name">

                                {{ $emp->full_name }}

                            </div>

                            <div class="employee-nik">

                                NIK:
                                {{ $emp->nik_ktp }}

                            </div>

                        </td>


                        {{-- ==========================================================
                             JABATAN / DEPARTMENT / AREA / LEVEL
                        =========================================================== --}}

                        <td>

                            @if($contract)

                                {{-- JOB TITLE --}}
                                <div class="job-title">

                                    {{ $contract->job_title ?? '-' }}

                                </div>


                                {{-- DEPARTMENT + AREA --}}
                                <div class="job-meta">

                                    <span>

                                        <strong>Div:</strong>

                                        {{ $contract->department ?? '-' }}

                                    </span>


                                    @if(!empty($contract->placement_area))

                                        <span>

                                            |

                                            <strong>Area:</strong>

                                            {{ $contract->placement_area }}

                                        </span>

                                    @endif

                                </div>


                                {{-- CATEGORY + LEVEL --}}
                                <div class="job-level">

                                    @if($maskLevelCategory)

                                        <span class="text-muted">

                                            Cat: *****
                                            |
                                            Lvl: *****

                                        </span>

                                    @else

                                        Cat:
                                        {{ $contract->category ?? '-' }}

                                        |

                                        Lvl:
                                        {{ $contract->level ?? '-' }}

                                    @endif

                                </div>

                            @else

                                <span class="text-muted">

                                    Belum ada kontrak aktif

                                </span>

                            @endif

                        </td>


                        {{-- ==========================================================
                             STATUS KERJA
                        =========================================================== --}}

                        <td class="text-center">

                            @if($contract)

                                @if(
                                    in_array(
                                        $contract->employment_type,
                                        $terminationTypes,
                                        true
                                    )
                                )

                                    <span
                                        class="badge bg-danger status-badge"
                                        title="Alasan: {{ $contract->exit_reason ?? '-' }}"
                                    >

                                        <i class="fa-solid fa-user-slash me-1"></i>

                                        {{ $contract->employment_type }}

                                    </span>


                                    @if($contract->exit_date)

                                        <div
                                            class="text-muted mt-1"
                                            style="font-size: 10px;"
                                        >

                                            {{ \Carbon\Carbon::parse($contract->exit_date)->format('d/m/Y') }}

                                        </div>

                                    @endif

                                @else

                                    @php
                                        $roman = [
                                            1 => 'I',
                                            2 => 'II',
                                            3 => 'III',
                                            4 => 'IV',
                                            5 => 'V',
                                            6 => 'VI',
                                            7 => 'VII',
                                        ];

                                        $employmentLabel =
                                            $contract->employment_type === 'PKWTT'
                                                ? 'Tetap'
                                                : ($contract->employment_type === 'PKWT'
                                                    ? 'Kontrak ' . ($roman[(int) $contract->pkwt_sequence] ?? (string) $contract->pkwt_sequence)
                                                    : ($contract->employment_type ?? '-'));
                                    @endphp

                                    <span class="badge bg-primary status-badge">

                                        <i class="fa-solid fa-user-check me-1"></i>

                                        {{ $employmentLabel }}

                                    </span>

                                @endif

                            @else

                                <span class="badge bg-secondary status-badge">

                                    Belum Set

                                </span>

                            @endif

                        </td>


                        {{-- ==========================================================
                             GAPOK + TUNJANGAN
                        =========================================================== --}}

                        @if($showFinancialColumns)

                            {{-- GAPOK --}}
                            <td class="text-end">

                                @if(!$contract)

                                    <span class="text-muted">
                                        -
                                    </span>

                                @elseif($maskFinancial)

                                    <span
                                        class="salary-masked"
                                        title="Nominal hanya dapat dilihat untuk level 13 ke bawah"
                                    >
                                        *****
                                    </span>

                                @else

                                    <span class="salary-value">

                                        Rp
                                        {{ number_format(
                                            (float) ($contract->basic_salary ?? 0),
                                            0,
                                            ',',
                                            '.'
                                        ) }}

                                    </span>

                                @endif

                            </td>


                            {{-- TUNJANGAN --}}
                            <td class="text-end">

                                @if(!$contract)

                                    <span class="text-muted">
                                        -
                                    </span>

                                @elseif($maskFinancial)

                                    <span
                                        class="salary-masked"
                                        title="Nominal hanya dapat dilihat untuk level 13 ke bawah"
                                    >
                                        *****
                                    </span>

                                @else

                                    <span class="salary-value">

                                        Rp
                                        {{ number_format(
                                            (float) ($contract->allowance ?? 0),
                                            0,
                                            ',',
                                            '.'
                                        ) }}

                                    </span>

                                @endif

                            </td>

                        @endif


                        {{-- ==========================================================
                             PTKP
                        =========================================================== --}}

                        <td class="text-center">

                            @if(!$contract)

                                <span
                                    class="badge bg-light text-muted border status-badge"
                                >
                                    Belum Set
                                </span>

                            @elseif($maskFinancial)

                                <span
                                    class="salary-masked"
                                    title="PTKP hanya dapat dilihat untuk level 13 ke bawah"
                                >
                                    *****
                                </span>

                            @elseif($contract->ptkp_status)

                                <div class="ptkp-value">

                                    {{ $ptkpDisplay }}

                                </div>

                            @else

                                <span
                                    class="badge bg-light text-muted border status-badge"
                                >
                                    Belum Set
                                </span>

                            @endif

                        </td>


                        {{-- ==========================================================
                             STATUS BPJS
                        =========================================================== --}}

                        <td class="text-center">

                            @if(!$contract)

                                <span
                                    class="badge bg-light text-muted border status-badge"
                                >
                                    Belum Set
                                </span>

                            @elseif($maskFinancial)

                                <span
                                    class="salary-masked"
                                    title="Informasi BPJS hanya dapat dilihat untuk level 13 ke bawah"
                                >
                                    *****
                                </span>

                            @else

                                <div class="bpjs-status">

                                    {{-- BPJS TK --}}
                                    @if($bpjsTkActive)

                                        <span class="badge bg-success">

                                            <i class="fa-solid fa-check me-1"></i>

                                            TK ON

                                        </span>

                                    @else

                                        <span class="badge bg-light text-muted border">

                                            <i class="fa-solid fa-xmark me-1"></i>

                                            TK OFF

                                        </span>

                                    @endif


                                    {{-- BPJS KS --}}
                                    @if($bpjsKsActive)

                                        <span class="badge bg-info text-dark">

                                            <i class="fa-solid fa-check me-1"></i>

                                            KS ON

                                        </span>

                                    @else

                                        <span class="badge bg-light text-muted border">

                                            <i class="fa-solid fa-xmark me-1"></i>

                                            KS OFF

                                        </span>

                                    @endif


                                    {{-- AUTO / MANUAL --}}
                                    @if($manualBpjs)

                                        <span
                                            class="badge bpjs-manual"
                                            title="Kontrak menggunakan nominal BPJS manual"
                                        >

                                            <i class="fa-solid fa-sliders me-1"></i>

                                            MANUAL

                                        </span>

                                    @else

                                        <span
                                            class="badge bpjs-auto"
                                            title="Kontrak menggunakan perhitungan BPJS otomatis"
                                        >

                                            <i class="fa-solid fa-calculator me-1"></i>

                                            AUTO

                                        </span>

                                    @endif

                                </div>

                            @endif

                        </td>


                        {{-- ==========================================================
                             AKSI
                        =========================================================== --}}

                        <td class="text-center">

                            <a
                                href="{{ route(
                                    'contracts.local.edit',
                                    $emp->uuid
                                ) }}"
                                class="btn btn-sm btn-outline-primary rounded-2 btn-edit-contract"
                                title="Kelola Kontrak & Gaji Local"
                            >

                                <i class="fa-solid fa-pen-to-square me-1"></i>

                                Edit Kontrak

                            </a>

                        </td>

                    </tr>


                @empty

                    {{-- ==========================================================
                         EMPTY
                    =========================================================== --}}

                    <tr>

                        <td
                            colspan="{{ $showFinancialColumns ? 9 : 7 }}"
                            class="text-center py-5"
                        >

                            <div class="empty-icon">

                                <i class="fa-solid fa-users-slash"></i>

                            </div>

                            <div class="empty-title">

                                Belum ada data karyawan Local.

                            </div>

                            <div class="empty-description mt-1">

                                Data karyawan Local akan tampil
                                setelah tersedia.

                            </div>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- ================================================================
         PAGINATION
    ================================================================= --}}

    @if($employees->hasPages())

        <div class="d-flex justify-content-between align-items-center mt-4">

            <div class="text-muted small">

                Menampilkan

                <strong>
                    {{ $employees->firstItem() ?? 0 }}
                </strong>

                sampai

                <strong>
                    {{ $employees->lastItem() ?? 0 }}
                </strong>

                dari

                <strong>
                    {{ $employees->total() }}
                </strong>

                karyawan.

            </div>


            <div>

                {{ $employees->links() }}

            </div>

        </div>

    @else

        @if($employees->count() > 0)

            <div class="text-muted small mt-3">

                Total
                <strong>{{ $employees->count() }}</strong>
                karyawan.

            </div>

        @endif

    @endif

</div>

@endsection