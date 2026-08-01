@extends('layouts.app')

@section('title', 'Personal Account Settings')
@section('page_title', 'Informasi & Pengaturan Akun')

@section('content')
<div class="container-fluid p-0">
    <!-- ALERT SUCCESS -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 auto-dismiss-alert" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- ALERT ERROR -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 auto-dismiss-alert" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- LEFT SIDEBAR: RINGKASAN PROFIL AKUN -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center">
                <!-- AVATAR / INITIALS -->
                <div class="position-relative d-inline-block mx-auto mb-3">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="rounded-circle object-fit-cover border shadow-sm" style="width: 100px; height: 100px;">
                    @else
                        <div class="mx-auto bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px; font-size: 2.2rem;">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    @endif
                </div>

                <h5 class="fw-bold text-dark m-0">{{ $user->name }}</h5>
                <p class="text-muted small mb-2">{{ $user->email }}</p>
                
                <!-- BADGE ROLE DINAMIS -->
                <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 py-1 mb-3">
                    {{ ucfirst(str_replace('_', ' ', $user->role ?? 'User')) }}
                </span>

                <!-- DATA SUMMARY DARI KARYAWAN (READ-ONLY) -->
                <div class="text-start p-3 bg-light rounded-3 small text-muted">
                    <div class="mb-2">
                        <i class="fa-solid fa-id-card me-2 text-primary"></i> 
                        <strong>NIK KTP:</strong> {{ $user->employee->nik_ktp ?? '-' }}
                    </div>
                    <div class="mb-2">
                        <i class="fa-solid fa-phone me-2 text-primary"></i> 
                        <strong>WhatsApp:</strong> {{ $user->employee->phone_number ?? '-' }}
                    </div>
                    <div class="mb-2">
                        <i class="fa-solid fa-building me-2 text-primary"></i> 
                        <strong>Placement:</strong> {{ $user->employee->activeContract->site_placement ?? 'PT Batu Karang Malang' }}
                    </div>
                    <div>
                        <i class="fa-solid fa-credit-card me-2 text-primary"></i> 
                        <strong>Rekening Payroll:</strong> {{ $user->employee->bank_name ?? '-' }} - {{ $user->employee->bank_account_number ?? '-' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT FORM: UPLOAD FOTO PROFIL & GANTI PASSWORD -->
        <div class="col-lg-8">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- SECTION 1: UPLOAD FOTO PROFIL (AVATAR USER) -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fa-solid fa-camera text-primary me-2"></i> Foto Profil Personal
                    </h6>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Unggah Foto Profil Baru</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        <small class="text-muted mt-1 d-block">Format: JPG, PNG, WEBP (Maksimal 2 MB). Foto ini khusus untuk profil akun & topbar kanan atas.</small>
                    </div>
                </div>

                <!-- SECTION 2: GANTI PASSWORD AKUN LOGIN -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fa-solid fa-lock text-danger me-2"></i> Ganti Password Akun Login
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Password Saat Ini</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Masukkan password lama untuk verifikasi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Password Baru</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Konfirmasi Password Baru</label>
                            <input type="password" name="new_password_confirmation" class="form-control" placeholder="Ulangi password baru">
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection