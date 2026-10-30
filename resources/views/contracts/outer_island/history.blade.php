@extends('layouts.app')

@section('title', 'Detail Riwayat Kontrak — Outer Island')
@section('page_title', 'Detail Riwayat Kontrak — Outer Island')

@section('content')
@php
    $role = strtolower((string) (auth()->user()->role ?? ''));
    $canEditHistory = in_array($role, ['manager_keuangan', 'super_admin'], true)
        && (bool) ($canEditHistory ?? false);
    $canSeeFinancial = (bool) ($canViewSensitiveData ?? false);

    $level = is_numeric($history->level) ? (int) $history->level : null;
    $canSeeLevelCategory = in_array($role, ['manager_keuangan', 'super_admin'], true)
        || (in_array($role, ['hrd', 'head_hrd', 'kepala_hrd', 'keuangan'], true)
            && ($level === null || $level <= 13));

    $money = fn ($value) => $value === null || $value === ''
        ? '-' : number_format((float) $value, 0, ',', '.');
    $date = fn ($value) => $value
        ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';

    $fields = [
        'Jabatan' => 'job_title',
        'Department' => 'department',
        'Penempatan' => 'placement_area',
        'Kategori' => 'category',
        'Level' => 'level',
        'Status hubungan kerja' => 'employment_type',
        'Urutan PKWT' => 'pkwt_sequence',
        'Urutan Freelance' => 'freelance_sequence',
        'Jenis perubahan' => 'change_type',
        'Alasan perubahan' => 'change_reason',
        'Tanggal mulai' => 'start_date',
        'Tanggal berakhir' => 'end_date',
        'Tanggal keluar' => 'exit_date',
        'Alasan keluar' => 'exit_reason',
        'Fingerprint PIN' => 'fingerprint_pin',
        'NIK Fingerprint' => 'nik_fingerprint',
    ];

    $today = \Carbon\Carbon::today();
    $start = $history->start_date ? \Carbon\Carbon::parse($history->start_date)->startOfDay() : null;
    $end = $history->end_date ? \Carbon\Carbon::parse($history->end_date)->endOfDay() : null;
    $exit = $history->exit_date ? \Carbon\Carbon::parse($history->exit_date)->endOfDay() : null;
    $isScheduled = $start && $start->gt($today);
    $isEnded = ($end && $end->lt($today)) || ($exit && $exit->lt($today));
    $isEffective = $start && $start->lte($today)
        && (!$end || $end->gte($today))
        && (!$exit || $exit->gte($today));
@endphp

