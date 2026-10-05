@extends('layouts.app')

@section('title', 'Setup Kontrak & Gaji - Local')

@section('content')

@php

/*
|--------------------------------------------------------------------------
| ROLE
|--------------------------------------------------------------------------
*/

$userRole = auth()->user()->role ?? null;

$financeRoles = [
    'super_admin',
    'manager_keuangan',
];

$headHrdRoles = [
    'head_hrd',
    'kepala_hrd',
];

$isManagerKeuangan = in_array(
    $userRole,
    $financeRoles,
    true
);

$isHeadHrd = in_array(
    $userRole,
    $headHrdRoles,
    true
);

$isHrd = $userRole === 'hrd';
$isKeuangan = $userRole === 'keuangan';


/*
|--------------------------------------------------------------------------
| CURRENT CONTRACT / HISTORY
|--------------------------------------------------------------------------
*/

$currentContract = $contract ?? null;
$currentHistory  = $history ?? null;

$histories = $histories ?? collect();


/*
|--------------------------------------------------------------------------
| CURRENT LEVEL
|--------------------------------------------------------------------------
*/

$contractLevel =
    $currentHistory?->level
    ?? $currentContract?->level
    ?? null;

$levelNumber = is_numeric($contractLevel)
    ? (int) $contractLevel
    : null;

$isHighLevel =
    $levelNumber !== null &&
    $levelNumber >= 14;


/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
|
| FINANCE
|   super_admin
|   manager_keuangan
|
| HEAD HRD
|   Level/category + financial jika level <= 13
|
| HRD
|   Level/category jika level <= 13
|   Financial tidak boleh dilihat
|
*/

$canSeeSalary =
    $isManagerKeuangan ||
    (
        ($isHeadHrd || $isKeuangan) &&
        ($levelNumber === null || $levelNumber <= 13)
    );

$canSeeLevelCategory =
    $isManagerKeuangan ||
    (
        ($isHeadHrd || $isHrd || $isKeuangan) &&
        ($levelNumber === null || $levelNumber <= 13)
    );

$maskLevelCategory = !$canSeeLevelCategory;
$maskFinancial     = !$canSeeSalary;


/*
|--------------------------------------------------------------------------
| CURRENT HISTORY ID
|--------------------------------------------------------------------------
*/

$currentHistoryId =
    $currentContract?->current_contract_history_id
    ?? $currentHistory?->id_contract_history
    ?? null;


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

$formatRupiah = function ($value) {

    if ($value === null || $value === '') {
        return '';
    }

    return number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
};


/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

$formatDate = function ($value) {

    if (!$value) {
        return '';
    }

    try {

        return \Carbon\Carbon::parse($value)
            ->format('Y-m-d');

    } catch (\Throwable $e) {

        return '';

    }

};


/*
|--------------------------------------------------------------------------
| CURRENT VALUES
|--------------------------------------------------------------------------
*/

$basicSalary =
    $currentHistory?->basic_salary
    ?? $currentContract?->basic_salary
    ?? 0;

$allowance =
    $currentHistory?->allowance
    ?? $currentContract?->allowance
    ?? 0;

$category =
    $currentHistory?->category
    ?? $currentContract?->category
    ?? '';

$level =
    $currentHistory?->level
    ?? $currentContract?->level
    ?? '';

$employmentType =
    $currentHistory?->employment_type
    ?? $currentContract?->employment_type
    ?? 'PKWT';

$pkwtSequence =
    $currentHistory?->pkwt_sequence
    ?? $currentContract?->pkwt_sequence
    ?? '';

$jobTitle =
    $currentHistory?->job_title
    ?? $currentContract?->job_title
    ?? '';

$department =
    $currentHistory?->department
    ?? $currentContract?->department
    ?? '';

$placementArea =
    $currentHistory?->placement_area
    ?? $currentContract?->placement_area
    ?? '';

$fingerprintPin =
    $currentHistory?->fingerprint_pin
    ?? $currentContract?->fingerprint_pin
    ?? '';

$nikFingerprint =
    $currentHistory?->nik_fingerprint
    ?? $currentContract?->nik_fingerprint
    ?? '';


/*
|--------------------------------------------------------------------------
| DATE VALUES
|--------------------------------------------------------------------------
*/

$startDate = $formatDate(
    $currentHistory?->start_date
    ?? $currentContract?->start_date
);

$endDate = $formatDate(
    $currentHistory?->end_date
    ?? $currentContract?->end_date
);

$exitDate = $formatDate(
    $currentHistory?->exit_date
    ?? $currentContract?->exit_date
);

$exitReason =
    $currentHistory?->exit_reason
    ?? $currentContract?->exit_reason
    ?? '';


/*
|--------------------------------------------------------------------------
| BPJS
|--------------------------------------------------------------------------
|
| PENTING:
| Nilai dibaca dari history aktif terlebih dahulu.
|
*/

$isBpjstkActive = (bool) (
    $currentHistory?->is_bpjstk_active
    ?? $currentContract?->is_bpjstk_active
    ?? false
);

$isBpjsHealthActive = (bool) (
    $currentHistory?->is_bpjs_health_active
    ?? $currentContract?->is_bpjs_health_active
    ?? false
);

$useManualBpjs = (bool) (
    $currentHistory?->use_manual_bpjs
    ?? $currentContract?->use_manual_bpjs
    ?? false
);


/*
|--------------------------------------------------------------------------
| MANUAL BPJS
|--------------------------------------------------------------------------
*/

$manualBpjstkEmployee =
    $currentHistory?->manual_bpjs_tk_employee
    ?? $currentHistory?->manual_bpjstk_employee
    ?? $currentContract?->manual_bpjs_tk_employee
    ?? $currentContract?->manual_bpjstk_employee
    ?? 0;

$manualBpjsHealthEmployee =
    $currentHistory?->manual_bpjs_ks_employee
    ?? $currentHistory?->manual_bpjs_health_employee
    ?? $currentContract?->manual_bpjs_ks_employee
    ?? $currentContract?->manual_bpjs_health_employee
    ?? 0;

$manualBpjsCompany =
    $currentHistory?->manual_bpjs_company
    ?? $currentContract?->manual_bpjs_company
    ?? 0;


/*
|--------------------------------------------------------------------------
| PTKP
|--------------------------------------------------------------------------
*/

$normalizePtkpDisplay = function ($value) {
    $raw = strtoupper(trim((string) ($value ?? '')));

    $raw = str_replace(
        ["\xC2\xA0", "\xE2\x80\xAF"],
        ' ',
        $raw
    );

    $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;
    $raw = str_replace(['/', '-', '_'], '', $raw);

    if ($raw === '' || $raw === '0') {
        return 'TK0';
    }

    if (!preg_match(
        '/^(TK|K)(0[0-4]|[0-4])$/u',
        $raw,
        $match
    )) {
        return 'TK0';
    }

    $prefix = $match[1];
    $numberRaw = $match[2];
    $number = (int) $numberRaw;

    if ($prefix === 'TK') {
        return $number === 0
            ? 'TK0'
            : 'TK' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    // Format lama K0-K3 dinormalisasi ke K01-K04.
    if (strlen($numberRaw) === 2) {
        return $number >= 1 && $number <= 4
            ? 'K' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)
            : 'K01';
    }

    return $number >= 0 && $number <= 3
        ? 'K' . str_pad((string) ($number + 1), 2, '0', STR_PAD_LEFT)
        : 'K01';
};

