@extends('layouts.app')

@section('title', 'Master Data Diri Karyawan Luar Pulau')
@section('page_title', 'Master Data Diri Karyawan Luar Pulau')

@section('content')
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
    <!-- HEADER BARIS TOMBOL AKSI -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-users text-primary me-2"></i> Daftar Master Karyawan Luar Pulau
            </h5>
            <small class="text-muted">Data identitas pribadi, kontak, alamat & rekening payroll karyawan PT Batu Karang Luar Pulau</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- TOMBOL EXPORT CSV -->
            <a href="{{ route('employees.outer_island.export') }}" class="btn btn-outline-secondary px-3 py-2 rounded-3 fw-semibold">
                <i class="fa-solid fa-file-export me-1"></i> Export CSV
            </a>
            <!-- TOMBOL IMPOR EXCEL -->
            <button type="button" class="btn btn-outline-success px-3 py-2 rounded-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> Impor Excel
            </button>
            <!-- TOMBOL TAMBAH MANUAL -->
            <a href="{{ route('employees.outer_island.create') }}" class="btn btn-primary px-3 py-2 rounded-3 fw-semibold">
                <i class="fa-solid fa-user-plus me-1"></i> Tambah Karyawan
            </a>
        </div>
    </div>

    <!-- BARIS FITUR SEARCH & FILTER -->
    <div class="row g-2 mb-3">
        <div class="col-md-5 col-lg-4">
            <form action="{{ route('employees.outer_island.index') }}" method="GET">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3">
                        <i class="fa-solid fa-magnifying-glass text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari Nama, NIK, Email, No HP..." value="{{ request('search') }}">
                    <button class="btn btn-primary rounded-end-3" type="submit">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('employees.outer_island.index') }}" class="btn btn-outline-secondary rounded-3 ms-1" title="Reset Pencarian">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- NOTIFIKASI / ALERT ERROR JIKA GAGAL -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 auto-dismiss-alert" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- NOTIFIKASI / ALERT SUCCESS JIKA BERHASIL -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 auto-dismiss-alert" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- TABEL KARYAWAN LUAR PULAU -->
    <div class="table-responsive">
        <table class="table table-hover align-middle border-top">
            <thead class="table-light">
                <tr class="small text-muted">
                    <th class="py-3 text-center" style="width: 50px;">No</th>
                    <th class="py-3 text-center" style="width: 80px;">Foto KTP</th>
                    <th class="py-3">NIK KTP</th>
                    <th class="py-3">Nama Lengkap</th>
                    <th class="py-3">Kontak</th>
                    <th class="py-3">Alamat KTP</th>
                    <th class="py-3">Rekening Bank</th>
                    <th class="py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $index => $emp)
                @php
                    $ktpPath = $emp->ktp_path_outer ?? $emp->ktp_path ?? null;
                    $fullName = $emp->full_name_outer ?? $emp->full_name ?? '';
                    $nikKtp = $emp->nik_ktp_outer ?? $emp->nik_ktp ?? '';
                    $gender = $emp->gender_outer ?? $emp->gender ?? 'L';
                    $marital = $emp->marital_status_outer ?? $emp->marital_status ?? 'single';
                    $phone = $emp->phone_number_outer ?? $emp->phone_number ?? null;
                    $email = $emp->email_outer ?? $emp->email ?? null;
                    $addressKtp = $emp->address_ktp_outer ?? $emp->address_ktp ?? '-';
                    $bankName = $emp->bank_name_outer ?? $emp->bank_name ?? null;
                    $bankAccount = $emp->bank_account_number_outer ?? $emp->bank_account_number ?? null;
                @endphp
                <tr>
                    <!-- 1. LOOPING NOMOR URUT -->
                    <td class="text-center fw-semibold text-muted">
                        {{ $employees->firstItem() ? $employees->firstItem() + $index : $index + 1 }}
                    </td>

                    <!-- 2. THUMBNAIL FOTO KTP & PREVIEW TRIGGER -->
                    <td class="text-center">
                        @if($ktpPath && Storage::disk('public')->exists($ktpPath))
                            <button type="button" 
                                    class="btn btn-link p-0 border-0" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalPreviewKtp-{{ $emp->id_employee_outer ?? $emp->id ?? $loop->index }}"
                                    title="Klik untuk memperbesar Foto KTP">
                                <img src="{{ asset('storage/' . $ktpPath) }}" 
                                     alt="KTP {{ $fullName }}" 
                                     class="rounded-2 border shadow-sm object-fit-cover" 
                                     style="width: 48px; height: 36px; cursor: pointer;">
                            </button>

                            <!-- MODAL POPUP PREVIEW KTP (PER KARYAWAN) -->
                            <div class="modal fade" id="modalPreviewKtp-{{ $emp->id_employee_outer ?? $emp->id ?? $loop->index }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header border-bottom">
                                            <h6 class="modal-title fw-bold text-dark">
                                                <i class="fa-solid fa-id-card text-primary me-2"></i> Foto KTP - {{ $fullName }}
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-3 text-center bg-light">
                                            <img src="{{ asset('storage/' . $ktpPath) }}" 
                                                 alt="KTP {{ $fullName }}" 
                                                 class="img-fluid rounded-3 shadow-sm border" 
                                                 style="max-height: 500px;">
                                        </div>
                                        <div class="modal-footer border-top bg-white">
                                            <a href="{{ asset('storage/' . $ktpPath) }}" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Ukuran Asli
                                            </a>
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Placeholder jika belum ada foto KTP -->
                            <span class="badge bg-light text-secondary border px-2 py-1 small" title="Foto KTP belum diunggah">
                                <i class="fa-solid fa-image-slash"></i> No Foto
                            </span>
                        @endif
                    </td>

                    <!-- 3. NIK KTP -->
                    <td class="fw-medium text-dark">{{ $nikKtp }}</td>

                    <!-- 4. NAMA LENGKAP -->
                    <td>
                        <div class="fw-bold text-dark">{{ $fullName }}</div>
                        <small class="text-muted">{{ $gender == 'L' ? 'Laki-laki' : 'Perempuan' }} | {{ ucfirst($marital) }}</small>
                    </td>

                    <!-- 5. KONTAK -->
                    <td>
                        <div><i class="fa-solid fa-phone text-muted me-1 small"></i> {{ $phone ?? '-' }}</div>
                        <small class="text-muted"><i class="fa-solid fa-envelope me-1 small"></i> {{ $email ?? '-' }}</small>
                    </td>

                    <!-- 6. ALAMAT KTP (PENGGANTI WILAYAH) -->
                    <td>
                        <div class="small fw-semibold text-dark text-wrap" style="max-width: 220px;">
                            {{ $addressKtp }}
                        </div>
                    </td>

                    <!-- 7. REKENING BANK -->
                    <td>
                        <div class="fw-semibold text-dark">{{ $bankName ?? '-' }}</div>
                        <small class="text-muted">{{ $bankAccount ?? '-' }}</small>
                    </td>

                    <!-- 8. AKSI -->
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="{{ route('employees.outer_island.edit', $emp->uuid) }}" class="btn btn-sm btn-outline-warning rounded-2" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            
                            <form action="{{ route('employees.outer_island.destroy', $emp->uuid) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data karyawan luar pulau ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-2" title="Hapus">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-user-slash fs-2 mb-2 d-block text-secondary"></i>
                        Data karyawan luar pulau tidak ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PAGINATION BARIS BAWAH -->
    <div class="d-flex justify-content-between align-items-center flex-wrap mt-4 pt-3 border-top">
        <div class="small text-muted mb-2 mb-md-0">
            Menampilkan <strong>{{ $employees->firstItem() ?? 0 }}</strong> sampai <strong>{{ $employees->lastItem() ?? 0 }}</strong> dari <strong>{{ $employees->total() }}</strong> karyawan
        </div>
        <div>
            {{ $employees->links() }}
        </div>
    </div>
</div>

<!-- MODAL POPUP IMPORT EXCEL / CSV -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalImportExcelLabel">
                    <i class="fa-solid fa-file-excel text-success me-2"></i> Impor Master Karyawan Luar Pulau
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('employees.outer_island.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i> Gunakan format template resmi agar susunan kolom data terbaca dengan sempurna oleh sistem.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Download Format Template</label>
                        <div>
                            <a href="{{ route('employees.outer_island.download-template') }}" class="btn btn-sm btn-light border text-primary fw-semibold rounded-2">
                                <i class="fa-solid fa-download me-1"></i> Download Template CSV/Excel
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Pilih File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file_excel" class="form-control" accept=".xlsx, .xls, .csv, .txt" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">
                        <i class="fa-solid fa-upload me-1"></i> Upload & Impor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT AUTO-DISMISS ALERT SETELAH 4 DETIK -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            const alerts = document.querySelectorAll('.auto-dismiss-alert');
            alerts.forEach(function (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 4000);
    });
</script>
@endsection