@extends('layouts.app')

@section('title', 'Dashboard Payroll')
@section('page_title', 'Dashboard Payroll')

@section('content')
<style>
    .dashboard-advanced { --dash-ink:#15213b; --dash-muted:#718096; --dash-line:#e8edf5; color:var(--dash-ink); }
    .dashboard-advanced .dash-hero { position:relative; overflow:hidden; color:#fff; background:linear-gradient(122deg,#132b55 0%,#1759a6 58%,#2879c7 100%); border:0; border-radius:22px; box-shadow:0 14px 35px rgba(23,89,166,.18); }
    .dashboard-advanced .dash-hero:after { content:""; position:absolute; width:260px; height:260px; border:1px solid rgba(255,255,255,.13); border-radius:50%; right:-75px; top:-125px; box-shadow:0 0 0 35px rgba(255,255,255,.035),0 0 0 75px rgba(255,255,255,.025); pointer-events:none; }
    .dashboard-advanced .dash-hero-content { position:relative; z-index:1; }
    .dashboard-advanced .dash-eyebrow { text-transform:uppercase; letter-spacing:.12em; font-size:.72rem; font-weight:800; opacity:.78; }
    .dashboard-advanced .dash-card { border:1px solid var(--dash-line); border-radius:18px; background:#fff; box-shadow:0 5px 18px rgba(21,33,59,.035); height:100%; }
    .dashboard-advanced .dash-card-body { padding:1.15rem; }
    .dashboard-advanced .dash-kpi-icon { width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; border-radius:14px; font-size:1.05rem; flex-shrink:0; }
    .dashboard-advanced .dash-kpi-label { font-size:.78rem; color:var(--dash-muted); font-weight:650; margin-bottom:.28rem; }
    .dashboard-advanced .dash-kpi-value { font-size:clamp(1.2rem,1.6vw,1.65rem); line-height:1.25; font-weight:800; letter-spacing:-.035em; color:var(--dash-ink); overflow-wrap:anywhere; }
    .dashboard-advanced .dash-kpi-foot { color:var(--dash-muted); font-size:.74rem; margin-top:.45rem; }
    .dashboard-advanced .dash-section-title { font-weight:800; font-size:1rem; letter-spacing:-.015em; color:var(--dash-ink); }
    .dashboard-advanced .dash-section-subtitle { color:var(--dash-muted); font-size:.8rem; }
    .dashboard-advanced .dash-chip { display:inline-flex; align-items:center; gap:.4rem; border:1px solid rgba(255,255,255,.22); background:rgba(255,255,255,.11); color:#fff; border-radius:999px; padding:.43rem .72rem; font-size:.76rem; font-weight:700; }
    .dashboard-advanced .dash-filter { background:#fff; border:1px solid var(--dash-line); border-radius:18px; box-shadow:0 5px 18px rgba(21,33,59,.035); }
    .dashboard-advanced .form-label { font-size:.77rem; font-weight:750; color:#53627a; }
    .dashboard-advanced .form-control, .dashboard-advanced .form-select { border-color:#dfe6f0; border-radius:11px; min-height:42px; }
    .dashboard-advanced .form-control:focus, .dashboard-advanced .form-select:focus { border-color:#76a9f5; box-shadow:0 0 0 .2rem rgba(13,110,253,.1); }
    .dashboard-advanced .dash-progress { height:9px; background:#e9eef6; border-radius:999px; overflow:hidden; }
    .dashboard-advanced .dash-progress .progress-bar { border-radius:999px; }
    .dashboard-advanced .dash-quick-link { display:flex; align-items:flex-start; gap:.85rem; height:100%; padding:1rem; border:1px solid var(--dash-line); border-radius:16px; color:inherit; text-decoration:none; background:#fff; transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease; }
    .dashboard-advanced .dash-quick-link:hover { transform:translateY(-2px); border-color:#bfd4f5; box-shadow:0 10px 25px rgba(21,33,59,.08); color:inherit; }
    .dashboard-advanced .dash-quick-icon { width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:13px; flex-shrink:0; }
    .dashboard-advanced .tone-blue { background:#e8f1ff; color:#1456b8; } .dashboard-advanced .tone-cyan { background:#e5f8ff; color:#087e9b; }
    .dashboard-advanced .tone-purple { background:#f0eaff; color:#6741b8; } .dashboard-advanced .tone-violet { background:#f6eaff; color:#8b3caa; }
    .dashboard-advanced .tone-green { background:#e3f8ed; color:#157347; } .dashboard-advanced .tone-emerald { background:#e1f7ee; color:#07835e; }
    .dashboard-advanced .tone-teal { background:#dcf8f5; color:#0b8278; } .dashboard-advanced .tone-orange { background:#fff0df; color:#b85b05; }
    .dashboard-advanced .tone-red { background:#ffeaed; color:#b42336; }
    .dashboard-advanced .dash-quick-title { font-weight:800; font-size:.88rem; margin-bottom:.25rem; }
    .dashboard-advanced .dash-quick-desc { font-size:.76rem; color:var(--dash-muted); line-height:1.45; }
    .dashboard-advanced .dash-table thead th { background:#f7f9fc; border-bottom:1px solid var(--dash-line); color:#68758b; text-transform:uppercase; letter-spacing:.045em; font-size:.68rem; font-weight:800; white-space:nowrap; padding:.8rem .85rem; }
    .dashboard-advanced .dash-table tbody td { padding:.85rem; border-color:#eef1f6; vertical-align:middle; font-size:.83rem; }
    .dashboard-advanced .dash-table tbody tr:last-child td { border-bottom:0; }
    .dashboard-advanced .dash-avatar { display:inline-flex; width:36px; height:36px; align-items:center; justify-content:center; border-radius:12px; background:#edf3fc; color:#285b9f; font-weight:800; font-size:.8rem; }
    .dashboard-advanced .dash-empty { border:1px dashed #ccd6e5; border-radius:14px; padding:1.5rem; text-align:center; color:var(--dash-muted); background:#fbfcfe; }
    .dashboard-advanced .dash-soft-note { background:#f6f9fe; border:1px solid #e5edf9; border-radius:14px; padding:.85rem 1rem; color:#4e6280; font-size:.8rem; }
    .dashboard-advanced .dash-stat-line { display:flex; justify-content:space-between; gap:1rem; padding:.6rem 0; border-bottom:1px solid #edf1f6; font-size:.84rem; }
    .dashboard-advanced .dash-stat-line:last-child { border-bottom:0; }
    .dashboard-advanced .dash-chart-wrap { position:relative; height:280px; }
    .dashboard-advanced .dash-chart-wrap-sm { position:relative; height:250px; }
    @media (max-width:767.98px) { .dashboard-advanced .dash-hero { border-radius:17px; } .dashboard-advanced .dash-card-body { padding:1rem; } .dashboard-advanced .dash-chart-wrap { height:240px; } .dashboard-advanced .dash-chart-wrap-sm { height:220px; } }
</style>

<div class="container-fluid dashboard-advanced px-0">
    @if($isEmployeeRole)
        <div class="dash-hero p-4 p-lg-5 mb-4">
            <div class="dash-hero-content d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="dash-eyebrow mb-2">Portal karyawan</div>
                    <h2 class="fw-bold mb-2">Halo, {{ $userName }}!</h2>
                    <p class="mb-0 text-white-50">Dashboard ini disiapkan untuk informasi akun pribadi Anda.</p>
                </div>
                <span class="dash-chip"><i class="fa-solid fa-user-check"></i> {{ $roleLabel }}</span>
            </div>
        </div>
        <div class="dash-card mb-4">
            <div class="dash-card-body d-flex flex-column flex-md-row align-items-start gap-3">
                <div class="dash-kpi-icon tone-blue"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1">Data perusahaan dilindungi</h5>
                    <p class="text-muted mb-2">Statistik payroll perusahaan dan data karyawan lain tidak ditampilkan di akun ini.</p>
                    <p class="text-muted small mb-0">Untuk menampilkan slip gaji pribadi di dashboard, akun login perlu ditautkan secara eksplisit ke record karyawan. Dashboard ini tidak menebak hubungan akun dan karyawan agar data tidak tertukar.</p>
                    @if(Route::has('profile.edit'))<a href="{{ route('profile.edit') }}" class="btn btn-primary mt-3"><i class="fa-solid fa-user-gear me-1"></i> Buka Profil Saya</a>@endif
                </div>
            </div>
        </div>
    @elseif($accessDenied)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold"><i class="fa-solid fa-lock me-2"></i>Akses dashboard terbatas</h5>
            Role akun ini belum dipetakan ke tampilan dashboard. Tidak ada statistik karyawan maupun finansial yang dimuat.
            <div class="mt-3"><a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-user me-1"></i> Profil Saya</a></div>
        </div>
    @else
        <div class="dash-hero p-4 p-lg-5 mb-4">
            <div class="dash-hero-content d-flex flex-column flex-xl-row align-items-start align-items-xl-center justify-content-between gap-4">
                <div>
                    <div class="dash-eyebrow mb-2">Payroll intelligence · PT Batu Karang</div>
                    <h2 class="fw-bold mb-2">Selamat datang, {{ $userName }}</h2>
                    <p class="mb-3 text-white-50" style="max-width:760px">
                        @if($isFullFinance)
                            Pantau biaya payroll, status penguncian, permintaan unlock, dan kondisi tenaga kerja dari satu tempat.
                        @elseif($isHeadHrd)
                            Pantau kondisi karyawan, kontrak mendekati akhir, dan ringkasan finansial yang sesuai batas akses level Anda.
                        @elseif($isLimitedFinance)
                            Pantau ringkasan payroll yang dapat diakses berdasarkan aturan level dan kelola tindak lanjut operasional.
                        @else
                            Pantau data karyawan, kelengkapan kontrak, serta administrasi HR yang perlu ditindaklanjuti.
                        @endif
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="dash-chip"><i class="fa-solid fa-calendar-days"></i> {{ $periodLabel }}</span>
                        <span class="dash-chip"><i class="fa-solid fa-location-dot"></i> {{ $scopeLabel }}</span>
                        <span class="dash-chip"><i class="fa-solid fa-user-shield"></i> {{ $roleLabel }}</span>
                    </div>
                </div>
                @if($canSeePayrollWorkflow)
                    <a href="{{ route('payrolls.local.create', ['period' => $period]) }}" class="btn btn-light text-primary fw-bold px-4 py-3 rounded-3 shadow-sm flex-shrink-0">
                        <i class="fa-solid fa-calculator me-2"></i> Buka Payroll Periode Ini
                    </a>
                @elseif(Route::has('employees.local.index'))
                    <a href="{{ route('employees.local.index') }}" class="btn btn-light text-primary fw-bold px-4 py-3 rounded-3 shadow-sm flex-shrink-0">
                        <i class="fa-solid fa-users me-2"></i> Buka Master Karyawan
                    </a>
                @endif
            </div>
        </div>

        @if($isFullFinance && $unlockRequestCount > 0)
            <div class="alert alert-warning border-0 rounded-4 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
                <div><strong><i class="fa-solid fa-lock-open me-2"></i>{{ $unlockRequestCount }} permintaan unlock payroll</strong><div class="small mt-1">Ada permintaan pada periode {{ $periodLabel }} yang perlu ditinjau.</div></div>
                <a href="{{ route('payrolls.local.index', ['period' => $period]) }}" class="btn btn-warning fw-bold">Tinjau Rekap Payroll <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        @endif

        <form method="GET" action="{{ route('dashboard') }}" class="dash-filter p-3 p-lg-4 mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="dashboardPeriod" class="form-label">Periode payroll</label>
                    <input id="dashboardPeriod" class="form-control" type="month" name="period" value="{{ $period }}" required>
                </div>
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="dashboardScope" class="form-label">Cakupan data</label>
                    <select id="dashboardScope" class="form-select" name="scope">
                        @foreach($scopeOptions as $scopeValue => $scopeOptionLabel)
                            <option value="{{ $scopeValue }}" @selected($scope === $scopeValue)>{{ $scopeOptionLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-xl-6 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-filter me-1"></i> Terapkan Filter</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
                    <span class="small text-muted align-self-center ms-md-auto">Grafik payroll mengikuti 6 bulan hingga periode pilihan.</span>
                </div>
            </div>
        </form>

        {{-- KPI TENAGA KERJA --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div><div class="dash-section-title">Ringkasan tenaga kerja</div><div class="dash-section-subtitle">Status master karyawan dan kontrak pada cakupan yang dipilih.</div></div>
            <span class="badge text-bg-light border text-secondary rounded-pill px-3 py-2">{{ $scopeLabel }}</span>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="dash-card"><div class="dash-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Karyawan aktif</div><div class="dash-kpi-value">{{ number_format($totalEmployees, 0, ',', '.') }}</div></div><span class="dash-kpi-icon tone-blue"><i class="fa-solid fa-users"></i></span></div>
                    <div class="dash-kpi-foot">Seluruh wilayah sesuai filter.</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="dash-card"><div class="dash-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Kontrak aktif tercatat</div><div class="dash-kpi-value">{{ number_format($employeesWithContract, 0, ',', '.') }}</div></div><span class="dash-kpi-icon tone-green"><i class="fa-solid fa-file-circle-check"></i></span></div>
                    <div class="dash-kpi-foot">Karyawan dengan riwayat kontrak aktif saat ini.</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="dash-card"><div class="dash-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Kontrak berakhir ≤ 30 hari</div><div class="dash-kpi-value {{ $contractsExpiringCount > 0 ? 'text-warning' : '' }}">{{ number_format($contractsExpiringCount, 0, ',', '.') }}</div></div><span class="dash-kpi-icon" style="background:#fff1d8;color:#a96300"><i class="fa-solid fa-calendar-xmark"></i></span></div>
                    <div class="dash-kpi-foot">Perlu ditinjau oleh HRD.</div>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="dash-card"><div class="dash-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Belum memiliki kontrak</div><div class="dash-kpi-value {{ $employeesMissingContract > 0 ? 'text-danger' : '' }}">{{ number_format($employeesMissingContract, 0, ',', '.') }}</div></div><span class="dash-kpi-icon" style="background:#ffeaed;color:#b42336"><i class="fa-solid fa-file-circle-exclamation"></i></span></div>
                    <div class="dash-kpi-foot">Karyawan aktif tanpa riwayat kontrak aktif yang terhubung.</div>
                </div></div>
            </div>
        </div>

        @if($canSeeFinancials)
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div><div class="dash-section-title">Ringkasan finansial payroll</div><div class="dash-section-subtitle">{{ $canSeeFullFinancials ? 'Angka seluruh level pada wilayah terpilih.' : ($isLimitedFinance ? 'Angka payroll Local saja, sesuai batas level ≤ 13 atau level yang belum ditentukan. Angka Outer Island tidak digabungkan.' : 'Angka hanya mencakup data yang diizinkan untuk level ≤ 13 atau level yang belum ditentukan.') }}</div></div>
                @if(!$canSeeFullFinancials)<span class="badge text-bg-light border text-secondary rounded-pill px-3 py-2"><i class="fa-solid fa-shield-halved me-1"></i> Terbatas sesuai level</span>@endif
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3"><div class="dash-card"><div class="dash-card-body"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Total bruto · {{ $periodLabel }}</div><div class="dash-kpi-value">Rp {{ number_format($totalGrossSalary, 0, ',', '.') }}</div></div><span class="dash-kpi-icon tone-blue"><i class="fa-solid fa-wallet"></i></span></div>
                    <div class="dash-kpi-foot">
                        @if(!is_null($grossSalaryChange))
                            <span class="{{ $grossSalaryChange > 0 ? 'text-danger' : ($grossSalaryChange < 0 ? 'text-success' : 'text-muted') }} fw-bold"><i class="fa-solid {{ $grossSalaryChange > 0 ? 'fa-arrow-trend-up' : ($grossSalaryChange < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }} me-1"></i>{{ number_format(abs($grossSalaryChange), 1, ',', '.') }}%</span> dibanding bulan sebelumnya.
                        @else
                            Belum ada data bulan sebelumnya untuk pembanding.
                        @endif
                    </div></div></div></div>
                <div class="col-6 col-xl-3"><div class="dash-card"><div class="dash-card-body"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Take-home pay</div><div class="dash-kpi-value">Rp {{ number_format($totalNetSalary, 0, ',', '.') }}</div></div><span class="dash-kpi-icon tone-green"><i class="fa-solid fa-hand-holding-dollar"></i></span></div><div class="dash-kpi-foot">Total gaji bersih pada periode pilihan.</div></div></div></div>
                <div class="col-6 col-xl-3"><div class="dash-card"><div class="dash-card-body"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">PPh 21</div><div class="dash-kpi-value">Rp {{ number_format($totalPph21, 0, ',', '.') }}</div></div><span class="dash-kpi-icon tone-purple"><i class="fa-solid fa-receipt"></i></span></div><div class="dash-kpi-foot">Akumulasi pemotongan PPh 21 pada periode pilihan.</div></div></div></div>
                <div class="col-6 col-xl-3"><div class="dash-card"><div class="dash-card-body"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="dash-kpi-label">Iuran BPJS</div><div class="dash-kpi-value">Rp {{ number_format($totalBpjs, 0, ',', '.') }}</div></div><span class="dash-kpi-icon" style="background:#fff1d8;color:#a96300"><i class="fa-solid fa-shield-heart"></i></span></div><div class="dash-kpi-foot">BPJS TK + BPJS Kesehatan sesuai data payroll.</div></div></div></div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="dash-card"><div class="dash-card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><div class="dash-section-title">Tren payroll bulanan</div><div class="dash-section-subtitle">Gaji bruto dibanding take-home pay, 6 bulan berakhir pada {{ $periodLabel }}.</div></div><span class="badge text-bg-light border rounded-pill">6 periode</span></div>
                        @if($monthlyTrend->sum('gross') > 0 || $monthlyTrend->sum('net') > 0)
                            <div class="dash-chart-wrap"><canvas id="payrollTrendChart" aria-label="Grafik tren payroll enam bulan"></canvas></div>
                        @else
                            <div class="dash-empty"><i class="fa-solid fa-chart-line fs-3 d-block mb-2"></i>Belum ada data payroll pada rentang enam bulan ini.</div>
                        @endif
                    </div></div>
                </div>
                <div class="col-lg-4">
                    <div class="dash-card"><div class="dash-card-body">
                        <div class="dash-section-title">Cakupan payroll</div><div class="dash-section-subtitle mb-4">Karyawan pada populasi yang dapat dilihat untuk periode {{ $periodLabel }}.</div>
                        <div class="d-flex justify-content-between align-items-end mb-2"><span class="text-muted small">Payroll tersimpan</span><strong>{{ number_format($currentPayrollEmployees, 0, ',', '.') }} / {{ number_format($payrollPopulationCount, 0, ',', '.') }}</strong></div>
                        <div class="dash-progress mb-2"><div class="progress-bar bg-primary" style="width: {{ min(100, max(0, $payrollCoveragePercent)) }}%"></div></div>
                        <div class="d-flex justify-content-between small mb-4"><span class="text-muted">Coverage</span><strong>{{ number_format($payrollCoveragePercent, 1, ',', '.') }}%</strong></div>
                        <div class="dash-stat-line"><span class="text-muted">Payroll record</span><strong>{{ number_format($currentPayrollCount, 0, ',', '.') }}</strong></div>
                        <div class="dash-stat-line"><span class="text-muted">Belum ada payroll</span><strong class="{{ $missingPayrolls > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($missingPayrolls, 0, ',', '.') }}</strong></div>
                        @if($isFullFinance)
                            <div class="dash-stat-line"><span class="text-muted">Payroll terkunci</span><strong>{{ number_format($lockedPayrollCount, 0, ',', '.') }}</strong></div>
                            <div class="dash-stat-line"><span class="text-muted">Permintaan unlock</span><strong class="{{ $unlockRequestCount > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($unlockRequestCount, 0, ',', '.') }}</strong></div>
                        @endif
                        <p class="text-muted small mt-3 mb-0">Coverage dihitung dari jumlah karyawan berbeda yang memiliki record payroll untuk periode pilihan.</p>
                    </div></div>
                </div>
            </div>
        @endif

        <div class="row g-3 mb-4">
            @if($canSeeLevelCategory)
                <div class="col-lg-5">
                    <div class="dash-card"><div class="dash-card-body">
                        <div class="dash-section-title">Distribusi kategori karyawan</div><div class="dash-section-subtitle mb-3">Kategori yang boleh ditampilkan untuk role dan level Anda.</div>
                        @if(count($categoryDistribution) > 0)
                            <div class="dash-chart-wrap-sm"><canvas id="categoryChart" aria-label="Distribusi kategori karyawan"></canvas></div>
                        @else
                            <div class="dash-empty"><i class="fa-solid fa-chart-pie fs-3 d-block mb-2"></i>Belum ada kategori yang dapat ditampilkan.</div>
                        @endif
                    </div></div>
                </div>
            @endif
            <div class="{{ $canSeeLevelCategory ? 'col-lg-7' : 'col-12' }}">
                <div class="dash-card"><div class="dash-card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><div class="dash-section-title">Kontrak yang segera berakhir</div><div class="dash-section-subtitle">Kontrak dengan tanggal berakhir dalam 30 hari ke depan.</div></div><span class="badge {{ $contractsExpiringCount > 0 ? 'text-bg-warning' : 'text-bg-light border text-secondary' }} rounded-pill px-3 py-2">{{ $contractsExpiringCount }} perlu ditinjau</span></div>
                    @if($contractsExpiring->isNotEmpty())
                        <div class="table-responsive"><table class="table dash-table mb-0"><thead><tr><th>Karyawan</th><th>Wilayah / Department</th><th>Berakhir</th><th>Sisa</th></tr></thead><tbody>
                            @foreach($contractsExpiring as $employee)
                                @php $daysLeft = now()->startOfDay()->diffInDays($employee['end_date'], false); @endphp
                                <tr>
                                    <td><div class="fw-bold">{{ $employee['name'] }}</div><div class="small text-muted">{{ $employee['job_title'] ?: 'Jabatan belum diisi' }}</div></td>
                                    <td><span class="badge text-bg-light border text-secondary mb-1">{{ $employee['region_label'] }}</span><div class="small text-muted">{{ $employee['department'] ?: 'Tanpa department' }}</div></td>
                                    <td class="fw-semibold text-nowrap">{{ $employee['end_date']->format('d M Y') }}</td>
                                    <td><span class="badge {{ $daysLeft <= 7 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ max(0, $daysLeft) }} hari</span></td>
                                </tr>
                            @endforeach
                        </tbody></table></div>
                    @else
                        <div class="dash-empty"><i class="fa-solid fa-calendar-check fs-3 d-block mb-2"></i>Tidak ada kontrak yang berakhir dalam 30 hari ke depan.</div>
                    @endif
                    @if($contractsExpiredCount > 0)
                        <div class="dash-soft-note mt-3"><i class="fa-solid fa-circle-exclamation me-2 text-warning"></i>Ada <strong>{{ $contractsExpiredCount }}</strong> karyawan aktif dengan tanggal akhir kontrak yang sudah lewat. Periksa status kontraknya untuk memastikan data mutakhir.</div>
                    @endif
                </div></div>
            </div>
        </div>

        @if($canSeePayrollWorkflow)
            <div class="dash-card mb-4"><div class="dash-card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div><div class="dash-section-title">Payroll periode {{ $periodLabel }}</div><div class="dash-section-subtitle">Record payroll terbaru sesuai cakupan dan batas akses role Anda.</div></div>
                    <div class="d-flex flex-wrap gap-2">
                        @if(Route::has('payrolls.local.index'))<a href="{{ route('payrolls.local.index', ['period' => $period]) }}" class="btn btn-sm btn-outline-primary">Rekap Local <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a>@endif
                        @if(Route::has('payrolls.outer_island.index'))<a href="{{ route('payrolls.outer_island.index', ['period' => $period]) }}" class="btn btn-sm btn-outline-primary">Rekap Outer Island <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a>@endif
                    </div>
                </div>
                @if($recentPayrolls->isNotEmpty())
                    <div class="table-responsive"><table class="table dash-table mb-0"><thead><tr><th>Karyawan</th><th>Wilayah</th><th>Status</th><th>Lock</th><th class="text-end">Gaji bruto</th><th class="text-end">Take-home pay</th><th>Diperbarui</th></tr></thead><tbody>
                        @foreach($recentPayrolls as $payrollRow)
                            @php
                                $statusText = strtolower($payrollRow['status']);
                                $statusTone = in_array($statusText, ['approved', 'complete', 'completed', 'final', 'selesai', 'approved_locked'], true) ? 'text-bg-success' : (in_array($statusText, ['draft', 'pending', 'proses', 'processing'], true) ? 'text-bg-warning' : 'text-bg-light border text-secondary');
                            @endphp
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><span class="dash-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($payrollRow['employee_name'], 0, 1)) }}</span><div><div class="fw-bold">{{ $payrollRow['employee_name'] }}</div>@if($isHeadHrd || $isLimitedFinance)<div class="small text-muted">Level: {{ $payrollRow['level'] ?? 'Belum ditentukan' }}</div>@endif</div></div></td>
                                <td><span class="badge text-bg-light border text-secondary">{{ $payrollRow['region_label'] }}</span></td>
                                <td><span class="badge {{ $statusTone }}">{{ $payrollRow['status'] }}</span></td>
                                <td>
                                    @if($isFullFinance)
                                        @if($payrollRow['unlock_requested'])<span class="badge text-bg-warning">Unlock diminta</span>@elseif($payrollRow['is_locked'])<span class="badge text-bg-success">Terkunci</span>@else<span class="badge text-bg-light border text-secondary">Terbuka</span>@endif
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap fw-semibold">Rp {{ number_format($payrollRow['gross_salary'], 0, ',', '.') }}</td>
                                <td class="text-end text-nowrap fw-bold text-success">Rp {{ number_format($payrollRow['net_salary'], 0, ',', '.') }}</td>
                                <td class="text-nowrap small text-muted">{{ $payrollRow['updated_at']?->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @else
                    <div class="dash-empty"><i class="fa-solid fa-money-check-dollar fs-3 d-block mb-2"></i>Belum ada record payroll yang dapat ditampilkan pada periode ini.</div>
                @endif
            </div></div>
        @endif

        <div class="mb-3"><div class="dash-section-title">Akses cepat</div><div class="dash-section-subtitle">Menu ditampilkan berdasarkan role akun yang sedang login.</div></div>
        @if($quickLinks->isNotEmpty())
            <div class="row g-3 mb-4">
                @foreach($quickLinks as $link)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <a href="{{ $link['url'] }}" class="dash-quick-link">
                            <span class="dash-quick-icon tone-{{ $link['tone'] }}"><i class="fa-solid {{ $link['icon'] }}"></i></span>
                            <span class="flex-grow-1"><span class="dash-quick-title d-block">{{ $link['title'] }}</span><span class="dash-quick-desc d-block">{{ $link['description'] }}</span></span>
                            <i class="fa-solid fa-arrow-up-right-from-square small text-muted mt-1"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 px-1 py-3 text-muted small border-top">
            <span><strong class="text-dark">Payroll Intelligence</strong> · Dashboard periode {{ $periodLabel }}</span>
            <span><i class="fa-solid fa-shield-halved me-1 text-success"></i>Angka finansial mengikuti hak akses role dan level.</span>
        </div>
    @endif
</div>

@if(!$isEmployeeRole && !$accessDenied && ($canSeeFinancials || $canSeeLevelCategory))
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const monthlyTrend = @json($monthlyTrend->values());
            const trendElement = document.getElementById('payrollTrendChart');
            if (trendElement && monthlyTrend.length && window.Chart) {
                const trendContext = trendElement.getContext('2d');
                const grossGradient = trendContext.createLinearGradient(0, 0, 0, 280);
                grossGradient.addColorStop(0, 'rgba(37, 99, 235, .22)');
                grossGradient.addColorStop(1, 'rgba(37, 99, 235, 0)');
                const netGradient = trendContext.createLinearGradient(0, 0, 0, 280);
                netGradient.addColorStop(0, 'rgba(22, 163, 74, .18)');
                netGradient.addColorStop(1, 'rgba(22, 163, 74, 0)');

                new Chart(trendContext, {
                    type: 'line',
                    data: {
                        labels: monthlyTrend.map(item => item.label),
                        datasets: [
                            { label: 'Gaji Bruto', data: monthlyTrend.map(item => item.gross), borderColor: '#2563eb', backgroundColor: grossGradient, fill: true, borderWidth: 3, tension: .35, pointRadius: 3, pointHoverRadius: 6 },
                            { label: 'Take-home Pay', data: monthlyTrend.map(item => item.net), borderColor: '#16a34a', backgroundColor: netGradient, fill: true, borderWidth: 3, tension: .35, pointRadius: 3, pointHoverRadius: 6 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, padding: 18 } },
                            tooltip: { callbacks: { label: context => `${context.dataset.label}: Rp ${new Intl.NumberFormat('id-ID').format(context.raw || 0)}` } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.12)' }, ticks: { callback: value => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(value) } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            const categoryElement = document.getElementById('categoryChart');
            const categoryData = @json($categoryDistribution);
            if (categoryElement && Object.keys(categoryData).length && window.Chart) {
                new Chart(categoryElement.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(categoryData),
                        datasets: [{ data: Object.values(categoryData), backgroundColor: ['#2563eb','#16a34a','#f59e0b','#8b5cf6','#06b6d4','#f43f5e','#64748b'], borderWidth: 3, borderColor: '#fff', hoverOffset: 7 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, padding: 14 } } } }
                });
            }
        });
    </script>
@endif
@endsection