$ptkp = $normalizePtkpDisplay(
    $currentHistory?->ptkp_status
        ?? $currentContract?->ptkp_status
        ?? 'TK0'
);

$ptkpOptions = [
    'TK0',
    'TK01',
    'TK02',
    'TK03',
    'K01',
    'K02',
    'K03',
    'K04',
];

$employmentStatusOptions = $employmentStatusOptions
    ?? [
        'PKWTT' => 'Tetap',
        'PKWT|1' => 'Kontrak I',
        'PKWT|2' => 'Kontrak II',
        'PKWT|3' => 'Kontrak III',
        'PKWT|4' => 'Kontrak IV',
        'PKWT|5' => 'Kontrak V',
        'PKWT|6' => 'Kontrak VI',
        'PKWT|7' => 'Kontrak VII',
    ];

$currentEmploymentUi =
    $employmentType === 'PKWT'
        ? 'PKWT|' . ((int) ($pkwtSequence ?: 1))
        : $employmentType;

$newEmploymentUi =
    $employmentType === 'PKWT'
        ? 'PKWT|' . ((int) ($nextPkwtSequence ?: 1))
        : $employmentType;


/*
|--------------------------------------------------------------------------
| TERMINATION
|--------------------------------------------------------------------------
*/

$terminationTypes = [
    'PHK'          => 'PHK',
    'Resign'       => 'Resign',
    'Pensiun'      => 'Pensiun',
    'End_Contract' => 'End Contract',
];

$isTermination = in_array(
    $employmentType,
    array_keys($terminationTypes),
    true
);


/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

$backUrl = route(
    'contracts.local.index'
);

$updateUrl = route(
    'contracts.local.update',
    $employee->uuid
);

$addPeriodUrl = route(
    'contracts.local.period.store',
    $employee->uuid
);


/*
|--------------------------------------------------------------------------
| NEXT PKWT
|--------------------------------------------------------------------------
*/

$nextPkwtSequence =
    ((int) ($currentHistory?->pkwt_sequence ?? 0)) + 1;

if ($nextPkwtSequence > 7) {
    $nextPkwtSequence = 7;
}

if ($nextPkwtSequence < 1) {
    $nextPkwtSequence = 1;
}


/*
|--------------------------------------------------------------------------
| NEW PERIOD START DATE
|--------------------------------------------------------------------------
*/

$newPeriodStartDate = '';

if ($endDate) {

    try {

        $newPeriodStartDate =
            \Carbon\Carbon::parse($endDate)
                ->addDay()
                ->format('Y-m-d');

    } catch (\Throwable $e) {

        $newPeriodStartDate =
            now()->format('Y-m-d');

    }

} else {

    $newPeriodStartDate =
        now()->format('Y-m-d');

}

@endphp


<style>

/*
|--------------------------------------------------------------------------
| TOGGLE BUTTON
|--------------------------------------------------------------------------
*/

.rhuekamp-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 130px;
    border: 0;
    border-radius: 8px;
    padding: 8px 14px;
    font-weight: 700;
    transition: all .2s ease;
    cursor: pointer;
}

.rhuekamp-toggle.toggle-on {
    background: #198754;
    color: #fff;
}

.rhuekamp-toggle.toggle-off {
    background: #6c757d;
    color: #fff;
}

.rhuekamp-toggle:hover {
    transform: translateY(-1px);
    opacity: .92;
}

.rhuekamp-toggle .toggle-icon {
    width: 22px;
    text-align: center;
}

.rhuekamp-toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rhuekamp-toggle-description {
    font-size: 13px;
    color: #6c757d;
}

</style>


<div class="container-fluid py-4">


{{-- ==============================================================
     FLASH
============================================================== --}}

@if(session('success'))

    <div class="alert alert-success alert-dismissible fade show shadow-sm">

        <i class="fas fa-check-circle me-2"></i>

        {{ session('success') }}

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

@endif


@if(session('error'))

    <div class="alert alert-danger alert-dismissible fade show shadow-sm">

        <i class="fas fa-exclamation-circle me-2"></i>

        {{ session('error') }}

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

@endif


@if($errors->any())

    <div class="alert alert-danger shadow-sm">

        <div class="fw-bold mb-2">

            <i class="fas fa-exclamation-triangle me-1"></i>

            Terdapat kesalahan:

        </div>

        <ul class="mb-0">

            @foreach($errors->all() as $error)

                <li>{{ $error }}</li>

            @endforeach

        </ul>

    </div>

@endif


{{-- ==============================================================
     HEADER
============================================================== --}}

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

    <div>

        <h3 class="fw-bold mb-1">
            Setup Jabatan, Kontrak & Gaji Acuan
        </h3>

        <div class="text-muted">
            Local
        </div>

        <div class="mt-2">

            <span class="fw-semibold">
                {{ $employee->full_name ?? $employee->name ?? '-' }}
            </span>

            <span class="text-muted ms-2">

                NIK:
                {{ $employee->nik_ktp ?? $employee->nik_ktp ?? '-' }}

            </span>

        </div>

    </div>


    <div class="mt-3 mt-md-0">

        <a
            href="{{ $backUrl }}"
            class="btn btn-outline-secondary">

            <i class="fas fa-arrow-left me-1"></i>

            Kembali

        </a>

    </div>

</div>


{{-- ==============================================================
     ACCESS INFORMATION
============================================================== --}}

@if(!$isManagerKeuangan)

    <div class="alert alert-info shadow-sm">

        <i class="fas fa-info-circle me-2"></i>

        @if($isHeadHrd)

            Anda login sebagai <strong>Head HRD</strong>.
            Data financial hanya dapat dilihat untuk
            contract dengan level maksimal <strong>13</strong>.

        @elseif($isKeuangan)

            Anda login sebagai <strong>Keuangan</strong>.
            Data financial hanya dapat dilihat untuk
            contract dengan level maksimal <strong>13</strong>.

        @elseif($isHrd)

            Anda login sebagai <strong>HRD</strong>.
            Data Level/Kategori dapat dilihat untuk
            contract dengan level maksimal <strong>13</strong>.
            Data financial tidak ditampilkan.

        @else

            Akses financial dibatasi berdasarkan role.

        @endif

    </div>

@endif


{{-- ==============================================================
     CURRENT CONTRACT
============================================================== --}}

@if($currentContract || $currentHistory)

