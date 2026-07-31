<style>
    .pagination svg, 
    nav svg {
        width: 1rem !important;
        height: 1rem !important;
    }
</style>
@extends('layouts.app')

@section('title', 'Dashboard Overview')
@section('page_title', 'Dashboard Overview')

@section('content')
<!-- BARIS RINGKASAN STATISTIK SEMENTARA -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card-custom p-4 d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                <i class="fa-solid fa-users fs-3"></i>
            </div>
            <div>
                <div class="text-muted small fw-medium">Total Master Karyawan</div>
                <h3 class="fw-bold m-0 text-dark">0</h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom p-4 d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                <i class="fa-solid fa-building-user fs-3"></i>
            </div>
            <div>
                <div class="text-muted small fw-medium">Active Site Placement</div>
                <h3 class="fw-bold m-0 text-dark">PT Batu Karang</h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom p-4 d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                <i class="fa-solid fa-shield-halved fs-3"></i>
            </div>
            <div>
                <div class="text-muted small fw-medium">Status System</div>
                <span class="badge bg-success px-3 py-2 rounded-pill fw-semibold">Ready v2.0</span>
            </div>
        </div>
    </div>
</div>

<!-- CARD WELCOME & QUICK LINK -->
<div class="card-custom p-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h5 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-gem text-primary me-2"></i>
                Sistem Payroll 2.0 PT Batu Karang
            </h5>
            <p class="text-muted m-0 small">Modul terpadu pengelolaan Data Diri Master Karyawan, Jabatan, Site Placement, PPh 21, dan BPJS.</p>
        </div>
        <a href="{{ route('employees.index') }}" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold">
            <i class="fa-solid fa-user-gear me-2"></i> Kelola Master Karyawan
        </a>
    </div>
</div>
@endsection