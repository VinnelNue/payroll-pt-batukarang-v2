@extends('layouts.app')

@section('title', 'Rekap Process Payroll')
@section('page_title', 'Rekap Hasil Penggajian Bulanan')

@section('content')
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <!-- 1. TOP HEADER & ACTION BUTTONS -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold text-dark m-0">Rekap penggajian Bulanan</h4>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                    <i class="fa-solid fa-calendar-day me-1"></i> {{ $period }}
                </span>
            </div>
            <p class="text-muted small m-0 mt-1">Hasil kalkulasi Gaji Kotor, BPJS, PPh 21 TER, dan Take Home Pay periode ini.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- FILTER PERIODE BULAN (COMPACT) -->
            <form action="{{ route('payrolls.index') }}" method="GET" class="d-flex gap-2">
                <input type="month" name="period" class="form-control form-control-sm fw-bold border-secondary-subtle" value="{{ $period }}" onchange="this.form.submit()">
            </form>

            <!-- ACTION EDIT ABSENSI -->
            <a href="{{ route('absensi.create') }}" class="btn btn-primary btn-sm px-3 py-2 rounded-3 fw-semibold">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Absensi & Variabel
            </a>
        </div>
    </div>

    <!-- Alert Success dihapus dari sini agar tidak double jika sudah ada di Layout App -->

    <!-- 2. SUMMARY CARDS ELEGAN -->
    @php
        $totalGross = $payrolls->sum('gross_salary');
        $totalNet   = $payrolls->sum('net_salary');
        $totalPph   = $payrolls->sum('pph21_deduction');
        $totalBpjs  = $payrolls->sum('bpjs_tk_deduction') + $payrolls->sum('bpjs_ks_deduction');
    @endphp
    <div class="row g-3 mb-4">
        <!-- TOTAL THP (BCA) -->
        <div class="col-md-3">
            <div class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 h-100 position-relative overflow-hidden">
                <small class="text-success fw-bold text-uppercase tracking-wider d-block mb-1">Total Take Home Pay (BCA)</small>
                <h4 class="fw-bolder text-success m-0">Rp {{ number_format($totalNet, 0, ',', '.') }}</h4>
                <i class="fa-solid fa-building-columns text-success opacity-10 position-absolute end-0 bottom-0 fs-1 m-2"></i>
            </div>
        </div>

        <!-- TOTAL PPH 21 (CORTAX) -->
        <div class="col-md-3">
            <div class="p-3 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-25 h-100 position-relative overflow-hidden">
                <small class="text-danger fw-bold text-uppercase tracking-wider d-block mb-1">Setoran PPh 21 (CORTAX)</small>
                <h4 class="fw-bolder text-danger m-0">Rp {{ number_format($totalPph, 0, ',', '.') }}</h4>
                <i class="fa-solid fa-calculator text-danger opacity-10 position-absolute end-0 bottom-0 fs-1 m-2"></i>
            </div>
        </div>

        <!-- TOTAL BPJS -->
        <div class="col-md-3">
            <div class="p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 h-100 position-relative overflow-hidden">
                <small class="text-warning-emphasis fw-bold text-uppercase tracking-wider d-block mb-1">Total Iuran BPJS</small>
                <h4 class="fw-bolder text-warning-emphasis m-0">Rp {{ number_format($totalBpjs, 0, ',', '.') }}</h4>
                <i class="fa-solid fa-shield-halved text-warning opacity-10 position-absolute end-0 bottom-0 fs-1 m-2"></i>
            </div>
        </div>

        <!-- TOTAL GROSS -->
        <div class="col-md-3">
            <div class="p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 h-100 position-relative overflow-hidden">
                <small class="text-primary fw-bold text-uppercase tracking-wider d-block mb-1">Total Pengeluaran Bruto</small>
                <h4 class="fw-bolder text-primary m-0">Rp {{ number_format($totalGross, 0, ',', '.') }}</h4>
                <i class="fa-solid fa-money-bill-wave text-primary opacity-10 position-absolute end-0 bottom-0 fs-1 m-2"></i>
            </div>
        </div>
    </div>

    <!-- 3. TABEL REKAP PAYROLL -->
    <div class="table-responsive rounded-4 border border-light-subtle shadow-2xs">
        <table class="table table-hover align-middle mb-0 text-nowrap">
            <thead class="bg-light border-bottom">
                <tr class="text-secondary small fw-bold text-uppercase">
                    <th class="py-3 px-3 text-center" style="width: 40px;">No</th>
                    <th class="py-3 px-3">Karyawan</th>
                    <th class="py-3 px-3 text-center">Kehadiran</th>
                    <th class="py-3 px-3 text-end">Gaji Bruto</th>
                    <th class="py-3 px-3 text-end">BPJS TK (2%)</th>
                    <th class="py-3 px-3 text-end">BPJS KS (1%)</th>
                    <th class="py-3 px-3 text-end">PPh 21 TER</th>
                    <th class="py-3 px-3 text-end">Potongan / Kasbon</th>
                    <th class="py-3 px-3 text-end bg-success bg-opacity-10 text-success fw-bolder">Take Home Pay</th>
                    <th class="py-3 px-3 text-center" style="width: 80px;">Slip</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($payrolls as $index => $pay)
                <tr>
                    <td class="text-center fw-bold text-muted px-3">{{ $index + 1 }}</td>
                    <td class="px-3">
                        <div class="fw-bold text-dark">{{ $pay->employee->full_name ?? '-' }}</div>
                        <span class="badge bg-light text-secondary border small fw-normal">{{ $pay->employee->activeContract->job_title ?? '-' }}</span>
                    </td>
                    <td class="text-center px-3">
                        <div class="fw-semibold text-dark">{{ $pay->work_days }} Hari</div>
                        @if($pay->unpaid_leave > 0)
                            <small class="text-danger fw-semibold">({{ $pay->unpaid_leave }} Hari Absen)</small>
                        @endif
                    </td>
                    <td class="text-end fw-semibold text-dark px-3">
                        Rp {{ number_format($pay->gross_salary, 0, ',', '.') }}
                    </td>
                    <td class="text-end text-muted small px-3">
                        Rp {{ number_format($pay->bpjs_tk_deduction, 0, ',', '.') }}
                    </td>
                    <td class="text-end text-muted small px-3">
                        Rp {{ number_format($pay->bpjs_ks_deduction, 0, ',', '.') }}
                    </td>
                    <td class="text-end text-danger fw-semibold px-3">
                        Rp {{ number_format($pay->pph21_deduction, 0, ',', '.') }}
                    </td>
                    <td class="text-end text-danger small px-3">
                        Rp {{ number_format($pay->cash_advance, 0, ',', '.') }}
                    </td>
                    <td class="text-end fw-bolder text-success bg-success bg-opacity-10 fs-6 px-3">
                        Rp {{ number_format($pay->net_salary, 0, ',', '.') }}
                    </td>
                    <td class="text-center px-3">
                        <div class="d-flex justify-content-center gap-1">
                            <!-- Button Download / Preview PDF -->
                            <a href="{{ route('payrolls.print-pdf', $pay->id_payroll) }}" target="_blank" class="btn btn-light btn-sm border rounded-3 text-danger hover-bg-danger" title="Cetak Slip PDF">
                                <i class="fa-solid fa-file-pdf fs-6"></i>
                            </a>
                            <!-- Button Kirim Email -->
                            <a href="{{ route('payrolls.send-email', $pay->id_payroll) }}" class="btn btn-light btn-sm border rounded-3 text-primary hover-bg-primary" title="Kirim Email Slip Gaji" onclick="return confirm('Kirim slip gaji ke email karyawan?')">
                                <i class="fa-solid fa-paper-plane fs-6"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-file-circle-xmark fs-2 d-block mb-2 text-secondary opacity-50"></i>
                        Belum ada data payroll diproses untuk periode {{ $period }}.
                        <div class="mt-2">
                            <a href="{{ route('absensi.create') }}" class="btn btn-sm btn-primary px-3 rounded-3">
                                <i class="fa-solid fa-plus me-1"></i> Input Absensi
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection