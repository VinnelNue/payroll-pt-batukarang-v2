@extends('layouts.app')

@section('title', 'Detail Riwayat Kontrak')
@section('page_title', 'Detail Riwayat Kontrak')

@section('content')
@php
    $role = strtolower((string) (auth()->user()->role ?? ''));
    // Hak edit tetap ditentukan controller dan dibatasi lagi pada role yang diizinkan.
    $canEditHistory = in_array($role, ['manager_keuangan', 'super_admin'], true)
        && (bool) ($canEditHistory ?? false);
    $canSeeFinancial = (bool) ($canViewSensitiveData ?? false);

    $fmt = fn ($value) => $value === null || $value === ''
        ? '-'
        : number_format((float) $value, 0, ',', '.');
    $date = fn ($value) => $value
        ? \Carbon\Carbon::parse($value)->format('d/m/Y')
        : '-';

    $today = \Carbon\Carbon::today();
    $start = $history->start_date ? \Carbon\Carbon::parse($history->start_date)->startOfDay() : null;
    $end = $history->end_date ? \Carbon\Carbon::parse($history->end_date)->startOfDay() : null;

    if ($start && $start->greaterThan($today)) {
        $effectiveStatus = 'Terjadwal';
        $effectiveStatusClass = 'status-scheduled';
        $effectiveStatusIcon = 'fa-calendar-clock';
    } elseif ($end && $end->lessThan($today)) {
        $effectiveStatus = 'Selesai';
        $effectiveStatusClass = 'status-ended';
        $effectiveStatusIcon = 'fa-circle-check';
    } else {
        $effectiveStatus = 'Berlaku berdasarkan tanggal';
        $effectiveStatusClass = 'status-active';
        $effectiveStatusIcon = 'fa-circle-check';
    }

    $fields = [
        'Jabatan' => 'job_title',
        'Department' => 'department',
        'Penempatan' => 'placement_area',
        'Kategori' => 'category',
        'Level' => 'level',
        'Status kerja' => 'employment_type',
        'Urutan PKWT' => 'pkwt_sequence',
        'Jenis perubahan' => 'change_type',
        'Alasan perubahan' => 'change_reason',
        'Tanggal mulai' => 'start_date',
        'Tanggal akhir' => 'end_date',
        'Tanggal keluar' => 'exit_date',
        'Alasan keluar' => 'exit_reason',
        'Fingerprint PIN' => 'fingerprint_pin',
        'NIK Fingerprint' => 'nik_fingerprint',
        'PTKP' => 'ptkp_status',
        'Kategori TER' => 'ter_category',
    ];
@endphp

