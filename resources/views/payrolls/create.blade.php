@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian')
@section('page_title', 'Form Input Absensi & Komponen Variabel')

@section('content')
<div class="card-custom p-4 mb-4 shadow-sm border-0 rounded-4 bg-white">
    <!-- HEADER TITLE & BUTTON -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-calendar-check text-primary me-2"></i> Input Absensi & Variabel Bulanan
            </h5>
            <small class="text-muted">Kelola rekap kehadiran bulanan via Import Excel atau input manual spreadsheet.</small>
        </div>
        <a href="{{ route('payrolls.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-medium">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Rekap
        </a>
    </div>

    <!-- SECTION 1: IMPORT EXCEL CARD & PERIODE -->
    <div class="row g-3 mb-4">
        <!-- CARD UPLOAD EXCEL -->
        <div class="col-lg-6">
            <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 h-100">
                <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-excel fs-5"></i>
                    <span>Import Rekap Absensi dari Excel</span>
                </h6>
                <p class="text-muted small mb-2">Upload file Excel (.xlsx / .csv) hasil ekspor mesin absensi untuk mengisi kolom HDR & Lembur secara otomatis.</p>
                
                <form action="{{ route('payrolls.import') }}" method="POST" enctype="multipart/form-data" class="row g-2">
                    @csrf
                    <div class="col-8">
                        <input type="file" name="file" required class="form-control form-control-sm border-success border-opacity-25">
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-success btn-sm fw-bold w-100">
                            <i class="fa-solid fa-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PERIODE GAJIAN & INFO -->
        <div class="col-lg-6">
            <div class="p-3 bg-light border border-light-subtle rounded-3 h-100 d-flex flex-column justify-content-center">
                <label class="form-label fw-bold text-secondary small text-uppercase mb-1">Periode Gajian (Bulan & Tahun)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-regular fa-calendar"></i></span>
                    <input type="month" name="period_month" form="mainPayrollForm" class="form-control border-start-0 fw-bold text-dark" value="{{ old('period_month', $period ?? date('Y-m')) }}" onchange="window.location.href='{{ route('absensi.create') }}?period='+this.value" required>
                </div>
                <small class="text-muted mt-2">
                    <i class="fa-solid fa-circle-info text-info me-1"></i> Potongan BPJS TK/KS & TER PPh 21 akan dihitung otomatis dari Master Kontrak.
                </small>
            </div>
        </div>
    </div>

    <!-- SECTION 2: FORM MAIN SPREADSHEET -->
    <form id="mainPayrollForm" action="{{ route('payrolls.store') }}" method="POST">
        @csrf

        <div class="table-responsive rounded-3 border">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead>
                    <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">
                        <th rowspan="2" class="py-3 px-3 border-end border-secondary" style="width: 50px;">No</th>
                        <th rowspan="2" class="py-3 px-3 text-start border-end border-secondary" style="min-width: 200px;">Karyawan</th>
                        <th rowspan="2" class="py-3 px-3 text-start border-end border-secondary" style="min-width: 180px;">Jabatan & Level</th>
                        <th colspan="3" class="py-2 bg-primary text-white border-end border-primary-subtle fw-bold">
                            <i class="fa-solid fa-user-clock me-1"></i> Kehadiran & Absensi (HR)
                        </th>
                        <th colspan="4" class="py-2 bg-success text-white fw-bold">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Variabel & Potongan (Finance)
                        </th>
                    </tr>
                    <tr class="text-center align-middle small fw-bold">
                        <th class="bg-primary-subtle text-primary border-end" style="width: 100px;">HDR (Hari)</th>
                        <th class="bg-primary-subtle text-danger border-end" style="width: 120px;">Unpaid Leave</th>
                        <th class="bg-primary-subtle text-primary border-end" style="width: 100px;">Lembur (Jam)</th>
                        
                        <th class="bg-success-subtle text-success border-end" style="width: 150px;">Cuti Melahirkan</th>
                        <th class="bg-success-subtle text-success border-end" style="width: 160px;">Bonus / Insentif</th>
                        <th class="bg-success-subtle text-danger border-end" style="width: 160px;">Pinjaman / Kasbon</th>
                        <th class="bg-success-subtle text-danger" style="width: 150px;">Potongan Lain</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $index => $emp)
                    @php 
                        $contract = $emp->activeContract; 
                        $existing = $emp->payrolls->first(); // Ambil data jika sudah pernah di-input di DB
                    @endphp
                    <tr>
                        <td class="text-center fw-bold text-muted border-end">{{ $index + 1 }}</td>
                        <td class="border-end">
                            <div class="fw-bold text-dark">{{ $emp->full_name }}</div>
                            <small class="text-muted">NIK: {{ $emp->nik_ktp }}</small>
                        </td>
                        <td class="border-end">
                            <div class="fw-semibold text-dark">{{ $contract->job_title ?? '-' }}</div>
                            <small class="text-muted">Kat: {{ $contract->category ?? '-' }} | Lvl: {{ $contract->level ?? '-' }}</small>
                        </td>

                        <!-- 1. HDR (Hari Masuk) -->
                        <td class="border-end bg-light-subtle">
                            <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][work_days]" class="form-control form-control-sm text-center fw-bold border-secondary-subtle" value="{{ old('payrolls.'.$emp->id_employee.'.work_days', $existing->work_days ?? 27) }}" required>
                        </td>

                        <!-- 2. Unpaid Leave -->
                        <td class="border-end bg-light-subtle">
                            <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][unpaid_leave]" class="form-control form-control-sm text-center fw-bold text-danger border-danger-subtle" value="{{ old('payrolls.'.$emp->id_employee.'.unpaid_leave', $existing->unpaid_leave ?? 0) }}">
                        </td>

                        <!-- 3. Lembur -->
                        <td class="border-end bg-light-subtle">
                            <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][overtime_hours]" class="form-control form-control-sm text-center fw-bold border-secondary-subtle" value="{{ old('payrolls.'.$emp->id_employee.'.overtime_hours', $existing->overtime_hours ?? 0) }}">
                        </td>

                        <!-- 4. Cuti Melahirkan (Nominal) -->
                        <td class="border-end">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted">Rp</span>
                                <input type="text" name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]" class="form-control fw-bold currency-input" value="{{ number_format($existing->maternity_leave_pay ?? 0, 0, ',', '.') }}">
                            </div>
                        </td>

                        <!-- 5. Bonus / Insentif -->
                        <td class="border-end">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-success fw-bold">Rp</span>
                                <input type="text" name="payrolls[{{ $emp->id_employee }}][incentive]" class="form-control fw-bold text-success currency-input" value="{{ number_format($existing->incentive ?? 0, 0, ',', '.') }}">
                            </div>
                        </td>

                        <!-- 6. Pinjaman / Kasbon -->
                        <td class="border-end">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-danger fw-bold">Rp</span>
                                <input type="text" name="payrolls[{{ $emp->id_employee }}][cash_advance]" class="form-control fw-bold text-danger currency-input" value="{{ number_format($existing->cash_advance ?? 0, 0, ',', '.') }}">
                            </div>
                        </td>

                        <!-- 7. Potongan Lain -->
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-danger fw-bold">Rp</span>
                                <input type="text" name="payrolls[{{ $emp->id_employee }}][other_deductions]" class="form-control fw-bold text-danger currency-input" value="{{ number_format($existing->other_deductions ?? 0, 0, ',', '.') }}">
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            Belum ada data karyawan aktif untuk diproses gajian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-success px-5 py-2 fw-bold rounded-3 shadow-sm">
                <i class="fa-solid fa-calculator me-2"></i> Hitung & Simpan Process Payroll
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // FORMATTER RUPIAH REAL-TIME SAAT DIKETIK / DI-PASTE
    const currencyInputs = document.querySelectorAll('.currency-input');

    const formatCurrency = (val) => {
        let clean = val.replace(/[^0-9]/g, '');
        return clean ? new Intl.NumberFormat('id-ID').format(clean) : '0';
    };

    currencyInputs.forEach(function (input) {
        // Format nilai awal saat halaman dimuat
        if (input.value) {
            input.value = formatCurrency(input.value);
        }

        // Event saat menginput angka
        input.addEventListener('input', function () {
            this.value = formatCurrency(this.value);
        });

        // Kosongkan angka 0 saat diklik/fokus agar nyaman mengetik
        input.addEventListener('focus', function () {
            if (this.value === '0') {
                this.value = '';
            }
        });

        // Kembalikan ke angka 0 jika ditinggalkan kosong
        input.addEventListener('blur', function () {
            if (this.value === '') {
                this.value = '0';
            }
        });
    });
});
</script>
@endpush