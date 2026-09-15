<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Payroll 2.0 PT Batu Karang</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom App CSS -->
    <link rel="stylesheet" href="{{ asset('css/app-style.css') }}">
    
    @stack('styles')
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar-wrapper" id="sidebarWrapper">
        <div class="sidebar-brand">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; min-width: 38px;">
                    <i class="fa-solid fa-gem text-primary fs-5"></i>
                </div>
                <div class="brand-text">
                    <h6 class="fw-bold text-white m-0 tracking-wide" style="font-size: 0.95rem;">BATU KARANG</h6>
                    <span class="badge bg-info text-dark fw-bold px-2 py-0" style="font-size: 0.65rem;">PAYROLL 2.0</span>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <!-- GROUP 1: OVERVIEW -->
            <div class="menu-header">Main Menu</div>
            <a href="{{ route('dashboard') }}" class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <!-- GROUP 2: MASTER KARYAWAN -->
            <div class="menu-header mt-3">Master Karyawan</div>

            <!-- 1. WILAYAH MALANG -->
            @php
                $isMalangActive = request()->routeIs('employees.malang.*') || request()->routeIs('contracts.malang.*');
            @endphp
            <a class="nav-link-custom d-flex align-items-center justify-content-between {{ $isMalangActive ? '' : 'collapsed' }}" 
            data-bs-toggle="collapse" href="#menuMalang" role="button" aria-expanded="{{ $isMalangActive ? 'true' : 'false' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-city"></i>
                    <span>Karyawan Malang</span>
                </div>
                <i class="fa-solid fa-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse {{ $isMalangActive ? 'show' : '' }} ms-3 ps-2 border-start border-white border-opacity-25" id="menuMalang">
                <a href="{{ route('employees.local.index') }}" class="nav-link-custom py-2 my-1 {{ request()->routeIs('employees.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-user me-2"></i><span>Data Diri</span>
                </a>
                <a href="{{ route('contracts.local.index') }}" class="nav-link-custom py-2 my-1 {{ request()->routeIs('contracts.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-signature me-2"></i><span>Penempatan & Kontrak</span>
                </a>
            </div>

            <!-- 2. WILAYAH LUAR PULAU -->
            @php
                $isLuarPulauActive = request()->routeIs('employees.luar.*') || request()->routeIs('contracts.luar.*');
            @endphp
            <a class="nav-link-custom d-flex align-items-center justify-content-between mt-1 {{ $isLuarPulauActive ? '' : 'collapsed' }}" 
            data-bs-toggle="collapse" href="#menuLuarPulau" role="button" aria-expanded="{{ $isLuarPulauActive ? 'true' : 'false' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-plane-departure"></i>
                    <span>Karyawan Luar Pulau</span>
                </div>
                <i class="fa-solid fa-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse {{ $isLuarPulauActive ? 'show' : '' }} ms-3 ps-2 border-start border-white border-opacity-25" id="menuLuarPulau">
                <a href="#" class="nav-link-custom py-2 my-1">
                    <i class="fa-solid fa-user me-2"></i><span>Data Diri</span>
                </a>
                <a href="#" class="nav-link-custom py-2 my-1">
                    <i class="fa-solid fa-file-signature me-2"></i><span>Penempatan & Kontrak</span>
                </a>
            </div>

            <!-- GROUP 3: PENGGAJIAN & PERPAJAKAN -->
            <div class="menu-header mt-3">Penggajian & Tax</div>
                <!-- 1. INPUT ABSENSI (COLLAPSE) -->

                @php
                    $isAbsensiActive = request()->routeIs('payrolls.local.create');
                @endphp

                <a class="nav-link-custom d-flex align-items-center justify-content-between {{ $isAbsensiActive ? '' : 'collapsed' }}"
                data-bs-toggle="collapse"
                href="#menuAbsensi"
                role="button"
                aria-expanded="{{ $isAbsensiActive ? 'true' : 'false' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Input Absensi</span>
                </div>

                <i class="fa-solid fa-chevron-down small transition-icon"></i>
                </a>

                <div class="collapse {{ $isAbsensiActive ? 'show' : '' }} ms-3 ps-2 border-start border-white border-opacity-25"
                    id="menuAbsensi">
                <!-- ABSENSI LOCAL -->
                <a href="{{ route('payrolls.local.create') }}"
                class="nav-link-custom py-2 my-1 {{ request()->routeIs('payrolls.local.create') ? 'active' : '' }}">

                    <i class="fa-solid fa-location-dot me-2"></i>
                    <span>Absensi Malang</span>
                </a>

                <!-- ABSENSI OUTER ISLAND -->
                <!-- Route akan dibuat nanti ketika modul Outer Island sudah dibuat -->
                <a href="#"
                class="nav-link-custom py-2 my-1">

                    <i class="fa-solid fa-plane-departure me-2"></i>
                    <span>Absensi Luar Pulau</span>
                </a>
                </div>


            <!-- 2. PROCESS PAYROLL (COLLAPSE / TERPISAH) -->
            @php
                $isPayrollActive = request()->routeIs('payrolls.*') && !request()->routeIs('absensi.*');
            @endphp
            <a class="nav-link-custom d-flex align-items-center justify-content-between mt-1 {{ $isPayrollActive ? '' : 'collapsed' }}" 
            data-bs-toggle="collapse" href="#menuPayroll" role="button" aria-expanded="{{ $isPayrollActive ? 'true' : 'false' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Process Payroll</span>
                </div>
                <i class="fa-solid fa-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse {{ $isPayrollActive ? 'show' : '' }} ms-3 ps-2 border-start border-white border-opacity-25" id="menuPayroll">
                <a href="{{ route('payrolls.local.index', ['region' => 'malang']) }}" class="nav-link-custom py-2 my-1">
                    <i class="fa-solid fa-location-dot me-2"></i><span>Payroll Malang</span>
                </a>
                <a href="#" class="nav-link-custom py-2 my-1">
                    <i class="fa-solid fa-plane-departure me-2"></i><span>Payroll Luar Pulau</span>
                </a>
            </div>

            <!-- 3. PPH 21 & BPJS (TETAP TUNGGAL) -->
            <a href="{{ route('tax-bpjs.index') }}" class="nav-link-custom mt-1 {{ request()->routeIs('tax-bpjs.*') ? 'active' : '' }}" title="PPh 21 & BPJS Master">
                <i class="fa-solid fa-calculator"></i>
                <span>PPh 21 & BPJS Master</span>
            </a>

            <!-- GROUP 4: PERSONAL ACCOUNT  -->
            <div class="menu-header mt-3">Personal Account</div>

            <!-- HANYA TAMPIL UNTUK MANAGER KEUANGAN -->
            @if(Auth::check() && Auth::user()->role === 'manager_keuangan')
                <a href="{{ route('users.index') }}" class="nav-link-custom {{ request()->routeIs('users.*') ? 'active' : '' }}" title="Kelola Pengguna">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Kelola Pengguna</span>
                </a>
            @endif

            <a href="{{ route('profile.edit') }}" class="nav-link-custom {{ request()->routeIs('profile.*') ? 'active' : '' }}" title="Pengaturan Akun">
                <i class="fa-solid fa-sliders"></i>
                <span>Pengaturan Akun</span>
            </a>
        </div>
    </aside>

    <!-- TOPBAR NAVBAR -->
    <header class="topbar-wrapper">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-light rounded-3 border border-secondary border-opacity-25 px-2 py-1 text-secondary" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>
            <h5 class="fw-bold m-0 text-dark">@yield('page_title', 'Overview')</h5>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- TOPBAR AVATAR DROPDOWN -->
            <div class="dropdown">
                <button class="btn btn-light rounded-pill border border-secondary border-opacity-25 px-2 py-1 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    @if(Auth::user() && Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="rounded-circle object-fit-cover border" style="width: 32px; height: 32px;">
                    @else
                        <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            {{ substr(Auth::user()->name ?? 'BK', 0, 2) }}
                        </div>
                    @endif
                    <span class="fw-medium small text-dark me-1">{{ Auth::user()->name ?? 'Admin Payroll' }}</span>
                    <i class="fa-solid fa-chevron-down small text-muted me-1"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3 mt-2">
                    <li><a class="dropdown-item small py-2" href="{{ route('profile.edit') }}"><i class="fa-solid fa-user-gear me-2 text-muted"></i> Pengaturan Akun</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item small py-2 text-danger w-100 border-0 bg-transparent text-start">
                                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        @yield('content')
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SCRIPT TOGGLE MINI SIDEBAR -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('sidebarToggle');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    document.body.classList.toggle('mini-sidebar');
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>