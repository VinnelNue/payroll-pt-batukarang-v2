@extends('layouts.app')

@section('title', 'Input Absensi & Variabel Gajian')
@section('page_title', 'Form Input Absensi & Komponen Variabel')

@push('styles')
<style>
    /* RESET FONT ABSENSI: Pakai font sistem paling bersih & tegak */
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
    /* Styling saat status belum diisi / belum ada log scan */
    .status-bg-empty { 
        background-color: #f8f9fa !important; 
        color: #6c757d !important; 
        font-weight: normal !important; 
    }
    /* COLOR PALETTE DYNAMIC PER STATUS */
    .status-bg-H { background-color: #ffffff !important; color: #198754 !important; }
    .status-bg-SKD { background-color: #e0f2fe !important; color: #0284c7 !important; }
    .status-bg-C { background-color: #fef3c7 !important; color: #d97706 !important; }
    .status-bg-CM { background-color: #f3e8ff !important; color: #7e22ce !important; }
    .status-bg-A { background-color: #fee2e2 !important; color: #dc2626 !important; font-weight: 900 !important; }
    .status-bg-H05 { background-color: #ffedd5 !important; color: #c2410c !important; font-weight: 900 !important; }

    .attendance-select option {
        font-weight: bold;
        text-align: center;
        background-color: #ffffff;
        color: #333333;
    }
    
    /* OPTIMASI TABEL LEBAR & FIX STICKY BUG */
    .timesheet-container {
        max-width: 100%;
        overflow-x: auto;
        white-space: nowrap;
    }

    /* FIX COLUMN 1 (NO) */
    .sticky-col-1 {
        position: sticky !important;
        left: 0 !important;
        z-index: 5;
        width: 45px !important;
        min-width: 45px !important;
    }

    /* FIX COLUMN 2 (KARYAWAN & JABATAN) */
    .sticky-col-2 {
        position: sticky !important;
        left: 45px !important;
        z-index: 5;
        box-shadow: 3px 0 5px rgba(0,0,0,0.08);
    }

    /* Pastikan Header Tetap Gelap Hitam/Dark */
    tr.bg-dark th.sticky-col-1,
    tr.bg-dark th.sticky-col-2 {
        background-color: #212529 !important;
        color: #ffffff !important;
        z-index: 6;
    }

    /* Pastikan Body TD Tetap Putih */
    tbody td.sticky-col-1,
    tbody td.sticky-col-2 {
        background-color: #ffffff !important;
    }
</style>
@endpush

@section('content')
@php
    $isFinancialRole = in_array(Auth::user()->role ?? '', ['manager_keuangan', 'super_admin']);
    $daysInMonth = \Carbon\Carbon::parse(($period ?? date('Y-m')) . '-01')->daysInMonth;
@endphp

<div class="container-fluid p-0">
    <!-- TOP BAR CONTROL & HEADER -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-primary"></i>
                    <span>Input Absensi Harian & Variabel Bulanan</span>
                </h5>
                <p class="text-muted small m-0 mt-1">HRD mengelola absensi harian via ZIP/Excel, Manager Keuangan mengelola komponen finansial & TER.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                @if($isFinancialRole)
                    <a href="{{ route('payrolls.export-bca', ['period' => $period ?? date('Y-m')]) }}" class="btn btn-outline-primary btn-sm px-3 py-2 rounded-3 fw-bold">
                        <i class="fa-solid fa-file-csv me-1"></i> Export CSV BCA
                    </a>
                @endif

                <a href="{{ route('payrolls.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-medium">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Rekap
                </a>
            </div>
        </div>

        <hr class="my-3 opacity-10">

        <!-- CARDS: IMPORT ZIP/EXCEL & SELEKSI PERIODE -->
        <div class="row g-3">
            <!-- CARD IMPORT EXCEL / ZIP FINGERPRINT -->
            <div class="col-lg-6">
                <div class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-file-zipper"></i>
                        </div>
                        <h6 class="fw-bold text-success m-0">Import Log Mesin Fingerprint (ZIP / Excel)</h6>
                    </div>
                    <p class="text-muted small mb-3">Upload file <strong>.ZIP</strong> (kumpulan excel harian) atau file Excel tunggal dari mesin absensi.</p>
                    
                    <form action="{{ route('payrolls.import') }}" method="POST" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <input type="hidden" name="period_month" value="{{ $period ?? date('Y-m') }}">
                        <div class="col-8">
                            <input type="file" name="file" accept=".zip,.rar,.xlsx,.xls,.csv" required class="form-control form-control-sm bg-white border-success border-opacity-25 rounded-3" {{ (isset($isLocked) && $isLocked) ? 'disabled' : '' }}>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-success btn-sm fw-bold w-100 rounded-3 shadow-sm" {{ (isset($isLocked) && $isLocked) ? 'disabled' : '' }}>
                                <i class="fa-solid fa-file-import me-1"></i> Import Log
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
                        <span>Keterangan: <strong>H</strong> (Hadir), <strong>SKD</strong> (Dokter), <strong>C</strong> (Cuti), <strong class="text-danger">A (Alpha/Merah)</strong>.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN FORM SPREADSHEET TABLE -->
    <form id="mainPayrollForm" action="{{ route('payrolls.store') }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="table-responsive timesheet-container">
                <table class="table table-hover align-middle mb-0 text-nowrap table-bordered fs-7">
                    <thead>
                        <!-- TOP ROW HEADER -->
                        <tr class="bg-dark text-white text-center align-middle small text-uppercase tracking-wider">
                            <th rowspan="2" class="py-3 px-2 sticky-col-1 bg-dark text-white" style="width: 40px;">No</th>
                            <th rowspan="2" class="py-3 px-3 text-start sticky-col-2 bg-dark text-white" style="min-width: 220px;">Karyawan & Jabatan</th>
                            
                            @if($isFinancialRole)
                                <th rowspan="2" class="py-3 px-3 text-start bg-dark text-white" style="min-width: 170px;">Level, Kat & TER</th>
                            @endif

                            <th colspan="{{ $daysInMonth }}" class="py-2 bg-primary bg-gradient text-white fw-bold">
                                <i class="fa-solid fa-calendar-days me-1"></i> Timesheet Absensi Harian (Tgl 1 s/d {{ $daysInMonth }})
                            </th>
                            <th colspan="2" class="py-2 bg-info bg-gradient text-dark fw-bold">Rekap Jam/Hari</th>
                            
                            @if($isFinancialRole)
                                <th colspan="4" class="py-2 bg-success bg-gradient text-white fw-bold">
                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Variabel Finansial (Manager Keuangan Only)
                                </th>
                            @endif
                        </tr>
                        <!-- SUB ROW HEADER -->
                        <tr class="text-center align-middle small fw-bold">
                            <!-- LOOPING HEADERS TANGGAL 1 S/D 31 -->
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <th class="bg-light text-dark p-1" style="width: 38px; font-size: 0.75rem;">{{ $d }}</th>
                            @endfor

                            <th class="bg-danger bg-opacity-10 text-danger" style="width: 75px;">Alpha (A)</th>
                            <th class="bg-primary bg-opacity-10 text-primary" style="width: 75px;">Lembur(J)</th>

                            @if($isFinancialRole)
                                <th class="bg-success bg-opacity-10 text-success" style="width: 140px;">Cuti Melahirkan</th>
                                <th class="bg-success bg-opacity-10 text-success" style="width: 140px;">Bonus / Insentif</th>
                                <th class="bg-success bg-opacity-10 text-danger" style="width: 140px;">Kasbon</th>
                                <th class="bg-success bg-opacity-10 text-danger" style="width: 140px;">Potongan Lain</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $index => $emp)
                        @php 
                            $contract = $emp->activeContract; 

                            // AMBIL DATA PAYROLL PERIODE DIPILIH
                            $existing = $emp->payrolls->where('period_month', $period)->first();
                            $lockedState = (isset($isLocked) && $isLocked);

                            // DEKODE ARRAY DAILY ATTENDANCE
                            $dailyData = [];
                            if ($existing && $existing->daily_attendance) {
                                $dailyData = is_array($existing->daily_attendance) 
                                    ? $existing->daily_attendance 
                                    : (json_decode($existing->daily_attendance, true) ?? []);
                            }
                        @endphp
                        <tr>
                            <td class="text-center fw-bold text-muted sticky-col-1">{{ $index + 1 }}</td>
                            
                            <!-- NAMA & JABATAN -->
                            <td class="sticky-col-2">
                                <div class="fw-bold text-dark mb-0">{{ $emp->full_name }}</div>
                                <div class="text-primary fw-semibold" style="font-size: 0.78rem;">
                                    {{ $contract->job_title ?? 'Staff' }}
                                </div>
                                <span class="badge bg-light text-secondary border rounded-pill px-2 py-0 mt-1" style="font-size: 0.68rem;">
                                    NIK: {{ $emp->nik_ktp }}
                                </span>
                            </td>

                            <!-- LEVEL, KAT & TER (KHUSUS MANAGER KEUANGAN) -->
                            @if($isFinancialRole)
                                <td>
                                    <small class="text-muted d-block" style="font-size: 0.73rem;">
                                        Kat: <strong>{{ $contract->category ?? '-' }}</strong> | Lvl: <strong>{{ $contract->level ?? '-' }}</strong>
                                    </small>
                                    <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill mt-1" style="font-size: 0.68rem;">
                                        PTKP: {{ $contract->ptkp_status ?? 'TK/0' }}
                                    </span>
                                </td>
                            @endif

                            <!-- LOOPING SPREADSHEET TANGGAL HARI 1 S/D 31 -->
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $dayStatus = $dailyData[(string)$d] ?? $dailyData[$d] ?? ''; 
                                    $bgClass   = $dayStatus !== '' ? 'status-bg-' . str_replace('.', '', $dayStatus) : 'status-bg-empty';
                                @endphp
                                <td class="p-0 text-center">
                                    <select name="payrolls[{{ $emp->id_employee }}][daily_attendance][{{ $d }}]" 
                                            class="form-select form-select-sm border-0 attendance-select {{ $bgClass }}" 
                                            style="font-size: 0.75rem; height: 32px;"
                                            onchange="updateStatusColor(this); recalculateSummary(this);"
                                            {{ $lockedState ? 'disabled' : '' }}>
                                        <option value="" class="text-muted" {{ (string)$dayStatus === '' ? 'selected' : '' }}>-</option>
                                        <option value="H" class="text-success" {{ (string)$dayStatus === 'H' ? 'selected' : '' }}>H</option>
                                        <option value="H0.5" class="text-warning fw-bold" {{ (string)$dayStatus === 'H0.5' ? 'selected' : '' }}>H0.5</option>
                                        <option value="SKD" class="text-info" {{ (string)$dayStatus === 'SKD' ? 'selected' : '' }}>SKD</option>
                                        <option value="C" class="text-primary" {{ (string)$dayStatus === 'C' ? 'selected' : '' }}>C</option>
                                        <option value="CM" class="text-purple" {{ (string)$dayStatus === 'CM' ? 'selected' : '' }}>CM</option>
                                        <option value="A" class="text-danger" {{ (string)$dayStatus === 'A' ? 'selected' : '' }}>A</option>
                                    </select>
                                </td>
                            @endfor

                            <!-- REKAP ALPHA / UNPAID LEAVE -->
                            <td class="p-1">
                                <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][unpaid_leave]" class="form-control form-control-sm text-center fw-bold text-danger border-danger border-opacity-25" value="{{ old('payrolls.'.$emp->id_employee.'.unpaid_leave', $existing->unpaid_leave ?? 0) }}" {{ $lockedState ? 'readonly' : '' }}>
                            </td>

                            <!-- REKAP LEMBUR (JAM) -->
                            <td class="p-1">
                                <input type="number" step="0.5" name="payrolls[{{ $emp->id_employee }}][overtime_hours]" class="form-control form-control-sm text-center fw-bold border-secondary border-opacity-25" value="{{ old('payrolls.'.$emp->id_employee.'.overtime_hours', $existing->overtime_hours ?? 0) }}" {{ $lockedState ? 'readonly' : '' }}>
                            </td>

                            <!-- BAGIAN FINANSIAL (HANYA MANAGER KEUANGAN / SUPER ADMIN) -->
                            @if($isFinancialRole)
                                <td class="p-1">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Rp</span>
                                        <input type="text" name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]" class="form-control border-start-0 fw-bold currency-input" value="{{ number_format($existing->maternity_leave_pay ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                    </div>
                                </td>
                                <td class="p-1">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-success bg-opacity-10 border-end-0 text-success fw-bold">Rp</span>
                                        <input type="text" name="payrolls[{{ $emp->id_employee }}][incentive]" class="form-control border-start-0 fw-bold text-success currency-input" value="{{ number_format($existing->incentive ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                    </div>
                                </td>
                                <td class="p-1">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">Rp</span>
                                        <input type="text" name="payrolls[{{ $emp->id_employee }}][cash_advance]" class="form-control border-start-0 fw-bold text-danger currency-input" value="{{ number_format($existing->cash_advance ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                    </div>
                                </td>
                                <td class="p-1">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-danger bg-opacity-10 border-end-0 text-danger fw-bold">Rp</span>
                                        <input type="text" name="payrolls[{{ $emp->id_employee }}][other_deductions]" class="form-control border-start-0 fw-bold text-danger currency-input" value="{{ number_format($existing->other_deductions ?? 0, 0, ',', '.') }}" {{ $lockedState ? 'readonly' : '' }}>
                                    </div>
                                </td>
                            @else
                                <input type="hidden" name="payrolls[{{ $emp->id_employee }}][maternity_leave_pay]" value="{{ $existing->maternity_leave_pay ?? 0 }}">
                                <input type="hidden" name="payrolls[{{ $emp->id_employee }}][incentive]" value="{{ $existing->incentive ?? 0 }}">
                                <input type="hidden" name="payrolls[{{ $emp->id_employee }}][cash_advance]" value="{{ $existing->cash_advance ?? 0 }}">
                                <input type="hidden" name="payrolls[{{ $emp->id_employee }}][other_deductions]" value="{{ $existing->other_deductions ?? 0 }}">
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="45" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 d-block mb-3"></i>
                                <span>Belum ada data karyawan aktif untuk diproses gajian.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- FOOTER BAR -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-bottom-4 border-top">
                <div class="text-muted small">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i> 
                    @if(isset($isLocked) && $isLocked)
                        Kalkulasi periode ini sudah dikunci.
                    @else
                        Pastikan seluruh data diperiksa sebelum mengunci perhitungan.
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if(isset($isLocked) && $isLocked)
                        @if($isFinancialRole)
                            <button type="submit" form="formUnlockPayroll" class="btn btn-outline-warning fw-bold px-4 py-2 rounded-3 shadow-sm">
                                <i class="fa-solid fa-lock-open me-1"></i> Unlock Calculation
                            </button>
                        @endif
                    @else
                        <button type="submit" class="btn btn-success px-4 py-2 fw-bold rounded-3 shadow-sm">
                            <i class="fa-solid fa-calculator me-1"></i> Hitung & Simpan Process Payroll
                        </button>

                        <button type="submit" form="formLockPayroll" class="btn btn-dark px-4 py-2 fw-bold rounded-3 shadow-sm" onclick="return confirm('Apakah Anda yakin ingin mengunci perhitungan payroll periode ini?');">
                            <i class="fa-solid fa-lock me-1"></i> Lock Calculation
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </form>

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
// 1. GLOBAL FUNCTION: Ubah warna background per-kotak tanggal
function updateStatusColor(selectEl) {
    selectEl.classList.remove('status-bg-H', 'status-bg-H05', 'status-bg-SKD', 'status-bg-C', 'status-bg-CM', 'status-bg-A', 'status-bg-empty');
    
    if (selectEl.value === '') {
        selectEl.classList.add('status-bg-empty');
    } else {
        let cleanVal = selectEl.value.replace('.', '');
        selectEl.classList.add('status-bg-' + cleanVal);
    }
}

// 2. GLOBAL FUNCTION: Hitung ulang total Alpha & H0.5 secara instan di browser
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

    const unpaidInput = row.querySelector('input[name*="[unpaid_leave]"]');
    if (unpaidInput) {
        unpaidInput.value = alphaCount;
    }
}

// 3. DOM LOADED EVENT FOR CURRENCY INPUT FORMATTING
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
            if (this.value === '0') this.value = '';
        });

        input.addEventListener('blur', function () {
            if (this.value === '') this.value = '0';
        });
    });
});
</script>
@endpush