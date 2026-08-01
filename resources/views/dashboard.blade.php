@extends('layouts.app')

@section('title', 'Dashboard Overview')
@section('page_title', 'Dashboard Overview')

@section('content')
<div class="container-fluid p-0">
    <!-- WELCOME BANNER -->
    <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 mb-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold m-0"><i class="fa-solid fa-building me-2"></i> Sistem Payroll 2.0 PT Batu Karang</h4>
                <p class="mb-0 mt-1 opacity-75 small">Modul terpadu pengelolaan Data Diri Master Karyawan, Jabatan, Site Placement, PPh 21 TER, dan BPJS.</p>
            </div>
            <div class="d-none d-md-block">
                <a href="{{ route('absensi.create') }}" class="btn btn-light text-primary fw-bold px-3 py-2 rounded-3 shadow-sm">
                    <i class="fa-solid fa-calculator me-1"></i> Input Absensi Bulanan
                </a>
            </div>
        </div>
    </div>

    <!-- ROW 1: STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-primary border-4 h-100">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-users fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block small">Total Master Karyawan</small>
                        <h4 class="fw-bold text-dark m-0">{{ $totalEmployees }} <span class="fs-6 text-muted fw-normal">Orang</span></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-success border-4 h-100">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-wallet fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block small">Total Payroll (Bruto)</small>
                        <h5 class="fw-bold text-dark m-0">Rp {{ number_format($totalGrossSalary, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-danger border-4 h-100">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-receipt fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block small">Setoran PPh 21 (TER)</small>
                        <h5 class="fw-bold text-dark m-0">Rp {{ number_format($totalPph21, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-warning border-4 h-100">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning-emphasis me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-shield-halved fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block small">Total Iuran BPJS</small>
                        <h5 class="fw-bold text-dark m-0">Rp {{ number_format($totalBpjs, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: CHARTS SECTION -->
    <div class="row g-4 mb-4">
        <!-- CHART 1: TREN PENGELUARAN PAYROLL -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-chart-line text-primary me-2"></i> Tren Pengeluaran Payroll (6 Bulan Terakhir)
                    </h6>
                    <span class="badge bg-light text-muted border">Monthly Trend</span>
                </div>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="payrollTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 2: DISTRIBUSI KATEGORI KARYAWAN -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-chart-pie text-success me-2"></i> Kategori Karyawan
                    </h6>
                </div>
                <div style="position: relative; height: 220px; width: 100%;" class="d-flex align-items-center justify-content-center">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: RECENT PAYROLL PROCESSING TABLE -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-clock-rotate-left text-info me-2"></i> Status Penggajian Periode {{ $currentPeriod }}
            </h6>
            <a href="{{ route('payrolls.index') }}" class="btn btn-sm btn-link text-decoration-none fw-bold">Lihat Semua Rekap →</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted border-bottom">
                        <th>Nama Karyawan</th>
                        <th>Status Payroll</th>
                        <th>Gaji Bruto</th>
                        <th>Take Home Pay</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayrolls as $p)
                    <tr>
                        <td class="fw-semibold text-dark">{{ $p->employee->full_name }}</td>
                        <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Approved</span></td>
                        <td class="fw-bold">Rp {{ number_format($p->gross_salary, 0, ',', '.') }}</td>
                        <td class="fw-bold text-success">Rp {{ number_format($p->net_salary, 0, ',', '.') }}</td>
                        <td class="text-center">
                            <a href="{{ route('payrolls.print-pdf', $p->id_payroll) }}" target="_blank" class="btn btn-sm btn-light border text-danger" title="Cetak Slip">
                                <i class="fa-solid fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Belum ada data payroll yang diproses pada periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER STICKY -->
    <footer class="py-3 border-top bg-white rounded-4 shadow-sm mt-4">
        <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center px-4 text-muted small">
            <div>
                © {{ date('Y') }} <strong class="text-dark">PT. Batu Karang Malang</strong>. All rights reserved.
            </div>
            <div class="mt-2 mt-md-0">
                <span class="badge bg-light text-muted border me-2"><i class="fa-solid fa-circle text-success fs-8 me-1"></i> System Online</span>
                <span>Payroll System v2.0 (Laravel 13)</span>
            </div>
        </div>
    </footer>
</div>

<!-- SCRIPTS CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctxTrend = document.getElementById('payrollTrendChart').getContext('2d');
        
        // Gradient Fill
        const grossGradient = ctxTrend.createLinearGradient(0, 0, 0, 260);
        grossGradient.addColorStop(0, 'rgba(13, 110, 253, 0.3)');
        grossGradient.addColorStop(1, 'rgba(13, 110, 253, 0.0)');

        const netGradient = ctxTrend.createLinearGradient(0, 0, 0, 260);
        netGradient.addColorStop(0, 'rgba(25, 135, 84, 0.3)');
        netGradient.addColorStop(1, 'rgba(25, 135, 84, 0.0)');

        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartMonths) !!},
                datasets: [
                    {
                        label: 'Gaji Bruto (Rp)',
                        data: {!! json_encode($chartGross) !!},
                        borderColor: '#0d6efd',
                        backgroundColor: grossGradient,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#0d6efd',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Take Home Pay (Rp)',
                        data: {!! json_encode($chartNet) !!},
                        borderColor: '#198754',
                        backgroundColor: netGradient,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#198754',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, font: { weight: 'bold' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value / 1000000).toFixed(0) + ' Jt';
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. Doughnut Chart Kategori Karyawan
        const ctxCat = document.getElementById('categoryChart').getContext('2d');
        const catData = {!! json_encode($categoryDistribution) !!};
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: Object.keys(catData),
                datasets: [{
                    data: Object.values(catData),
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    });
</script>
@endsection