<style>
.history-detail-page{--ink:#172033;--muted:#697586;--line:#e3e9f2;color:var(--ink);padding-bottom:2rem}
.history-detail-page .hero{border-radius:20px;padding:1.4rem 1.6rem;color:#fff;background:radial-gradient(circle at 90% 0%,rgba(255,255,255,.22),transparent 28%),linear-gradient(125deg,#17264b,#3157d5);box-shadow:0 14px 35px rgba(31,56,130,.16)}
.history-detail-page .hero .text-muted{color:rgba(255,255,255,.75)!important}
.history-detail-page .pane{height:100%;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 8px 28px rgba(24,39,75,.045);overflow:hidden}
.history-detail-page .pane-header{padding:1rem 1.2rem;border-bottom:1px solid #edf0f6;background:#fbfcff}
.history-detail-page .pane-body{padding:1.2rem}
.history-detail-page .form-label{font-size:.8rem;font-weight:700;color:#475467}
.history-detail-page .form-control,.history-detail-page .form-select{border-color:#d9e1ed;border-radius:10px;min-height:42px}
.history-detail-page .form-control:focus,.history-detail-page .form-select:focus{border-color:#7290ff;box-shadow:0 0 0 .2rem rgba(49,87,213,.12)}
.history-detail-page .readonly-value{min-height:42px;padding:.65rem .8rem;background:#f6f8fc;border:1px solid var(--line);border-radius:10px;white-space:pre-wrap;overflow-wrap:anywhere}
.history-detail-page .metric{padding:1rem;border:1px solid var(--line);border-radius:13px;background:#fff;height:100%}
.history-detail-page .metric small{display:block;color:var(--muted);margin-bottom:.35rem}
.history-detail-page .metric strong{font-size:1.05rem}
.history-detail-page .section-title{font-weight:800;letter-spacing:-.02em}
@media(max-width:767px){.history-detail-page .hero{padding:1.1rem;border-radius:15px}.history-detail-page .pane-body{padding:1rem}}
</style>

<div class="container-fluid py-4 history-detail-page">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger shadow-sm">
            <strong><i class="fas fa-exclamation-triangle me-1"></i> Periksa data berikut:</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="hero mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="text-muted small mb-1">OUTER ISLAND · CONTRACT HISTORY #{{ $history->id_contract_history_outer_island }}</div>
            <h3 class="fw-bold mb-1">{{ $employee->full_name_outer ?? '-' }}</h3>
            <div class="text-muted">NIK: {{ $employee->nik_ktp_outer ?? '-' }}</div>
            <div class="mt-3 d-flex flex-wrap gap-2">
                @if($isScheduled)
                    <span class="badge bg-warning text-dark px-3 py-2"><i class="fas fa-calendar-clock me-1"></i> Terjadwal</span>
                    <span class="badge bg-light text-dark px-3 py-2">Efektif {{ $date($history->start_date) }}</span>
                @elseif($isEffective)
                    <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i> Berlaku hari ini</span>
                @else
                    <span class="badge bg-secondary px-3 py-2"><i class="fas fa-history me-1"></i> Riwayat tersimpan</span>
                @endif
            </div>
        </div>
        <a href="{{ route('contracts.outer_island.edit', $employee->uuid) }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Kontrak
        </a>
    </div>

    @if(!$canEditHistory)
        <div class="alert alert-info border-0 shadow-sm">
            <i class="fas fa-lock me-2"></i><strong>Mode lihat saja.</strong>
            Semua riwayat dapat dilihat sesuai izin data, tetapi hanya Manager Keuangan / Super Admin yang dapat mengoreksi riwayat tersimpan.
        </div>
    @else
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="fas fa-user-shield me-2"></i>
            Anda memiliki hak koreksi riwayat. Perubahan tanggal atau nominal dapat memengaruhi audit dan perhitungan payroll periode tersebut.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="metric"><small>Jenis kontrak</small><strong>{{ $history->employment_type ?? '-' }}</strong></div></div>
        <div class="col-6 col-xl-3"><div class="metric"><small>Level</small><strong>{{ $canSeeLevelCategory ? ($history->level ?? '-') : '*****' }}</strong></div></div>
        <div class="col-6 col-xl-3"><div class="metric"><small>Tanggal mulai</small><strong>{{ $date($history->start_date) }}</strong></div></div>
        <div class="col-6 col-xl-3"><div class="metric"><small>Gaji pokok</small><strong>{{ $canSeeFinancial ? 'Rp '.$money($history->basic_salary) : '*****' }}</strong></div></div>
    </div>

    <form method="POST" action="{{ $canEditHistory ? route('contracts.outer_island.period.update', ['employee' => $employee->uuid, 'history' => $history->getKey()]) : route('contracts.outer_island.period.show', ['employee' => $employee->uuid, 'history' => $history->getKey()]) }}">
        @csrf
        @if($canEditHistory) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="pane">
                    <div class="pane-header">
                        <h5 class="section-title mb-1">Penempatan & Periode</h5>
                        <small class="text-muted">Data pekerjaan dan tanggal efektif riwayat.</small>
                    </div>
                    <div class="pane-body">
                        <div class="row g-3">
                            @foreach($fields as $label => $field)
                                @php
                                    $value = old($field, $history->{$field});
                                    $isDate = in_array($field, ['start_date','end_date','exit_date'], true);
                                    $isLong = in_array($field, ['change_reason','exit_reason'], true);
                                    $isRestrictedLevel = in_array($field, ['level','category'], true) && !$canSeeLevelCategory;
                                @endphp
                                <div class="col-md-6">
                                    <label class="form-label">{{ $label }}</label>
                                    @if($canEditHistory)
                                        @if($isLong)
                                            <textarea class="form-control" name="{{ $field }}" rows="2">{{ $value }}</textarea>
                                        @elseif($isDate)
                                            <input class="form-control" type="date" name="{{ $field }}" value="{{ $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '' }}" @required($field === 'start_date')>
                                        @elseif($field === 'employment_type')
                                            <select class="form-select" name="{{ $field }}" required>
                                                @foreach(['PKWT','Freelance','PKWTT','Probation','Internship','PHK','Resign','Pensiun','End_Contract'] as $option)
                                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field === 'level' || $field === 'pkwt_sequence' || $field === 'freelance_sequence')
                                            <input class="form-control" type="number" min="{{ in_array($field, ['pkwt_sequence', 'freelance_sequence'], true) ? 1 : 0 }}" name="{{ $field }}" value="{{ $value }}">
                                        @elseif($field === 'change_type')
                                            <select class="form-select" name="change_type">
                                                <option value="">-</option>
                                                @foreach(['initial_contract'=>'Kontrak pertama','contract_renewal'=>'Perpanjangan kontrak','level_up'=>'Naik level','level_down'=>'Turun level','salary_adjustment'=>'Penyesuaian gaji','job_promotion'=>'Promosi jabatan','job_demotion'=>'Penurunan jabatan','position_change'=>'Perubahan penempatan'] as $type => $typeLabel)
                                                    <option value="{{ $type }}" @selected($value === $type)>{{ $typeLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input class="form-control" name="{{ $field }}" value="{{ $value }}">
                                        @endif
                                    @else
                                        <div class="readonly-value">{{ $isRestrictedLevel ? '*****' : ($isDate ? $date($value) : ($value === null || $value === '' ? '-' : $value)) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="pane mb-4">
                    <div class="pane-header">
                        <h5 class="section-title mb-1">Gaji, PTKP & BPJS</h5>
                        <small class="text-muted">Nilai ditampilkan berdasarkan hak akses akun.</small>
                    </div>
                    <div class="pane-body">
                        @foreach(['Gaji pokok'=>'basic_salary','Tunjangan'=>'allowance','PTKP / PPh21'=>'ptkp_status'] as $label => $field)
                            <div class="mb-3">
                                <label class="form-label">{{ $label }}</label>
                                @if(!$canSeeFinancial)
                                    <div class="readonly-value">*****</div>
                                @elseif($canEditHistory && in_array($field, ['basic_salary','allowance'], true))
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input class="form-control" type="number" min="0" step="0.01" name="{{ $field }}" value="{{ old($field, $history->{$field}) }}" required>
                                    </div>
                                @elseif($canEditHistory)
                                    <input class="form-control" name="{{ $field }}" value="{{ old($field, $history->{$field}) }}">
                                @else
                                    <div class="readonly-value">{{ in_array($field, ['basic_salary','allowance'], true) ? 'Rp '.$money($history->{$field}) : ($history->{$field} ?? '-') }}</div>
                                @endif
                            </div>
                        @endforeach

                        <div class="row g-3">
                            @foreach(['BPJS Ketenagakerjaan'=>'is_bpjstk_active','BPJS Kesehatan'=>'is_bpjs_health_active','Override BPJS manual'=>'use_manual_bpjs'] as $label => $field)
                                <div class="col-md-4">
                                    <label class="form-label">{{ $label }}</label>
                                    @if(!$canSeeFinancial)
                                        <div class="readonly-value">*****</div>
                                    @elseif($canEditHistory)
                                        <select class="form-select" name="{{ $field }}">
                                            <option value="0" @selected(!(bool) old($field, $history->{$field}))>OFF</option>
                                            <option value="1" @selected((bool) old($field, $history->{$field}))>ON</option>
                                        </select>
                                    @else
                                        <div class="readonly-value">{{ (bool) $history->{$field} ? 'ON' : 'OFF' }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @foreach(['BPJS TK karyawan'=>'manual_bpjs_tk_employee','BPJS Kesehatan karyawan'=>'manual_bpjs_ks_employee','BPJS perusahaan'=>'manual_bpjs_company'] as $label => $field)
                            <div class="mt-3">
                                <label class="form-label">{{ $label }}</label>
                                @if(!$canSeeFinancial)
                                    <div class="readonly-value">*****</div>
                                @elseif($canEditHistory)
                                    <div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0" step="0.01" name="{{ $field }}" value="{{ old($field, $history->{$field}) }}"></div>
                                @else
                                    <div class="readonly-value">Rp {{ $money($history->{$field}) }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($canEditHistory)
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('contracts.outer_island.edit', $employee->uuid) }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Koreksi Riwayat</button>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>
@endsection