<style>
    .contract-history-page {
        --ch-ink: #172033;
        --ch-muted: #6b7280;
        --ch-border: #e5eaf2;
        --ch-primary: #3157d5;
        color: var(--ch-ink);
        padding-bottom: 2.5rem;
    }
    .contract-history-page .history-hero {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        color: #fff;
        border-radius: 22px;
        padding: clamp(1.25rem, 3vw, 2rem);
        background: radial-gradient(circle at 88% 0%, rgba(144,169,255,.4), transparent 28%),
                    linear-gradient(125deg, #17264b 0%, #244080 52%, #3157d5 100%);
        box-shadow: 0 15px 38px rgba(32,56,125,.18);
    }
    .contract-history-page .history-hero::after {
        content: "";
        position: absolute;
        z-index: -1;
        width: 230px;
        height: 230px;
        right: -75px;
        bottom: -150px;
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 50%;
        box-shadow: 0 0 0 24px rgba(255,255,255,.035), 0 0 0 48px rgba(255,255,255,.025);
    }
    .contract-history-page .hero-kicker {
        color: rgba(255,255,255,.72);
        text-transform: uppercase;
        letter-spacing: .09em;
        font-size: .72rem;
        font-weight: 800;
    }
    .contract-history-page .hero-name {
        color: #fff;
        font-size: clamp(1.3rem, 2.4vw, 1.9rem);
        font-weight: 800;
        letter-spacing: -.035em;
        margin: .35rem 0 .45rem;
    }
    .contract-history-page .hero-meta { color: rgba(255,255,255,.78); font-size: .9rem; }
    .contract-history-page .hero-id {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .45rem .7rem; border: 1px solid rgba(255,255,255,.22);
        border-radius: 999px; background: rgba(255,255,255,.09);
        color: #fff; font-size: .78rem; font-weight: 700;
    }
    .contract-history-page .hero-back {
        border-color: rgba(255,255,255,.48); color: #fff;
        border-radius: 11px; font-weight: 700; padding: .65rem .9rem;
        background: rgba(255,255,255,.07);
    }
    .contract-history-page .hero-back:hover { background: #fff; color: #203674; }
    .contract-history-page .history-panel {
        border: 1px solid var(--ch-border); border-radius: 18px;
        background: #fff; box-shadow: 0 9px 28px rgba(25,39,73,.055);
        overflow: hidden;
    }
    .contract-history-page .panel-heading {
        padding: 1.15rem 1.35rem; border-bottom: 1px solid #edf0f6;
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .8rem;
    }
    .contract-history-page .panel-title { margin: 0; font-size: 1.03rem; font-weight: 800; letter-spacing: -.02em; }
    .contract-history-page .panel-subtitle { color: var(--ch-muted); font-size: .82rem; margin-top: .2rem; }
    .contract-history-page .panel-body { padding: clamp(1rem, 2.5vw, 1.5rem); }
    .contract-history-page .status-pill {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .45rem .75rem; border-radius: 999px; font-size: .76rem; font-weight: 800;
        white-space: nowrap;
    }
    .contract-history-page .status-active { color: #116544; background: #e7f7ef; border: 1px solid #c9eedb; }
    .contract-history-page .status-scheduled { color: #8a5200; background: #fff4dc; border: 1px solid #ffe1a3; }
    .contract-history-page .status-ended { color: #596273; background: #f0f2f6; border: 1px solid #e0e5ed; }
    .contract-history-page .summary-tile {
        height: 100%; padding: 1rem 1.1rem; border: 1px solid var(--ch-border);
        border-radius: 14px; background: linear-gradient(180deg, #fff, #fafbff);
    }
    .contract-history-page .summary-label { color: var(--ch-muted); font-size: .76rem; font-weight: 700; margin-bottom: .45rem; }
    .contract-history-page .summary-value { font-size: 1.2rem; font-weight: 850; letter-spacing: -.03em; overflow-wrap: anywhere; }
    .contract-history-page .summary-caption { color: var(--ch-muted); font-size: .76rem; margin-top: .2rem; }
    .contract-history-page .form-label { color: #39445a; font-size: .82rem; font-weight: 750; margin-bottom: .45rem; }
    .contract-history-page .form-control,
    .contract-history-page .form-select,
    .contract-history-page .input-group-text {
        min-height: 44px; border-color: #dce3ee; border-radius: 10px; font-size: .91rem;
    }
    .contract-history-page textarea.form-control { min-height: 92px; }
    .contract-history-page .form-control:focus,
    .contract-history-page .form-select:focus { border-color: #7893f6; box-shadow: 0 0 0 .22rem rgba(49,87,213,.12); }
    .contract-history-page .input-group-text { background: #f3f6fb; color: #4b5872; font-weight: 800; }
    .contract-history-page .readonly-value {
        min-height: 44px; padding: .65rem .8rem; border: 1px solid #e2e7ef;
        border-radius: 10px; background: #f7f9fc; color: #283449;
        overflow-wrap: anywhere; white-space: pre-wrap; font-size: .9rem;
    }
    .contract-history-page .field-section-title {
        display: flex; align-items: center; gap: .55rem; font-size: .82rem;
        font-weight: 850; color: #33415d; text-transform: uppercase; letter-spacing: .055em;
        margin: .2rem 0 .1rem;
    }
    .contract-history-page .field-section-title i { color: var(--ch-primary); }
    .contract-history-page .panel-footer {
        display: flex; justify-content: space-between; flex-wrap: wrap; gap: .75rem;
        align-items: center; padding: 1rem 1.35rem; border-top: 1px solid #edf0f6; background: #fbfcff;
    }
    .contract-history-page .btn { border-radius: 10px; font-weight: 750; padding: .65rem .95rem; }
    .contract-history-page .btn-primary { background: linear-gradient(135deg, #3157d5, #2445b8); border-color: #3157d5; }
    .contract-history-page .security-note { border: 1px solid #e3e9f3; border-radius: 13px; padding: .9rem 1rem; background: #f8faff; color: #4d5a70; font-size: .88rem; }
    .contract-history-page .alert { border-radius: 13px; }
    @media (max-width: 767.98px) {
        .contract-history-page .history-hero { border-radius: 17px; }
        .contract-history-page .panel-heading, .contract-history-page .panel-footer { padding: 1rem; }
    }
</style>

<div class="container-fluid py-4 contract-history-page">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger shadow-sm" role="alert">
            <div class="fw-bold mb-2"><i class="fas fa-triangle-exclamation me-2"></i>Periksa data berikut</div>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="history-hero mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-4">
            <div>
                <div class="hero-kicker"><i class="fas fa-file-signature me-2"></i>Manajemen Kontrak Local</div>
                <h2 class="hero-name">{{ $employee->full_name ?? $employee->name ?? '-' }}</h2>
                <div class="hero-meta mb-3">NIK: {{ $employee->nik_ktp ?? '-' }}</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="hero-id"><i class="fas fa-hashtag"></i>History #{{ $history->id_contract_history }}</span>
                    <span class="hero-id"><i class="fas fa-calendar-day"></i>Mulai {{ $date($history->start_date) }}</span>
                    <span class="status-pill {{ $effectiveStatusClass }}"><i class="fas {{ $effectiveStatusIcon }}"></i>{{ $effectiveStatus }}</span>
                </div>
            </div>
            <a href="{{ route('contracts.local.edit', $employee->uuid) }}" class="btn hero-back"><i class="fas fa-arrow-left me-2"></i>Kembali ke Kontrak</a>
        </div>
    </section>

    @if (!$canEditHistory)
        <div class="alert alert-info shadow-sm"><i class="fas fa-lock me-2"></i><strong>Mode lihat saja.</strong> Anda dapat melihat informasi riwayat sesuai hak akses, tetapi tidak dapat menyimpannya. Pengeditan riwayat hanya tersedia untuk Manager Keuangan/Super Admin yang mendapat izin dari controller.</div>
    @else
        <div class="alert alert-warning shadow-sm"><i class="fas fa-user-shield me-2"></i><strong>Akses pengelolaan riwayat aktif.</strong> Koreksi riwayat dapat memengaruhi perhitungan payroll historis. Pastikan tanggal efektif dan nominal sudah benar sebelum menyimpan.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="summary-tile"><div class="summary-label"><i class="fas fa-briefcase me-1"></i>Jabatan</div><div class="summary-value">{{ $history->job_title ?: '-' }}</div><div class="summary-caption">{{ $history->department ?: 'Department belum diisi' }}</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="summary-tile"><div class="summary-label"><i class="fas fa-layer-group me-1"></i>Level / Kategori</div><div class="summary-value">{{ $history->level ?? '-' }} <span class="text-muted">/</span> {{ $history->category ?? '-' }}</div><div class="summary-caption">Status: {{ $history->employment_type ?: '-' }}</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="summary-tile"><div class="summary-label"><i class="fas fa-calendar-alt me-1"></i>Periode</div><div class="summary-value" style="font-size:1rem">{{ $date($history->start_date) }} – {{ $date($history->end_date) }}</div><div class="summary-caption">Tanggal keluar: {{ $date($history->exit_date) }}</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="summary-tile"><div class="summary-label"><i class="fas fa-wallet me-1"></i>Gaji pokok</div><div class="summary-value">{{ $canSeeFinancial ? 'Rp '. $fmt($history->basic_salary) : '*****' }}</div><div class="summary-caption">Tunjangan: {{ $canSeeFinancial ? 'Rp '.$fmt($history->allowance) : '*****' }}</div></div></div>
    </div>

    <form method="POST" action="{{ $canEditHistory ? route('contracts.local.period.update', ['employee' => $employee->uuid, 'history' => $history->getKey()]) : '#' }}">
        @csrf
        @if ($canEditHistory) @method('PUT') @endif
        <section class="history-panel mb-4">
            <div class="panel-heading">
                <div><h3 class="panel-title"><i class="fas fa-clipboard-list text-primary me-2"></i>Data Riwayat Kontrak</h3><div class="panel-subtitle">ID record: {{ $history->id_contract_history }} · Data yang disimpan untuk periode ini</div></div>
                <span class="badge rounded-pill {{ $isCurrent ? 'bg-primary' : 'bg-secondary' }} px-3 py-2">{{ $isCurrent ? 'Terhubung ke master' : 'Riwayat tersimpan' }}</span>
            </div>
            <div class="panel-body">
                <div class="field-section-title mb-3"><i class="fas fa-user-tie"></i>Jabatan & status kerja</div>
                <div class="row g-3">
                    @foreach ($fields as $label => $field)
                        @php
                            $value = old($field, $history->{$field});
                            $isDate = in_array($field, ['start_date', 'end_date', 'exit_date'], true);
                            $isLong = in_array($field, ['change_reason', 'exit_reason'], true);
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label">{{ $label }}</label>
                            @if ($canEditHistory)
                                @if ($isLong)
                                    <textarea class="form-control" name="{{ $field }}" rows="2">{{ $value }}</textarea>
                                @elseif ($isDate)
                                    <input class="form-control" type="date" name="{{ $field }}" value="{{ $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '' }}" {{ $field === 'start_date' ? 'required' : '' }}>
                                @elseif ($field === 'employment_type')
                                    <select class="form-select" name="employment_type" required>
                                        @foreach (['PKWT', 'PKWTT', 'Probation', 'Internship', 'PHK', 'Resign', 'Pensiun', 'End_Contract'] as $opt)
                                            <option value="{{ $opt }}" @selected($value === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field === 'category')
                                    <input class="form-control" name="category" maxlength="10" value="{{ $value }}">
                                @elseif ($field === 'level' || $field === 'pkwt_sequence')
                                    <input class="form-control" type="number" min="1" {{ $field === 'pkwt_sequence' ? 'max=7' : '' }} name="{{ $field }}" value="{{ $value }}">
                                @elseif ($field === 'change_type')
                                    <input class="form-control" name="change_type" value="{{ $value }}">
                                @else
                                    <input class="form-control" name="{{ $field }}" value="{{ $value }}" {{ $field === 'job_title' ? 'required' : '' }}>
                                @endif
                            @else
                                <div class="readonly-value">{{ $isDate ? $date($value) : ($value === null || $value === '' ? '-' : $value) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">
                <div class="field-section-title mb-3"><i class="fas fa-money-bill-wave"></i>Gaji & tunjangan</div>
                <div class="row g-3">
                    @foreach (['Gaji pokok' => 'basic_salary', 'Tunjangan' => 'allowance'] as $label => $field)
                        <div class="col-md-6"><label class="form-label">{{ $label }}</label>
                            @if (!$canSeeFinancial)
                                <div class="readonly-value">*****</div>
                            @elseif ($canEditHistory)
                                <div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" name="{{ $field }}" min="0" step="0.01" required value="{{ old($field, $history->{$field}) }}"></div>
                            @else
                                <div class="readonly-value">Rp {{ $fmt($history->{$field}) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">
                <div class="field-section-title mb-3"><i class="fas fa-shield-halved"></i>BPJS & pengaturan iuran</div>
                <div class="row g-3 mb-4">
                    @foreach (['BPJS Ketenagakerjaan' => 'is_bpjstk_active', 'BPJS Kesehatan' => 'is_bpjs_health_active', 'Override BPJS manual' => 'use_manual_bpjs'] as $label => $field)
                        <div class="col-md-4"><label class="form-label">{{ $label }}</label>
                            @if ($canEditHistory)
                                <select class="form-select" name="{{ $field }}"><option value="0" @selected(!(bool) old($field, $history->{$field}))>OFF</option><option value="1" @selected((bool) old($field, $history->{$field}))>ON</option></select>
                            @else
                                <div class="readonly-value">{{ $canSeeFinancial ? ((bool) $history->{$field} ? 'ON' : 'OFF') : '*****' }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="row g-3">
                    @foreach (['BPJS TK karyawan' => 'manual_bpjs_tk_employee', 'BPJS Kesehatan karyawan' => 'manual_bpjs_ks_employee', 'BPJS perusahaan' => 'manual_bpjs_company'] as $label => $field)
                        <div class="col-md-4"><label class="form-label">{{ $label }}</label>
                            @if (!$canSeeFinancial)
                                <div class="readonly-value">*****</div>
                            @elseif ($canEditHistory)
                                <div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" name="{{ $field }}" step="0.01" min="0" value="{{ old($field, $history->{$field}) }}"></div>
                            @else
                                <div class="readonly-value">Rp {{ $fmt($history->{$field}) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($canEditHistory)
                <div class="panel-footer"><div class="small text-muted"><i class="fas fa-circle-info me-1"></i>Pastikan rentang tanggal tidak bertumpang tindih.</div><div class="d-flex flex-wrap gap-2"><a href="{{ route('contracts.local.edit', $employee->uuid) }}" class="btn btn-outline-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Perubahan History</button></div></div>
            @endif
        </section>
    </form>

    <div class="security-note"><i class="fas fa-circle-info me-2 text-primary"></i>Koreksi riwayat dapat memengaruhi payroll pada periode yang memakai tanggal dan nominal tersebut. Hak edit juga harus divalidasi di controller; tampilan ini bukan pengganti pemeriksaan izin di server.</div>
</div>
@endsection