<form
    id="currentContractForm"
    action="{{ $updateUrl }}"
    method="POST">

    {{--
        Salary menggunakan display field + hidden raw value.
        Dengan demikian nilai yang dikirim ke Laravel selalu numerik
        dan tidak bergantung pada formatter browser saat submit.
    --}}

    @csrf

    @method('PUT')


    <div class="row g-4">


        {{-- ======================================================
             LEFT
        ======================================================= --}}

        <div class="col-lg-6">

            <div class="card card-custom border-0 shadow-sm h-100">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-1">
                        Penempatan & Status Kerja
                    </h5>

                    <small class="text-muted d-block mb-4">
                        Informasi jabatan dan periode contract aktif
                    </small>


                    {{-- JOB TITLE --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Jabatan
                        </label>

                        <input
                            type="text"
                            name="job_title"
                            class="form-control"
                            value="{{ old('job_title', $jobTitle) }}"
                            required>

                    </div>


                    {{-- DEPARTMENT --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Department
                        </label>

                        <input
                            type="text"
                            name="department"
                            class="form-control"
                            value="{{ old('department', $department) }}">

                    </div>


                    {{-- PLACEMENT --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Penempatan
                        </label>

                        <input
                            type="text"
                            name="placement_area"
                            class="form-control"
                            value="{{ old('placement_area', $placementArea) }}"
                            placeholder="Contoh: Site Kalimantan">

                    </div>


                    {{-- FINGERPRINT --}}

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Fingerprint PIN
                            </label>

                            <input
                                type="text"
                                name="fingerprint_pin"
                                class="form-control"
                                value="{{ old('fingerprint_pin', $fingerprintPin) }}">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                NIK Fingerprint
                            </label>

                            <input
                                type="text"
                                name="nik_fingerprint"
                                class="form-control"
                                value="{{ old('nik_fingerprint', $nikFingerprint) }}">

                        </div>

                    </div>


                    <hr class="my-4">


                    {{-- CATEGORY --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Kategori
                        </label>

                        @if($canSeeLevelCategory)

                            <input
                                type="text"
                                name="category"
                                id="category"
                                class="form-control"
                                value="{{ old('category', $category) }}">

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="category"
                                value="{{ $category }}">

                        @endif

                    </div>


                    {{-- LEVEL --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Level
                        </label>

                        @if($canSeeLevelCategory)

                            <input
                                type="number"
                                name="level"
                                id="level"
                                class="form-control"
                                min="1"
                                value="{{ old('level', $level) }}">

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="level"
                                id="level"
                                value="{{ $level }}">

                        @endif

                    </div>


                    @if($isHighLevel && !$isManagerKeuangan)

                        <div class="alert alert-warning small">

                            <i class="fas fa-lock me-1"></i>

                            Contract level
                            <strong>{{ $levelNumber }}</strong>
                            berada pada level 14 atau lebih.

                            Data kategori, level dan financial
                            dibatasi berdasarkan role Anda.

                        </div>

                    @endif


                    <hr class="my-4">


                    {{-- STATUS KARYAWAN --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Status Karyawan
                        </label>

                        <select
                            name="employment_status_ui"
                            id="employment_status_ui"
                            class="form-select">

                            <optgroup label="Status dari Excel">

                                @foreach($employmentStatusOptions as $value => $label)

                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            old(
                                                'employment_status_ui',
                                                $currentEmploymentUi
                                            ) === $value
                                        )>
                                        {{ $label }}
                                    </option>

                                @endforeach

                            </optgroup>

                            <optgroup label="Status Penghentian Kerja">

                                @foreach($terminationTypes as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            old(
                                                'employment_status_ui',
                                                $currentEmploymentUi
                                            ) === $value
                                        )>
                                        {{ $label }}
                                    </option>
                                @endforeach

                            </optgroup>

                            {{-- Compatibility untuk data lama --}}
                            @if(
                                !array_key_exists($currentEmploymentUi, $employmentStatusOptions)
                                &&
                                !array_key_exists($currentEmploymentUi, $terminationTypes)
                                &&
                                $currentEmploymentUi !== ''
                            )
                                <option value="{{ $currentEmploymentUi }}" selected>
                                    {{ $currentEmploymentUi }}
                                </option>
                            @endif

                        </select>

                        <input
                            type="hidden"
                            name="employment_type"
                            id="employment_type"
                            value="{{ $employmentType }}">

                        <input
                            type="hidden"
                            name="pkwt_sequence"
                            id="pkwt_sequence"
                            value="{{ $pkwtSequence }}">

                        <div
                            id="pkwtSequenceBox"
                            class="small text-muted mt-2"
                            style="{{ $employmentType === 'PKWT' ? '' : 'display:none;' }}">
                            PKWT ke-<strong id="pkwtSequenceLabel">{{ $pkwtSequence ?: '-' }}</strong>
                        </div>

                    </div>


                    {{-- DATE --}}

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Tanggal Mulai
                            </label>

                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                class="form-control"
                                value="{{ old('start_date', $startDate) }}"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Tanggal Berakhir
                            </label>

                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                class="form-control"
                                value="{{ old('end_date', $endDate) }}">

                        </div>

                    </div>


                    {{-- TERMINATION --}}

                    <div
                        id="terminationBox"
                        class="mt-3"
                        style="{{ $isTermination ? '' : 'display:none;' }}">

                        <div class="alert alert-danger">

                            <div class="fw-bold mb-3">

                                <i class="fas fa-user-slash me-1"></i>

                                Informasi Penghentian Kerja

                            </div>


                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Tanggal Keluar
                                </label>

                                <input
                                    type="date"
                                    name="exit_date"
                                    class="form-control"
                                    value="{{ old('exit_date', $exitDate) }}">

                            </div>


                            <div>

                                <label class="form-label fw-semibold">
                                    Alasan
                                </label>

                                <textarea
                                    name="exit_reason"
                                    class="form-control"
                                    rows="3">{{ old('exit_reason', $exitReason) }}</textarea>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        {{-- ======================================================
             RIGHT
        ======================================================= --}}

        <div class="col-lg-6">

            <div class="card card-custom border-0 shadow-sm h-100">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-1">
                        Acuan Financial, BPJS & Pajak
                    </h5>

                    <small class="text-muted d-block mb-4">
                        Data yang digunakan sebagai dasar payroll
                    </small>


                    {{-- =================================================
                         BASIC SALARY
                    ================================================== --}}

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Gaji Pokok
                        </label>

                        @if($canSeeSalary)

                            <div class="input-group">

                                <span class="input-group-text">
                                    Rp
                                </span>

                                {{--
                                    PENTING:
                                    Gunakan SATU input bernama basic_salary.
                                    Tidak memakai display + hidden input karena
                                    raw value bisa tertinggal satu submit.
                                    Controller membersihkan format Rupiah di server.
                                --}}
                                <input
                                    type="text"
                                    name="basic_salary"
                                    id="basic_salary"
                                    class="form-control currency-input"
                                    value="{{ old('basic_salary', $formatRupiah($basicSalary)) }}"
                                    autocomplete="off"
                                    inputmode="numeric">

                            </div>

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="basic_salary"
                                value="{{ $basicSalary }}">

                        @endif

                    </div>


                    {{-- ALLOWANCE --}}

                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Tunjangan Level
                        </label>

                        @if($canSeeSalary)

                            <div class="input-group">

                                <span class="input-group-text">
                                    Rp
                                </span>

                                {{--
                                    Allowance tetap menjadi field bernama allowance.
                                    Nilainya dibentuk server berdasarkan:
                                    basic_salary x level x 2%.
                                    JS hanya membantu preview, bukan sumber utama.
                                --}}
                                <input
                                    type="text"
                                    name="allowance"
                                    id="allowance"
                                    class="form-control"
                                    value="{{ old('allowance', $formatRupiah($allowance)) }}"
                                    readonly>

                            </div>

                            <small class="text-muted">
                                Tunjangan = Gaji Pokok × Level × 2%.
                            </small>

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="allowance"
                                value="{{ $allowance }}">

                        @endif

                    </div>

                    <hr class="my-4">


                    {{-- =================================================
                         BPJS
                    ================================================== --}}

                    <h6 class="fw-bold mb-3">
                        BPJS
                    </h6>


                    @if($canSeeSalary)

                        {{-- BPJS TK --}}

                        <div class="mb-4">

                            <label class="form-label fw-semibold d-block">
                                BPJS Ketenagakerjaan
                            </label>

                            <div class="rhuekamp-toggle-wrapper">

                                <button
                                    type="button"
                                    class="rhuekamp-toggle {{ $isBpjstkActive ? 'toggle-on' : 'toggle-off' }}"
                                    data-toggle-field="is_bpjstk_active"
                                    data-toggle-state="{{ $isBpjstkActive ? '1' : '0' }}">

                                    <span class="toggle-icon">

                                        <i class="fas {{ $isBpjstkActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                    </span>

                                    <span class="toggle-label">

                                        {{ $isBpjstkActive ? 'ON' : 'OFF' }}

                                    </span>

                                </button>

                                <span class="rhuekamp-toggle-description">

                                    {{ $isBpjstkActive
                                        ? 'BPJS TK akan dihitung dalam payroll.'
                                        : 'BPJS TK tidak dihitung dalam payroll.'
                                    }}

                                </span>

                            </div>

                        </div>


                        {{-- BPJS HEALTH --}}

                        <div class="mb-4">

                            <label class="form-label fw-semibold d-block">
                                BPJS Kesehatan
                            </label>

                            <div class="rhuekamp-toggle-wrapper">

                                <button
                                    type="button"
                                    class="rhuekamp-toggle {{ $isBpjsHealthActive ? 'toggle-on' : 'toggle-off' }}"
                                    data-toggle-field="is_bpjs_health_active"
                                    data-toggle-state="{{ $isBpjsHealthActive ? '1' : '0' }}">

                                    <span class="toggle-icon">

                                        <i class="fas {{ $isBpjsHealthActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                    </span>

                                    <span class="toggle-label">

                                        {{ $isBpjsHealthActive ? 'ON' : 'OFF' }}

                                    </span>

                                </button>

                                <span class="rhuekamp-toggle-description">

                                    {{ $isBpjsHealthActive
                                        ? 'BPJS Kesehatan akan dihitung dalam payroll.'
                                        : 'BPJS Kesehatan tidak dihitung dalam payroll.'
                                    }}

                                </span>

                            </div>

                        </div>


                        {{-- MANUAL BPJS --}}

                        <div class="border rounded p-3 mb-4">

                            <div class="mb-3">

                                <label class="form-label fw-semibold d-block">
                                    Override BPJS Manual
                                </label>

                                <div class="rhuekamp-toggle-wrapper">

                                    <button
                                        type="button"
                                        class="rhuekamp-toggle {{ $useManualBpjs ? 'toggle-on' : 'toggle-off' }}"
                                        data-toggle-field="use_manual_bpjs"
                                        data-toggle-state="{{ $useManualBpjs ? '1' : '0' }}">

                                        <span class="toggle-icon">

                                            <i class="fas {{ $useManualBpjs ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                        </span>

                                        <span class="toggle-label">

                                            {{ $useManualBpjs ? 'ON' : 'OFF' }}

                                        </span>

                                    </button>

                                    <span class="rhuekamp-toggle-description">

                                        {{ $useManualBpjs
                                            ? 'Menggunakan nominal BPJS manual.'
                                            : 'Menggunakan perhitungan otomatis.'
                                        }}

                                    </span>

                                </div>

                            </div>


                            <div
                                id="manualBpjsBox"
                                style="{{ $useManualBpjs ? '' : 'display:none;' }}">

                                {{-- TK --}}

                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        BPJS TK - Karyawan
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            Rp
                                        </span>

                                        <input
                                            type="text"
                                            name="manual_bpjs_tk_employee"
                                            class="form-control currency-input"
                                            value="{{ old(
                                                'manual_bpjs_tk_employee',
                                                $formatRupiah($manualBpjstkEmployee)
                                            ) }}">

                                    </div>

                                </div>


                                {{-- KS --}}

                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        BPJS Kesehatan - Karyawan
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            Rp
                                        </span>

                                        <input
                                            type="text"
                                            name="manual_bpjs_ks_employee"
                                            class="form-control currency-input"
                                            value="{{ old(
                                                'manual_bpjs_ks_employee',
                                                $formatRupiah($manualBpjsHealthEmployee)
                                            ) }}">

                                    </div>

                                </div>


                                {{-- COMPANY --}}

                                <div>

                                    <label class="form-label fw-semibold">
                                        BPJS - Perusahaan
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            Rp
                                        </span>

                                        <input
                                            type="text"
                                            name="manual_bpjs_company"
                                            class="form-control currency-input"
                                            value="{{ old(
                                                'manual_bpjs_company',
                                                $formatRupiah($manualBpjsCompany)
                                            ) }}">

                                    </div>

                                </div>

                            </div>

                        </div>

                    @else

                        <div class="border rounded p-3 mb-4 bg-light">

                            <div class="fw-bold text-muted">

                                <i class="fas fa-lock me-2"></i>

                                Data BPJS Financial

                            </div>

                            <small class="text-muted">

                                Data BPJS tidak tersedia untuk role Anda.

                            </small>

                        </div>

                    @endif


                    {{-- =================================================
                         PTKP
                    ================================================== --}}

                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            PTKP / PPh21
                        </label>

                        @if($canSeeSalary)

                            <select
                                name="ptkp_status"
                                class="form-select">

                                @foreach($ptkpOptions as $item)

                                    <option
                                        value="{{ $item }}"
                                        @selected(
                                            old(
                                                'ptkp_status',
                                                $ptkp
                                            ) === $item
                                        )>

                                        {{ $item }}

                                    </option>

                                @endforeach

                            </select>

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="ptkp_status"
                                value="{{ $ptkp }}">

                        @endif

                    </div>


                    {{-- SAVE --}}

                    <div class="d-flex justify-content-end">

                        <button
                            type="submit"
                            class="btn btn-primary px-4">

                            <i class="fas fa-save me-1"></i>

                            Simpan Perubahan

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</form>

@else

    <div class="alert alert-info">

        <i class="fas fa-info-circle me-2"></i>

        Employee ini belum memiliki contract aktif.

        Gunakan tombol
        <strong>Tambah Contract Period</strong>
        di bawah untuk membuat contract pertama.

    </div>

@endif


{{-- ==============================================================
     CONTRACT HISTORY
============================================================== --}}

<div class="card card-custom border-0 shadow-sm mt-4">

    <div class="card-body p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

            <div>

                <h5 class="fw-bold mb-1">
                    Contract History
                </h5>

                <small class="text-muted">

                    Riwayat kontrak tersimpan.
                    Contract lama otomatis menjadi HISTORY
                    dan tidak dapat diedit.

                </small>

            </div>


            <button
                type="button"
                id="btnAddContractPeriod"
                class="btn btn-success">

                <i class="fas fa-plus me-1"></i>

                Tambah Contract Period

            </button>

        </div>


        {{-- =========================================================
             NEW PERIOD
        ========================================================== --}}

        <div
            id="newPeriodWrapper"
            class="border rounded p-4 mb-4"
            style="display:none;">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h6 class="fw-bold mb-1">
                        Contract Period Baru
                    </h6>

                    <small class="text-muted">

                        Data contract aktif digunakan sebagai template.

                    </small>

                </div>


                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary js-cancel-new-period">

                    <i class="fas fa-times"></i>

                </button>

            </div>


            <form
                action="{{ $addPeriodUrl }}"
                method="POST"
                id="newPeriodForm">

                @csrf


                {{-- BASIC INFORMATION --}}

                <div class="row g-3">


                    {{-- STATUS KARYAWAN / PERIOD BARU --}}

                    <div class="col-md-8">

                        <label class="form-label fw-semibold">
                            Status Karyawan
                        </label>

                        <select
                            name="new_employment_status_ui"
                            id="new_employment_status_ui"
                            class="form-select">

                            @foreach($employmentStatusOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(old('new_employment_status_ui', $newEmploymentUi) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach

                        </select>

                        <input
                            type="hidden"
                            name="employment_type"
                            id="new_employment_type"
                            value="{{ $employmentType }}">

                        <input
                            type="hidden"
                            name="pkwt_sequence"
                            id="new_pkwt_sequence"
                            value="{{ $nextPkwtSequence }}">

                        <div class="small text-muted mt-2">
                            Kontrak baru berikutnya: <strong id="newPkwtSequenceLabel">{{ $nextPkwtSequence }}</strong>
                        </div>

                    </div>


                    {{-- JOB --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Jabatan
                        </label>

                        <input
                            type="text"
                            name="job_title"
                            class="form-control"
                            value="{{ $jobTitle }}"
                            required>

                    </div>


                    {{-- DEPARTMENT --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Department
                        </label>

                        <input
                            type="text"
                            name="department"
                            class="form-control"
                            value="{{ $department }}">

                    </div>


                    {{-- PLACEMENT --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Penempatan
                        </label>

                        <input
                            type="text"
                            name="placement_area"
                            class="form-control"
                            value="{{ $placementArea }}">

                    </div>


                    {{-- FINGERPRINT PIN --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Fingerprint PIN
                        </label>

                        <input
                            type="text"
                            name="fingerprint_pin"
                            class="form-control"
                            value="{{ $fingerprintPin }}">

                    </div>


                    {{-- NIK FINGERPRINT --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            NIK Fingerprint
                        </label>

                        <input
                            type="text"
                            name="nik_fingerprint"
                            class="form-control"
                            value="{{ $nikFingerprint }}">

                    </div>


                    {{-- CATEGORY --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Kategori
                        </label>

                        @if($canSeeLevelCategory)

                            <input
                                type="text"
                                name="category"
                                class="form-control"
                                value="{{ $category }}">

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="category"
                                value="{{ $category }}">

                        @endif

                    </div>


                    {{-- LEVEL --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Level
                        </label>

                        @if($canSeeLevelCategory)

                            <input
                                type="number"
                                name="level"
                                id="new_level"
                                class="form-control"
                                min="1"
                                value="{{ $level }}">

                        @else

                            <input
                                type="text"
                                class="form-control bg-light text-muted fw-bold"
                                value="*****"
                                readonly>

                            <input
                                type="hidden"
                                name="level"
                                value="{{ $level }}">

                        @endif

                    </div>


                    {{-- START DATE --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Tanggal Mulai
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ $newPeriodStartDate }}"
                            required>

                    </div>


                    {{-- END DATE --}}

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Tanggal Berakhir
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="">

                    </div>

                </div>


                <hr class="my-4">


                {{-- FINANCIAL --}}

                <h6 class="fw-bold mb-3">
                    Financial, BPJS & Pajak
                </h6>


                @if($canSeeSalary)

                    <div class="row g-3">


                        {{-- SALARY --}}

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Gaji Pokok
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    Rp
                                </span>

                                <input
                                    type="text"
                                    name="basic_salary"
                                    id="new_basic_salary"
                                    class="form-control new-currency"
                                    value="{{ $formatRupiah($basicSalary) }}">

                            </div>

                        </div>


                        {{-- ALLOWANCE --}}

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Tunjangan Level
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    Rp
                                </span>

                                <input
                                    type="text"
                                    name="allowance"
                                    id="new_allowance"
                                    class="form-control new-currency"
                                    value="{{ $formatRupiah($allowance) }}"
                                    readonly>

                            </div>

                            <small class="text-muted">

                                Gaji Pokok × Level × 2%.

                            </small>

                        </div>


                        {{-- PTKP --}}

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                PTKP / PPh21
                            </label>

                            <select
                                name="ptkp_status"
                                class="form-select">

                                @foreach($ptkpOptions as $item)

                                    <option
                                        value="{{ $item }}"
                                        @selected(old('ptkp_status', $ptkp) === $item)>

                                        {{ $item }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>


                    {{-- =================================================
                         NEW PERIOD BPJS
                    ================================================== --}}

                    <div class="mt-4">


                        {{-- BPJS TK --}}

                        <div class="mb-4">

                            <label class="form-label fw-semibold d-block">
                                BPJS Ketenagakerjaan
                            </label>

                            <div class="rhuekamp-toggle-wrapper">

                                <button
                                    type="button"
                                    class="rhuekamp-toggle {{ $isBpjstkActive ? 'toggle-on' : 'toggle-off' }}"
                                    data-toggle-field="is_bpjstk_active"
                                    data-toggle-state="{{ $isBpjstkActive ? '1' : '0' }}">

                                    <span class="toggle-icon">

                                        <i class="fas {{ $isBpjstkActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                    </span>

                                    <span class="toggle-label">

                                        {{ $isBpjstkActive ? 'ON' : 'OFF' }}

                                    </span>

                                </button>

                                <span class="rhuekamp-toggle-description">

                                    {{ $isBpjstkActive
                                        ? 'BPJS TK akan dihitung.'
                                        : 'BPJS TK tidak dihitung.'
                                    }}

                                </span>

                            </div>

                        </div>


                        {{-- BPJS HEALTH --}}

                        <div class="mb-4">

                            <label class="form-label fw-semibold d-block">
                                BPJS Kesehatan
                            </label>

                            <div class="rhuekamp-toggle-wrapper">

                                <button
                                    type="button"
                                    class="rhuekamp-toggle {{ $isBpjsHealthActive ? 'toggle-on' : 'toggle-off' }}"
                                    data-toggle-field="is_bpjs_health_active"
                                    data-toggle-state="{{ $isBpjsHealthActive ? '1' : '0' }}">

                                    <span class="toggle-icon">

                                        <i class="fas {{ $isBpjsHealthActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                    </span>

                                    <span class="toggle-label">

                                        {{ $isBpjsHealthActive ? 'ON' : 'OFF' }}

                                    </span>

                                </button>

                                <span class="rhuekamp-toggle-description">

                                    {{ $isBpjsHealthActive
                                        ? 'BPJS Kesehatan akan dihitung.'
                                        : 'BPJS Kesehatan tidak dihitung.'
                                    }}

                                </span>

                            </div>

                        </div>


                        {{-- MANUAL BPJS --}}

                        <div class="border rounded p-3 mt-3">

                            <label class="form-label fw-semibold d-block">
                                Override BPJS Manual
                            </label>

                            <div class="rhuekamp-toggle-wrapper mb-3">

                                <button
                                    type="button"
                                    class="rhuekamp-toggle {{ $useManualBpjs ? 'toggle-on' : 'toggle-off' }}"
                                    data-toggle-field="use_manual_bpjs"
                                    data-toggle-state="{{ $useManualBpjs ? '1' : '0' }}">

                                    <span class="toggle-icon">

                                        <i class="fas {{ $useManualBpjs ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>

                                    </span>

                                    <span class="toggle-label">

                                        {{ $useManualBpjs ? 'ON' : 'OFF' }}

                                    </span>

                                </button>

                                <span class="rhuekamp-toggle-description">

                                    {{ $useManualBpjs
                                        ? 'Menggunakan nominal manual.'
                                        : 'Menggunakan perhitungan otomatis.'
                                    }}

                                </span>

                            </div>


                            <div
                                id="newManualBpjsBox"
                                style="{{ $useManualBpjs ? '' : 'display:none;' }}">

                                <div class="row g-3">


                                    {{-- TK --}}

                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            TK Karyawan
                                        </label>

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                Rp
                                            </span>

                                            <input
                                                type="text"
                                                name="manual_bpjs_tk_employee"
                                                class="form-control new-currency"
                                                value="{{ $formatRupiah($manualBpjstkEmployee) }}">

                                        </div>

                                    </div>


                                    {{-- KS --}}

                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            KS Karyawan
                                        </label>

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                Rp
                                            </span>

                                            <input
                                                type="text"
                                                name="manual_bpjs_ks_employee"
                                                class="form-control new-currency"
                                                value="{{ $formatRupiah($manualBpjsHealthEmployee) }}">

                                        </div>

                                    </div>


                                    {{-- COMPANY --}}

                                    <div class="col-md-4">

                                        <label class="form-label fw-semibold">
                                            Perusahaan
                                        </label>

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                Rp
                                            </span>

                                            <input
                                                type="text"
                                                name="manual_bpjs_company"
                                                class="form-control new-currency"
                                                value="{{ $formatRupiah($manualBpjsCompany) }}">

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                @else

                    <div class="alert alert-secondary">

                        <i class="fas fa-lock me-2"></i>

                        Data Financial, BPJS dan PPh21
                        mengikuti data contract sebelumnya
                        dan tidak dapat diubah oleh role Anda.

                    </div>

                @endif


                {{-- BUTTON --}}

                <div class="d-flex justify-content-end gap-2 mt-4">

                    <button
                        type="button"
                        class="btn btn-outline-secondary js-cancel-new-period">

                        Batal

                    </button>


                    <button
                        type="submit"
                        class="btn btn-success">

                        <i class="fas fa-plus me-1"></i>

                        Simpan Contract Period

                    </button>

                </div>

            </form>

        </div>


        {{-- =========================================================
             HISTORY TABLE
        ========================================================== --}}

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>
                            Contract
                        </th>

                        <th>
                            Periode
                        </th>

                        <th>
                            Jabatan
                        </th>

                        <th>
                            Status
                        </th>

                        <th class="text-end">
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($histories as $item)

                        @php

                            $isCurrent =
                                (string) $currentHistoryId ===
                                (string) $item->id_contract_history;

                            $itemLevel =
                                is_numeric($item->level)
                                    ? (int) $item->level
                                    : null;

                            $itemCanSeeLevel =
                                $isManagerKeuangan ||
                                (
                                    ($isHeadHrd || $isHrd || $isKeuangan) &&
                                    (
                                        $itemLevel === null ||
                                        $itemLevel <= 13
                                    )
                                );

                        @endphp


                        <tr>


                            {{-- CONTRACT --}}

                            <td>

                                <div class="fw-bold">

                                    @if($item->employment_type === 'PKWT')

                                        PKWT
                                        {{ $item->pkwt_sequence ?? '-' }}

                                    @else

                                        {{ $item->employment_type ?? '-' }}

                                    @endif

                                </div>


                                <small class="text-muted">

                                    Level:

                                    @if($itemCanSeeLevel)

                                        {{ $item->level ?? '-' }}

                                    @else

                                        *****

                                    @endif

                                </small>

                            </td>


                            {{-- PERIOD --}}

                            <td>

                                <div>

                                    {{ $item->start_date
                                        ? \Carbon\Carbon::parse($item->start_date)->format('d M Y')
                                        : '-'
                                    }}

                                </div>

                                <div class="text-muted small">

                                    s/d

                                    {{ $item->end_date
                                        ? \Carbon\Carbon::parse($item->end_date)->format('d M Y')
                                        : 'Sekarang'
                                    }}

                                </div>

                            </td>


                            {{-- JOB --}}

                            <td>

                                <div class="fw-semibold">
                                    {{ $item->job_title ?? '-' }}
                                </div>

                                <small class="text-muted">
                                    {{ $item->department ?? '-' }}
                                </small>

                            </td>


                            {{-- STATUS --}}

                            <td>

                                @if($isCurrent)

                                    <span class="badge bg-success">

                                        <i class="fas fa-check-circle me-1"></i>

                                        CURRENT

                                    </span>

                                @else

                                    <span class="badge bg-secondary">

                                        <i class="fas fa-lock me-1"></i>

                                        HISTORY

                                    </span>

                                @endif

                            </td>


                            {{-- ACTION --}}

                            <td class="text-end">

                                @if($isCurrent)

                                    <span class="badge bg-primary me-2">
                                        Aktif
                                    </span>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        onclick="window.scrollTo({top:0,behavior:'smooth'})">

                                        <i class="fas fa-edit me-1"></i>

                                        Edit

                                    </button>

                                @else

                                    <a
                                        href="{{ route(
                                            'contracts.local.period.show',
                                            [
                                                'employee' => $employee->uuid,
                                                'history'  => $item->uuid
                                                    ?? $item->id_contract_history,
                                            ]
                                        ) }}"
                                        class="btn btn-sm btn-outline-secondary">

                                        <i class="fas fa-eye me-1"></i>

                                        Detail

                                    </a>

                                    <span class="badge bg-light text-muted ms-1">

                                        <i class="fas fa-lock"></i>

                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="text-center py-5 text-muted">

                                <i class="fas fa-folder-open fa-2x mb-3"></i>

                                <div>
                                    Belum ada contract history.
                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>


{{-- =================================================================
JAVASCRIPT
================================================================== --}}

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {


    /*
    |--------------------------------------------------------------------------
    | CLEAN NUMBER
    |--------------------------------------------------------------------------
    */

    function cleanNumber(value) {

        return String(value ?? '')
            .replace(/[^\d]/g, '');

    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT RUPIAH
    |--------------------------------------------------------------------------
    */

    function formatRupiah(value) {

        const number = cleanNumber(value);

        if (!number) {
            return '';
        }

        return new Intl.NumberFormat('id-ID')
            .format(Number(number));

    }


    /*
    |--------------------------------------------------------------------------
    | CURRENCY INPUT
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll(
        '.currency-input, .new-currency, .currency-display'
    ).forEach(function (input) {

        /*
        |--------------------------------------------------------------------------
        | FORMAT NILAI AWAL
        |--------------------------------------------------------------------------
        |
        | Nilai dari database decimal seperti 5000000.00 sudah diformat oleh
        | Blade menjadi 5.000.000 sebelum masuk ke JavaScript.
        | Jangan mengirim nilai raw decimal 5000000.00 ke formatRupiah(),
        | karena cleanNumber() akan membacanya sebagai 500000000.
        |
        */
        if (input.value) {
            input.value = formatRupiah(input.value);
        }


        input.addEventListener('input', function () {

            if (this.readOnly && this.id !== 'basic_salary_display') {
                return;
            }

            this.value =
                formatRupiah(this.value);

        });


        input.addEventListener('blur', function () {

            if (this.value) {

                this.value =
                    formatRupiah(this.value);

            }

        });


        /*
        |--------------------------------------------------------------------------
        | FORMAT SAAT HALAMAN EDIT DIBUKA
        |--------------------------------------------------------------------------
        |
        | Nilai dari database biasanya berupa:
        |   5000000
        |
        | Saat halaman Edit dibuka, langsung tampil sebagai:
        |   5.000.000
        |
        | Jadi user tidak perlu mengetik ulang angka terlebih dahulu.
        */

        if (input.value) {

            input.value =
                formatRupiah(input.value);

        }

    });


    /*
    |--------------------------------------------------------------------------
    | TOGGLE BUTTON SYSTEM
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | ON:
    |   <input name="field" value="1">
    |
    | OFF:
    |   field DIHAPUS dari form
    |
    | Dengan cara ini:
    |
    | $request->has('field')
    |
    | tetap bekerja.
    |
    */

    function createToggleInput(
        button,
        state
    ) {

        const field =
            button.dataset.toggleField;

        if (!field) {
            return;
        }


        const form =
            button.closest('form');

        if (!form) {
            return;
        }


        let input =
            form.querySelector(
                'input[data-toggle-hidden="' +
                field +
                '"]'
            );


        if (state) {

            if (!input) {

                input =
                    document.createElement('input');

                input.type = 'hidden';

                input.name = field;

                input.value = '1';

                input.dataset.toggleHidden =
                    field;

                form.appendChild(input);

            } else {

                input.value = '1';

            }

        } else {

            if (input) {

                input.remove();

            }

        }

    }


    function updateToggleVisual(
        button,
        state
    ) {

        const icon =
            button.querySelector('.toggle-icon i');

        const label =
            button.querySelector('.toggle-label');


        button.dataset.toggleState =
            state ? '1' : '0';


        button.classList.toggle(
            'toggle-on',
            state
        );

        button.classList.toggle(
            'toggle-off',
            !state
        );


        if (icon) {

            icon.className =
                state
                    ? 'fas fa-toggle-on'
                    : 'fas fa-toggle-off';

        }


        if (label) {

            label.textContent =
                state
                    ? 'ON'
                    : 'OFF';

        }


        const description =
            button
                .closest('.rhuekamp-toggle-wrapper')
                ?.querySelector(
                    '.rhuekamp-toggle-description'
                );


        if (description) {

            if (
                button.dataset.toggleField ===
                'is_bpjstk_active'
            ) {

                description.textContent =
                    state
                        ? 'BPJS TK akan dihitung dalam payroll.'
                        : 'BPJS TK tidak dihitung dalam payroll.';

            }


            if (
                button.dataset.toggleField ===
                'is_bpjs_health_active'
            ) {

                description.textContent =
                    state
                        ? 'BPJS Kesehatan akan dihitung dalam payroll.'
                        : 'BPJS Kesehatan tidak dihitung dalam payroll.';

            }


            if (
                button.dataset.toggleField ===
                'use_manual_bpjs'
            ) {

                description.textContent =
                    state
                        ? 'Menggunakan nominal BPJS manual.'
                        : 'Menggunakan perhitungan otomatis.';

            }

        }

    }


    function initializeToggle(button) {

        const state =
            button.dataset.toggleState === '1';


        /*
        |--------------------------------------------------------------
        | Pastikan input sesuai state awal.
        |--------------------------------------------------------------
        */

        createToggleInput(
            button,
            state
        );


        updateToggleVisual(
            button,
            state
        );


        button.addEventListener(
            'click',
            function () {

                const currentState =
                    this.dataset.toggleState === '1';

                const newState =
                    !currentState;


                createToggleInput(
                    this,
                    newState
                );


                updateToggleVisual(
                    this,
                    newState
                );


                /*
                |----------------------------------------------------------
                | Manual BPJS
                |----------------------------------------------------------
                */

                if (
                    this.dataset.toggleField ===
                    'use_manual_bpjs'
                ) {

                    const form =
                        this.closest('form');

                    if (!form) {
                        return;
                    }


                    const isNewPeriod =
                        form.id === 'newPeriodForm';


                    const box =
                        form.querySelector(
                            isNewPeriod
                                ? '#newManualBpjsBox'
                                : '#manualBpjsBox'
                        );


                    if (box) {

                        box.style.display =
                            newState
                                ? ''
                                : 'none';

                    }

                }

            }
        );

    }


    document
        .querySelectorAll(
            '.rhuekamp-toggle'
        )
        .forEach(function (button) {

            initializeToggle(button);

        });


    /*
    |--------------------------------------------------------------------------
    | EMPLOYMENT STATUS
    |--------------------------------------------------------------------------
    |
    | UI mengikuti Excel:
    | Tetap / Kontrak I / ... / Kontrak VII.
    | Database tetap menerima:
    | employment_type = PKWTT / PKWT / termination
    | pkwt_sequence  = 1..7 / null
    |
    */

    function parseEmploymentUiValue(value) {
        const raw = String(value || '');

        if (raw.startsWith('PKWT|')) {
            const sequence = Number(raw.split('|')[1]) || 1;
            return {
                employmentType: 'PKWT',
                sequence,
            };
        }

        return {
            employmentType: raw,
            sequence: null,
        };
    }

    function bindEmploymentStatus(
        selectId,
        hiddenTypeId,
        hiddenSequenceId,
        sequenceBoxId,
        sequenceLabelId
    ) {
        const select = document.getElementById(selectId);
        const hiddenType = document.getElementById(hiddenTypeId);
        const hiddenSequence = document.getElementById(hiddenSequenceId);
        const sequenceBox = document.getElementById(sequenceBoxId);
        const sequenceLabel = document.getElementById(sequenceLabelId);

        if (!select || !hiddenType || !hiddenSequence) {
            return;
        }

        function sync() {
            const state = parseEmploymentUiValue(select.value);

            hiddenType.value = state.employmentType;
            hiddenSequence.value =
                state.employmentType === 'PKWT'
                    ? state.sequence
                    : '';

            if (sequenceBox) {
                sequenceBox.style.display =
                    state.employmentType === 'PKWT'
                        ? ''
                        : 'none';
            }

            if (sequenceLabel) {
                sequenceLabel.textContent =
                    state.employmentType === 'PKWT'
                        ? state.sequence
                        : '-';
            }

            // Termination UI is only present on the current contract form.
            if (sequenceBoxId === 'pkwtSequenceBox') {
                const terminationBox =
                    document.getElementById('terminationBox');

                if (terminationBox) {
                    const terminationTypes = [
                        'PHK',
                        'Resign',
                        'Pensiun',
                        'End_Contract',
                    ];

                    terminationBox.style.display =
                        terminationTypes.includes(state.employmentType)
                            ? ''
                            : 'none';
                }
            }
        }

        select.addEventListener('change', sync);
        sync();
    }

    bindEmploymentStatus(
        'employment_status_ui',
        'employment_type',
        'pkwt_sequence',
        'pkwtSequenceBox',
        'pkwtSequenceLabel'
    );

    bindEmploymentStatus(
        'new_employment_status_ui',
        'new_employment_type',
        'new_pkwt_sequence',
        'newPkwtSequenceBox',
        'newPkwtSequenceLabel'
    );


    /*
    |--------------------------------------------------------------------------
    | CURRENT FINANCIAL
    |--------------------------------------------------------------------------
    |
    | Tidak ada hidden basic_salary kedua.
    | Input name=basic_salary adalah input yang langsung dikirim tanpa
    | formatter JavaScript. Controller membersihkan nominal di server.
    | Server tetap menjadi sumber kebenaran untuk allowance.
    */

    const basicSalary =
        document.getElementById(
            'basic_salary'
        );

    const allowance =
        document.getElementById(
            'allowance'
        );

    const level =
        document.getElementById(
            'level'
        );


    function syncCurrentAllowance() {

        if (
            !basicSalary ||
            !allowance ||
            !level
        ) {
            return;
        }

        const salary =
            Number(
                cleanNumber(
                    basicSalary.value
                )
            ) || 0;

        const levelValue =
            Number(
                level.value
            ) || 0;

        const result =
            salary *
            (levelValue * 0.02);

        allowance.value =
            result > 0
                ? formatRupiah(
                    Math.round(result)
                )
                : '';

    }


    /*
    |--------------------------------------------------------------------------
    | BASIC SALARY INPUT
    |--------------------------------------------------------------------------
    */

    basicSalary?.addEventListener(
        'input',
        syncCurrentAllowance
    );


    basicSalary?.addEventListener(
        'blur',
        syncCurrentAllowance
    );


    level?.addEventListener(
        'input',
        syncCurrentAllowance
    );


    /*
    |--------------------------------------------------------------------------
    | PENTING
    |--------------------------------------------------------------------------
    |
    | Jangan menjalankan syncCurrentAllowance() saat page load.
    | Data allowance existing harus tetap ditampilkan apa adanya.
    | Perhitungan baru dilakukan ketika salary / level berubah.
    */

    /*
    |--------------------------------------------------------------------------
    | NEW PERIOD
    |--------------------------------------------------------------------------
    */

    const btnAddContractPeriod =
        document.getElementById(
            'btnAddContractPeriod'
        );

    const newPeriodWrapper =
        document.getElementById(
            'newPeriodWrapper'
        );


    function showNewPeriod() {

        if (!newPeriodWrapper) {
            return;
        }


        newPeriodWrapper.style.display = '';


        newPeriodWrapper.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

    }


    function hideNewPeriod() {

        if (!newPeriodWrapper) {
            return;
        }


        newPeriodWrapper.style.display =
            'none';

    }


    btnAddContractPeriod?.addEventListener(
        'click',
        showNewPeriod
    );


    document.querySelectorAll(
        '.js-cancel-new-period'
    ).forEach(function (button) {

        button.addEventListener(
            'click',
            hideNewPeriod
        );

    });


    /*
    |--------------------------------------------------------------------------
    | NEW PERIOD ALLOWANCE
    |--------------------------------------------------------------------------
    */

    const newSalary =
        document.getElementById(
            'new_basic_salary'
        );

    const newAllowance =
        document.getElementById(
            'new_allowance'
        );

    const newLevel =
        document.getElementById(
            'new_level'
        );


    function calculateNewAllowance() {

        if (
            !newSalary ||
            !newAllowance ||
            !newLevel
        ) {
            return;
        }


        const salary =
            Number(
                cleanNumber(
                    newSalary.value
                )
            ) || 0;


        const levelValue =
            Number(
                newLevel.value
            ) || 0;


        const result =
            salary *
            (levelValue * 0.02);


        newAllowance.value =
            result > 0
                ? formatRupiah(result)
                : '';

    }


    newSalary?.addEventListener(
        'input',
        calculateNewAllowance
    );


    newLevel?.addEventListener(
        'input',
        calculateNewAllowance
    );


    /*
    |--------------------------------------------------------------------------
    | AUTO OPEN NEW PERIOD
    |--------------------------------------------------------------------------
    */

    const urlParams =
        new URLSearchParams(
            window.location.search
        );


    if (
        urlParams.get('open_new_period') === 'true' ||
        urlParams.get('open_new_period') === '1'
    ) {

        showNewPeriod();

    }


    /*
    |--------------------------------------------------------------------------
    | SUBMIT CURRENT CONTRACT
    |--------------------------------------------------------------------------
    |
    | Field nominal BPJS pada form tetap dibersihkan sebelum request.
    | basic_salary sengaja tidak disentuh JavaScript dan dibersihkan server-side
    | oleh controller, sehingga submit pertama mengirim nilai input terakhir.
    */

    const currentForm =
        document.getElementById(
            'currentContractForm'
        );


    currentForm?.addEventListener(
        'submit',
        function () {

            this.querySelectorAll(
                '.currency-input'
            ).forEach(function (input) {

                input.value =
                    cleanNumber(
                        input.value
                    );

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | SUBMIT NEW PERIOD
    |--------------------------------------------------------------------------
    */

    const newPeriodForm =
        document.getElementById(
            'newPeriodForm'
        );


    newPeriodForm?.addEventListener(
        'submit',
        function () {

            this.querySelectorAll(
                '.new-currency'
            ).forEach(function (input) {

                input.value =
                    cleanNumber(input.value);

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | DOUBLE SUBMIT PROTECTION
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll(
        '#currentContractForm, #newPeriodForm'
    ).forEach(function (form) {

        form.addEventListener(
            'submit',
            function () {

                const submitButton =
                    this.querySelector(
                        'button[type="submit"]'
                    );


                if (!submitButton) {
                    return;
                }


                if (
                    submitButton.dataset.submitting === '1'
                ) {

                    return;

                }


                submitButton.dataset.submitting =
                    '1';


                setTimeout(function () {

                    submitButton.disabled = true;

                    submitButton.innerHTML =
                        '<i class="fas fa-spinner fa-spin me-1"></i>' +
                        ' Menyimpan...';

                }, 50);

            }
        );

    });

});

</script>

@endpush

@endsection