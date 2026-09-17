@extends('layouts.app')

@section('title', 'Kelola Pengguna Aplikasi')
@section('page_title', 'Kelola Pengguna & Hak Akses')

@section('content')
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h5 class="fw-bold text-dark m-0"><i class="fa-solid fa-users-gear text-primary me-2"></i> Pengaturan Akses User</h5>
            <small class="text-muted">Daftar pengguna yang memiliki hak akses login ke Sistem Payroll PT Batu Karang</small>
        </div>
        <button type="button" class="btn btn-primary px-3 py-2 rounded-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="fa-solid fa-user-plus me-1"></i> Tambah User Login
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 auto-dismiss-alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3 auto-dismiss-alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Alert jika ada error validasi formulir --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> Terdapat kesalahan pada pengisian form. Silakan periksa kembali.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle border-top">
            <thead class="table-light">
                <tr class="small text-muted">
                    <th>Pengguna</th>
                    <th>Email Login</th>
                    <th>Level / Role Akses</th>
                    <th>Terhubung Karyawan</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <!-- PROTEKSI: JIKA SUPER ADMIN, JANGAN RENDER BARISNYA -->
                @if($u->role === 'super_admin') @continue @endif

                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($u->avatar_url)
                                <img src="{{ $u->avatar_url }}" class="rounded-circle object-fit-cover" style="width: 36px; height: 36px;">
                            @else
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    {{ strtoupper(substr($u->name, 0, 2)) }}
                                </div>
                            @endif
                            <div class="fw-bold text-dark">{{ $u->name }}</div>
                        </div>
                    </td>
                    <td>{{ $u->email }}</td>
                    <td>
                        @if($u->role == 'manager_keuangan')
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1">Manager Keuangan</span>
                        @elseif($u->role == 'finance')
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">Staff Keuangan</span>
                        @elseif($u->role == 'head_hrd')
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">Kepala HRD</span>
                        @elseif($u->role == 'hrd')
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">HRD Staff</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3 py-1">Karyawan</span>
                        @endif
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $u->employee->full_name ?? '-' }}</span>
                        <small class="text-muted d-block">(NIK: {{ $u->employee->nik_ktp ?? '-' }})</small>
                    </td>
                    <td class="text-center">
                        @if($u->id !== auth()->id())
                            <form action="{{ route('users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun login ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Hapus User"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        @else
                            <span class="badge bg-light text-muted border">Akun Anda</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">Belum ada pengguna terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
</div>

<!-- MODAL TAMBAH USER LOGIN -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-primary me-2"></i> Tambah Akun Akses Karyawan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pilih Karyawan</label>
                        <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Master Karyawan --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id_employee }}" {{ old('employee_id') == $emp->id_employee ? 'selected' : '' }}>
                                    {{ $emp->full_name }} (NIK: {{ $emp->nik_ktp }})
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1">Hanya menampilkan karyawan yang belum memiliki akun login.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Alamat Email Login</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="nama@batukarang.com" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Role / Hak Akses</label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="" disabled {{ old('role') ? '' : 'selected' }}>-- Pilih Hak Akses --</option>
                            <optgroup label="Divisi Keuangan">
                                <option value="manager_keuangan" {{ old('role') == 'manager_keuangan' ? 'selected' : '' }}>Manager Keuangan (Approval & Lock Payroll)</option>
                                <option value="finance" {{ old('role') == 'finance' ? 'selected' : '' }}>Staff Keuangan (Export BCA & Rekap Gaji)</option>
                            </optgroup>
                            <optgroup label="Divisi HRD">
                                <option value="head_hrd" {{ old('role') == 'head_hrd' ? 'selected' : '' }}>Kepala HRD (Approval Master Data & Request Unlock)</option>
                                <option value="hrd" {{ old('role') == 'hrd' ? 'selected' : '' }}>HRD Staff (Input Master Karyawan & Kontrak)</option>
                            </optgroup>
                            <optgroup label="Lainnya">
                                <option value="karyawan" {{ old('role') == 'karyawan' ? 'selected' : '' }}>Karyawan Biasa (Akses Slip Gaji Pribadi)</option>
                            </optgroup>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password Default</label>
                        <!-- Penambahan minlength="6" dan penanganan error validasi Laravel -->
                        <input type="password" 
                               name="password" 
                               class="form-control @error('password') is-invalid @enderror" 
                               placeholder="Minimal 6 karakter" 
                               minlength="6" 
                               required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text small text-muted">Password minimal terdiri dari 6 karakter.</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Buat Akun Login</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
@if ($errors->any())
<script>
    // Otomatis membuka kembali Modal jika ada error validasi dari server (Laravel Controller)
    document.addEventListener("DOMContentLoaded", function() {
        var modalTambahUser = new bootstrap.Modal(document.getElementById('modalTambahUser'));
        modalTambahUser.show();
    });
</script>
@endif
@endpush
@endsection