@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian')
@section('page_title', 'Form Input Absensi & Komponen Variabel')

@section('content')
<div class="container-fluid p-0">
    <!-- TOP BAR CONTROL & HEADER -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-primary"></i>
                    <span>Input Absensi & Variabel Bulanan</span>
                </h5>
                <p class="text-muted small m-0 mt-1">Kelola rekap kehadiran bulanan via Import Excel atau spreadsheet manual.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <!-- TOMBOL EXPORT BCA KLIKBCA -->
                <a href="{{ route('payrolls.export-bca', ['period' => $period ?? date('Y-m')]) }}" class="btn btn-outline-primary btn-sm px-3 py-2 rounded-3 fw-bold">
                    <i class="fa-solid fa-file-csv me-1"></i> Export CSV BCA
                </a>

                <a href="{{ route('payrolls.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-medium">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Rekap
                </a>
            </div>
        </div>

        <hr class="my-3 opacity-10">

        <!-- CARDS: IMPORT EXCEL & SELEKSI PERIODE -->
        <div class="row g-3">
            <!-- CARD IMPORT EXCEL -->
            <div class="col-lg-6">
                <div class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-file-excel"></i>
                        </div>
                        <h6 class="fw-bold text-success m-0">Import Rekap Absensi (Excel)</h6>
                    </div>
                    <p class="text-muted small mb-3">Upload file Excel (.xlsx / .csv) dari mesin absensi untuk mengisi kolom HDR & Lembur secara otomatis.</p>
                    
                    <form action="{{ route('payrolls.import') }}" method="POST" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-8">
                            <input type="file" name="file" required class="form-control form-control-sm bg-white border-success border-opacity-25 rounded-3" {{ (isset($isLocked) && $isLocked) ? 'disabled' : '' }}>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-success btn-sm fw-bold w-100 rounded-3 shadow-sm" {{ (isset($isLocked) && $isLocked) ? 'disabled' : '' }}>
                                <i class="fa-solid fa-file-import me-1"></i> Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CARD PERIODE GAJIAN & LOCK STATUS -->
            <div class="col-lg-6">
                <div class="p-3 rounded-4 bg-light border border-secondary border-opacity-10 h-100 d-flex flex-column justify-content-center">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-dark small text-uppercase tracking-wider m-0">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i> Periode Gajian (Bulan & Tahun)
                        </label>

                        <!-- BADGE STATUS LOCK / UNLOCK -->
                        @if(isset($isLocked) && $isLocked)
                            <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-1 fw-bold">
                                <i class="fa-solid fa-lock me-1"></i> Calculations Locked
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning border border-warning rounded-pill px-3 py-1 fw-bold">
                                <i class="fa-solid fa-lock-open me-1"></i> Draft Mode
                            </span>
                        @endif
                    </div>

                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted rounded-start-3"><i class="fa-regular fa-calendar"></i></span>
                        <input type="month" name="period_month" form="mainPayrollForm" class="form-control border-start-0 fw-bold text-dark rounded-end-3" value="{{ old('period_month', $period ?? date('Y-m')) }}" onchange="window.location.href='{{ route('absensi.create') }}?period='+this.value" required>
                    </div>

                    <div class="d-flex align-items-center gap-1 text-muted small mt-2">
                        <i class="fa-solid fa-circle-info text-primary"></i>
                        <span>BPJS TK/KS & PPh 21 TER dihitung otomatis dari Master Kontrak.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN FORM SPREADSHEET TABLE -->
    <form id="mainPayrollForm" action="{{ route('payrolls.store') }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead>
                        <!-- TOP ROW HEADER -->
                        <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">
                            <th rowspan="2" class="py-3 px-3 border-end border-secondary border-opacity-25" style="width: 50px;">No</th>
                            <th rowspan="2" class="py-3 px-3 text-start border-end border-secondary border-opacity-25" style="min-width: 220px;">Karyawan</th>
                            <th rowspan="2" class="py-3 px-3 text-start border-end border-secondary border-opacity-25" style="min-width: 180px;">Jabatan & Level</th>
                            <th colspan="3" class="py-2 bg-primary bg-gradient text-white border-end border-primary border-opacity-25 fw-bold">
                                <i class="fa-solid fa-user-clock me-1"></i> Kehadiran & Absensi (HR)
                            </th>
                            <th colspan="4" class="py-2 bg-success bg-gradient text-white fw-bold">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Variabel & Potongan (Finance)
                            </th>
                        </tr>
                        <!-- SUB ROW HEADER -->
                        <tr class="text-center align-middle small fw-bold">
                            <th class="bg-primary bg-opacity-10 text-primary border-end border-secondary border-opacity-10 py-2" style="width: 100px;">HDR (Hari)</th>
                            <th class="bg-primary bg-opacity-10 text-danger border-end border-secondary border-opacity-10 py-2" style="width: 110px;">Unpaid Leave</th>
                            <th class="bg-primary bg-opacity-10 text-primary border-end border-secondary border-opacity-25 py-2" style="width: 100px;">Lembur (Jam)</th>
                            
                            <th class="bg-success bg-opacity-10 text-success border-end border-secondary border-opacity-10 py-2" style="width: 150px;">Cuti Melahirkan</th>
                            <th class="bg-success bg-opacity-10 text-success border-end border-secondary border-opacity-10 py-2" style="width: 160px;">Bonus / Insentif</th>
                            <th class="bg-success bg-opacity-10 text-danger border-end border-secondary border-opacity-10 py-2" style="width: 160px;">Pinjaman / Kasbon</th>
                            <th class="bg-success bg-opacity-10 text-danger py-2" style="width: 150px;">Potongan Lain</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $index => $emp)
                        @php 
                            $contract = $emp->activeContract; 
                            $existing = $emp->payrolls->first();
                            $lockedState = (isset($isLocked) && $isLocked);
                        @endphp
                        <tr>
                            <td class="text-center fw-bold text-muted border-end border-light-subtle">{{ $index + 1 }}</td>
                            <td class="border-end border-light-subtle">
                                <div class="fw-bold text-dark mb-0">{{ $emp->full_name }}</div>
                                <span class="badge bg-light text-secondary border rounded-pill px-2 py-0 mt-1" style="font-size: 0.72rem;">NIK: {{ $emp->nik_ktp }}</span>
                            </td>
                            <td class="border-end border-light-subtle">
                                <div class="fw-semibold text-dark">{{ $contract->job_title ?? '-' }}</div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Kat: {{ $contract->category ?? '-' }} | Lvl: {{ $contract->level ?? '-' }}</small>
                            </td>

                            <!-- 1. HDR (Hari Masuk) -->
                            <td class="border-end border-light-subtle bg-light bg-opacity-30 p-2">
                                <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][work_days]" class="form-control form-control-sm text-center fw-bold rounded-3 border-secondary border-opacity-25 shadow-2xs" value="{{ old('payrolls.'.$emp->id_employee.'.work_days', $existing->work_days ?? 27) }}" {{ $lockedState ? 'readonly' : '' }} required>
                            </td>

                            <!-- 2. Unpaid Leave -->
                            <td class="border-end border-light-subtle bg-light bg-opacity-30 p-2">
                                <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][unpaid_leave]" class="form-control form-control-sm text-center fw-bold text-danger border-danger border-opacity-25 rounded-3 shadow-2xs" value="{{ old('payrolls.'.$emp->id_employee.'.unpaid_leave', $existing->unpaid_leave ?? 0) }}" {{ $lockedState ? 'readonly' : '' }}>
                            </td>

                            <!-- 3. Lembur (Jam) -->
                            <td class="border-end border-secondary border-opacity-25 bg-light bg-opacity-30 p-2">
                                <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][overtime_hours]" class="form-control form-control-sm text-center fw-bold border-secondary border-opacity-25 rounded-3 shadow-2xs" value="{{ old('payrolls.'.$emp->id_employee.'.overtime_hours', $existing->overtime_hours ?? 0) }}" {{ $lockedState ? 'readonly' : '' }}>
                            </td>

                            <!-- 4. Cuti Melahirkan (Nominal) -->
                            <td class="border-end border-light-subtle p-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0 text-muted rounded-start-3">Rp</span>
                                    <input type="text" name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]" class="form-control border-start-0 fw-bold currency-input rounded-end-3" value="{{ number_format($existing->maternity_leave_pay ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                </div>
                            </td>

                            <!-- 5. Bonus / Insentif -->
                            <td class="border-end border-light-subtle p-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-success bg-opacity-10 border-end-0 text-success fw-bold rounded-start-3">Rp</span>
                                    <input type="text" name="payrolls[{{ $emp->id_employee }}][incentive]" class="form-control border-start-0 fw-bold text-success currency-input rounded-end-3" value="{{ number_format($existing->incentive ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                </div>
                            </td>

                            <!-- 6. Pinjaman / Kasbon -->
                            <td class="border-end border-light-subtle p-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold rounded-start-3">Rp</span>
                                    <input type="text" name="payrolls[{{ $emp->id_employee }}][cash_advance]" class="form-control border-start-0 fw-bold text-danger currency-input rounded-end-3" value="{{ number_format($existing->cash_advance ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                </div>
                            </td>

                            <!-- 7. Potongan Lain -->
                            <td class="p-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold rounded-start-3">Rp</span>
                                    <input type="text" name="payrolls[{{ $emp->id_employee }}][other_deductions]" class="form-control border-start-0 fw-bold text-danger currency-input rounded-end-3" value="{{ number_format($existing->other_deductions ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 d-block mb-3"></i>
                                <span>Belum ada data karyawan aktif untuk diproses gajian.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- FOOTER BAR: KONTROL TOMBOL SIMPAN & LOCK -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-bottom-4 border-top">
                <div class="text-muted small">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i> 
                    @if(isset($isLocked) && $isLocked)
                        Kalkulasi periode ini sudah dikunci. Buka kuncian untuk melakukan perubahan data.
                    @else
                        Pastikan seluruh data diperiksa dengan cermat sebelum mengunci perhitungan.
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if(isset($isLocked) && $isLocked)
                        <!-- TOMBOL UNLOCK (KHUSUS MANAGER KEUANGAN) -->
                        @if(Auth::user() && Auth::user()->role === 'manager_keuangan')
                            <button type="submit" form="formUnlockPayroll" class="btn btn-outline-warning fw-bold px-4 py-2 rounded-3 shadow-sm">
                                <i class="fa-solid fa-lock-open me-1"></i> Unlock Calculation
                            </button>
                        @endif
                    @else
                        <!-- TOMBOL SIMPAN SPREADSHEET -->
                        <button type="submit" class="btn btn-success px-4 py-2 fw-bold rounded-3 shadow-sm">
                            <i class="fa-solid fa-calculator me-1"></i> Hitung & Simpan Process Payroll
                        </button>

                        <!-- TOMBOL LOCK CALCULATION -->
                        <button type="submit" form="formLockPayroll" class="btn btn-dark px-4 py-2 fw-bold rounded-3 shadow-sm" onclick="return confirm('Apakah Anda yakin ingin mengunci perhitungan payroll periode ini?');">
                            <i class="fa-solid fa-lock me-1"></i> Lock Calculation
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <!-- HIDDEN FORMS FOR LOCK & UNLOCK ACTION -->
    <form id="formLockPayroll" action="{{ route('payrolls.lock') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="period" value="{{ $period ?? date('Y-m') }}">
    </form>

    <form id="formUnlockPayroll" action="{{ route('payrolls.unlock') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="period" value="{{ $period ?? date('Y-m') }}">
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const currencyInputs = document.querySelectorAll('.currency-input');

    const formatCurrency = (val) => {
        let clean = val.replace(/[^0-9]/g, '');
        return clean ? new Intl.NumberFormat('id-ID').format(clean) : '0';
    };

    currencyInputs.forEach(function (input) {
        if (input.value) {
            input.value = formatCurrency(input.value);
        }

        input.addEventListener('input', function () {
            this.value = formatCurrency(this.value);
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
});
</script>
@endpush