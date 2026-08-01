@extends('layouts.app')

@section('title', 'Setup Kontrak & Gaji')
@section('page_title', 'Setup Jabatan, Kontrak & Gaji Acuan')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h5 class="fw-bold text-dark m-0">Setup Jabatan & Gaji: {{ $employee->full_name }}</h5>
        <small class="text-muted">NIK: {{ $employee->nik_ktp }} | Kelola acuan Gapok, Tunjangan, BPJS & PPh 21</small>
    </div>
    <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<form action="{{ route('contracts.update', $employee->uuid) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <!-- PENEMPATAN & STATUS KERJA -->
        <div class="col-lg-6">
            <div class="card-custom p-4 h-100">
                <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                    <i class="fa-solid fa-briefcase me-2"></i> Penempatan & Status Kerja
                </h6>

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark">Nama Jabatan <span class="text-danger">*</span></label>
                        <input type="text" name="job_title" class="form-control" value="{{ old('job_title', $contract->job_title ?? '') }}" required placeholder="Contoh: Manager Regional Area">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Kategori</label>
                        <input type="text" name="category" class="form-control" value="{{ old('category', $contract->category ?? '') }}" placeholder="Contoh: A / B / C">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Level</label>
                        <input type="number" name="level" class="form-control" value="{{ old('level', $contract->level ?? '') }}" placeholder="Contoh: 21">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark">Status Hubungan Kerja <span class="text-danger">*</span></label>
                        <select name="employment_type" id="employmentTypeSelect" class="form-select" required>
                            <optgroup label="Status Aktif">
                                <option value="PKWT" {{ old('employment_type', $contract->employment_type ?? '') == 'PKWT' ? 'selected' : '' }}>PKWT (Kontrak)</option>
                                <option value="PKWTT" {{ old('employment_type', $contract->employment_type ?? '') == 'PKWTT' ? 'selected' : '' }}>PKWTT (Karyawan Tetap)</option>
                                <option value="Probation" {{ old('employment_type', $contract->employment_type ?? '') == 'Probation' ? 'selected' : '' }}>Probation (Masa Percobaan)</option>
                                <option value="Internship" {{ old('employment_type', $contract->employment_type ?? '') == 'Internship' ? 'selected' : '' }}>Magang / Internship</option>
                            </optgroup>
                            <optgroup label="Status Penghentian Kerja (Non-Aktif)">
                                <option value="PHK" {{ old('employment_type', $contract->employment_type ?? '') == 'PHK' ? 'selected' : '' }}>PHK (Pemutusan Hubungan Kerja)</option>
                                <option value="Resign" {{ old('employment_type', $contract->employment_type ?? '') == 'Resign' ? 'selected' : '' }}>Resign (Mengundurkan Diri)</option>
                                <option value="Pensiun" {{ old('employment_type', $contract->employment_type ?? '') == 'Pensiun' ? 'selected' : '' }}>Pensiun</option>
                                <option value="End_Contract" {{ old('employment_type', $contract->employment_type ?? '') == 'End_Contract' ? 'selected' : '' }}>Habis Masa Kontrak</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $contract->start_date ?? date('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark">Tanggal Berakhir</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $contract->end_date ?? '') }}">
                        <small class="text-muted">Kosongkan jika Karyawan Tetap (PKWTT)</small>
                    </div>

                    <!-- BOX DYNAMIC KHUSUS PHK / RESIGN -->
                    <div class="col-md-12 d-none" id="terminationBox">
                        <div class="p-3 bg-danger bg-opacity-10 border border-danger rounded-3 mt-2">
                            <h6 class="fw-bold text-danger mb-2">
                                <i class="fa-solid fa-user-slash me-1"></i> Informasi Penghentian Kerja
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark small">Tanggal Efektif Keluar / PHK</label>
                                    <input type="date" name="exit_date" class="form-control form-control-sm" value="{{ old('exit_date', $contract->exit_date ?? '') }}">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark small">Alasan Penghentian / Catatan</label>
                                    <textarea name="exit_reason" class="form-control form-control-sm" rows="2" placeholder="Catatan alasan PHK / Resign / Pensiun...">{{ old('exit_reason', $contract->exit_reason ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GAJI POKOK, TUNJANGAN, BPJS & PPH21 -->
        <div class="col-lg-6">
            <div class="card-custom p-4 h-100">
                <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                    <i class="fa-solid fa-money-bill-wave me-2"></i> Acuan Financial, BPJS & Pajak
                </h6>

                <div class="row g-3">
                    <!-- GAJI POKOK (GAPOK) -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark">Gaji Pokok (GAPOK) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Rp</span>
                            <input type="text" name="basic_salary" class="form-control fw-bold text-dark currency-input" 
                                value="{{ number_format(old('basic_salary', $contract->basic_salary ?? 0), 0, ',', '.') }}" required>
                        </div>
                    </div>

                    <!-- TUNJANGAN TETAP (TJ) -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark">Tunjangan Tetap (TJ) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Rp</span>
                            <input type="text" name="allowance" class="form-control fw-bold text-dark currency-input" 
                                value="{{ number_format(old('allowance', $contract->allowance ?? 0), 0, ',', '.') }}" required>
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- KEPESERTAAN BPJS -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark d-block">Kepesertaan BPJS</label>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_bpjstk_active" id="bpjstk" value="1" {{ old('is_bpjstk_active', $contract->is_bpjstk_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="bpjstk">Aktifkan Potongan BPJS Ketenagakerjaan</label>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_bpjs_health_active" id="bpjsks" value="1" {{ old('is_bpjs_health_active', $contract->is_bpjs_health_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="bpjsks">Aktifkan Potongan BPJS Kesehatan</label>
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- STATUS PTKP / PPH 21 (TER BARU) -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-dark">Status PTKP & Kategori TER (PPh 21) <span class="text-danger">*</span></label>
                        <select name="ptkp_status" class="form-select fw-semibold" required>
                            <optgroup label="TER A (Lajang / Kawin Tanpa Tanggungan)">
                                <option value="TK/0" {{ old('ptkp_status', $contract->ptkp_status ?? 'TK/0') == 'TK/0' ? 'selected' : '' }}>TK/0 — Tidak Kawin, 0 Tanggungan (TER A)</option>
                                <option value="TK/1" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'TK/1' ? 'selected' : '' }}>TK/1 — Tidak Kawin, 1 Tanggungan (TER A)</option>
                                <option value="K/0" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'K/0' ? 'selected' : '' }}>K/0 — Kawin, 0 Tanggungan (TER A)</option>
                            </optgroup>
                            <optgroup label="TER B (Kawin/Lajang Tanggungan Sedang)">
                                <option value="TK/2" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'TK/2' ? 'selected' : '' }}>TK/2 — Tidak Kawin, 2 Tanggungan (TER B)</option>
                                <option value="TK/3" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'TK/3' ? 'selected' : '' }}>TK/3 — Tidak Kawin, 3 Tanggungan (TER B)</option>
                                <option value="K/1" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'K/1' ? 'selected' : '' }}>K/1 — Kawin, 1 Tanggungan (TER B)</option>
                                <option value="K/2" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'K/2' ? 'selected' : '' }}>K/2 — Kawin, 2 Tanggungan (TER B)</option>
                            </optgroup>
                            <optgroup label="TER C (Kawin Tanggungan Maksimal)">
                                <option value="K/3" {{ old('ptkp_status', $contract->ptkp_status ?? '') == 'K/3' ? 'selected' : '' }}>K/3 — Kawin, 3 Tanggungan (TER C)</option>
                            </optgroup>
                        </select>
                        <small class="text-muted">Kategori TER A/B/C akan menentukan persentase potongan PPh 21 bulanan secara otomatis.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <a href="{{ route('contracts.index') }}" class="btn btn-light border px-4 me-2">Batal</a>
            <button type="submit" class="btn btn-primary px-5 fw-bold">
                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Kontrak & Gaji
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. FORMATTER CURRENCY RUPIAH REAL-TIME
    const currencyInputs = document.querySelectorAll('.currency-input');

    currencyInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                this.value = new Intl.NumberFormat('id-ID').format(value);
            } else {
                this.value = '0';
            }
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

    // 2. TOGGLE BOX PHK / RESIGN
    const empTypeSelect = document.getElementById('employmentTypeSelect');
    const terminationBox = document.getElementById('terminationBox');

    function checkTermination() {
        const value = empTypeSelect.value;
        if (['PHK', 'Resign', 'Pensiun', 'End_Contract'].includes(value)) {
            terminationBox.classList.remove('d-none');
        } else {
            terminationBox.classList.add('d-none');
        }
    }

    if (empTypeSelect && terminationBox) {
        empTypeSelect.addEventListener('change', checkTermination);
        checkTermination(); // Jalankan saat awal load
    }
});
</script>
@endpush