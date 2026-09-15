@extends('layouts.app')

@section('title', 'PPh 21 & BPJS Master')
@section('page_title', 'Master Parameter PPh 21 TER & BPJS')

@section('content')
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <!-- HEADER TITLE -->
    <div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
        <div>
            <h4 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-calculator text-primary me-2"></i> Parameter Acuan PPh 21 TER & BPJS
            </h4>
            <small class="text-muted">Konfigurasi iuran BPJS & acuan hukum Tarif Efektif Rata-Rata (TER) PPh 21 PP 58/2023.</small>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm px-3 py-2 rounded-3 fw-bold" data-bs-toggle="modal" data-bs-target="#editBpjsModal">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Parameter BPJS
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- ROW 1: BPJS CARDS (DYNAMIC) -->
    <h6 class="fw-bold text-dark mb-3">
        <i class="fa-solid fa-shield-halved text-warning me-2"></i> Iuran BPJS Ketenagakerjaan & Kesehatan (Dapat Disesuaikan)
    </h6>
    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark fs-6">BPJS Ketenagakerjaan (JHT)</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1">Aktif</span>
                </div>
                <div class="p-2 bg-white rounded-3 border text-center mt-3">
                    <small class="text-muted d-block small">Potongan Karyawan</small>
                    <span class="fs-4 fw-bolder text-danger">{{ $bpjsSettings['bpjs_tk_rate'] }}%</span>
                </div>
                <small class="text-muted d-block mt-3">
                    <i class="fa-solid fa-circle-info me-1 text-info"></i> Dasar Pengenaan: <strong>Gaji Pokok Kontrak</strong>
                </small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="p-3 rounded-4 bg-light border border-light-subtle h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark fs-6">BPJS Kesehatan (JKN)</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1">Aktif</span>
                </div>
                <div class="p-2 bg-white rounded-3 border text-center mt-3">
                    <small class="text-muted d-block small">Potongan Karyawan</small>
                    <span class="fs-4 fw-bolder text-danger">{{ $bpjsSettings['bpjs_ks_rate'] }}%</span>
                </div>
                <small class="text-muted d-block mt-3">
                    <i class="fa-solid fa-circle-info me-1 text-info"></i> Max Cap Gaji: <strong>Rp {{ number_format($bpjsSettings['bpjs_ks_cap'], 0, ',', '.') }}</strong>
                </small>
            </div>
        </div>
    </div>

    <!-- ROW 2: PPH 21 TER BRACKETS (READ ONLY STATIS HUKUM) -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-dark m-0">
            <i class="fa-solid fa-receipt text-danger me-2"></i> Tabel TER PPh 21 (PP No. 58/2023 & PMK 168/2023)
        </h6>
        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3"><i class="fa-solid fa-lock me-1"></i> Read-Only (Regulasi Pemerintah)</span>
    </div>

    <div class="row g-4">
        @foreach($terCategories as $catCode => $catData)
        <div class="col-lg-4">
            <div class="card border border-light-subtle rounded-4 h-100 shadow-2xs">
                <div class="card-header bg-primary bg-opacity-10 border-0 p-3 rounded-top-4">
                    <h6 class="fw-bolder text-primary m-0 d-flex justify-content-between align-items-center">
                        <span>{{ str_replace('_', ' ', $catCode) }}</span>
                        <span class="badge bg-primary text-white fs-7">{{ $catData['ptkp'] }}</span>
                    </h6>
                    <small class="text-muted d-block mt-1 fs-7">{{ $catData['description'] }}</small>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr class="small text-muted border-bottom">
                                <th class="py-2 px-3">Penghasilan Bruto / Bulan</th>
                                <th class="py-2 px-3 text-end">Tarif TER</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($catData['brackets'] as $bracket)
                            <tr>
                                <td class="py-2 px-3 small text-dark fw-semibold">
                                    @if(is_numeric($bracket['max']))
                                        s/d Rp {{ number_format($bracket['max'], 0, ',', '.') }}
                                    @else
                                        > Rp {{ $bracket['max'] }}
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-end fw-bold text-danger">
                                    {{ number_format($bracket['rate'], 2) }}%
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- MODAL EDIT PARAMETER BPJS -->
<div class="modal fade" id="editBpjsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h6 class="modal-header-title fw-bold text-dark m-0"><i class="fa-solid fa-sliders text-primary me-2"></i> Edit Parameter BPJS</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('tax-bpjs.update-bpjs') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Potongan BPJS TK Karyawan (%)</label>
                        <input type="number" step="0.1" name="bpjs_tk_employee_rate" class="form-control" value="{{ $bpjsSettings['bpjs_tk_rate'] }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Potongan BPJS Kesehatan Karyawan (%)</label>
                        <input type="number" step="0.1" name="bpjs_ks_employee_rate" class="form-control" value="{{ $bpjsSettings['bpjs_ks_rate'] }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Maksimum Cap Gaji BPJS Kesehatan (Rp)</label>
                        <input type="number" name="bpjs_ks_max_cap" class="form-control" value="{{ $bpjsSettings['bpjs_ks_cap'] }}" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection