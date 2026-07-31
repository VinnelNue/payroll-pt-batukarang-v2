@extends('layouts.app')

@section('title', 'Master Data Diri Karyawan')
@section('page_title', 'Master Data Diri Karyawan')

@section('content')
<div class="card-custom p-4 mb-4">
    <!-- HEADER BARIS TOMBOL AKSI -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-users text-primary me-2"></i> Daftar Master Karyawan
            </h5>
            <small class="text-muted">Data identitas pribadi, kontak, alamat & rekening payroll karyawan PT Batu Karang</small>
        </div>
        <div class="d-flex gap-2">
            <!-- TOMBOL IMPOR EXCEL -->
            <button type="button" class="btn btn-outline-success px-3 py-2 rounded-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> Impor Excel
            </button>
            <!-- TOMBOL TAMBAH MANUAL -->
            <a href="{{ route('employees.create') }}" class="btn btn-primary px-3 py-2 rounded-3 fw-semibold">
                <i class="fa-solid fa-user-plus me-1"></i> Tambah Karyawan
            </a>
        </div>
    </div>

    <!-- ALERT ERROR JIKA IMPOR GAGAL -->
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- TABEL KARYAWAN -->
    <div class="table-responsive">
        <table class="table table-hover align-middle border-top">
            <thead class="table-light">
                <tr>
                    <th class="py-3 text-center" style="width: 50px;">No</th>
                    <th class="py-3 text-center" style="width: 80px;">Foto KTP</th>
                    <th class="py-3">NIK KTP</th>
                    <th class="py-3">Nama Lengkap</th>
                    <th class="py-3">Kontak</th>
                    <th class="py-3">Wilayah</th>
                    <th class="py-3">Rekening Bank</th>
                    <th class="py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $index => $emp)
                <tr>
                    <!-- 1. LOOPING NOMOR URUT -->
                    <td class="text-center fw-semibold text-muted">
                        {{ $employees->firstItem() ? $employees->firstItem() + $index : $index + 1 }}
                    </td>

                    <!-- 2. THUMBNAIL FOTO KTP & PREVIEW TRIGGER -->
                    <td class="text-center">
                        @if($emp->ktp_path && Storage::disk('public')->exists($emp->ktp_path))
                            <button type="button" 
                                    class="btn btn-link p-0 border-0" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalPreviewKtp-{{ $emp->id_employee ?? $loop->index }}"
                                    title="Klik untuk memperbesar Foto KTP">
                                <img src="{{ asset('storage/' . $emp->ktp_path) }}" 
                                     alt="KTP {{ $emp->full_name }}" 
                                     class="rounded-2 border shadow-sm object-fit-cover" 
                                     style="width: 48px; height: 36px; cursor: pointer;">
                            </button>

                            <!-- MODAL POPUP PREVIEW KTP (PER KARYAWAN) -->
                            <div class="modal fade" id="modalPreviewKtp-{{ $emp->id_employee ?? $loop->index }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header border-bottom">
                                            <h6 class="modal-title fw-bold text-dark">
                                                <i class="fa-solid fa-id-card text-primary me-2"></i> Foto KTP - {{ $emp->full_name }}
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-3 text-center bg-light">
                                            <img src="{{ asset('storage/' . $emp->ktp_path) }}" 
                                                 alt="KTP {{ $emp->full_name }}" 
                                                 class="img-fluid rounded-3 shadow-sm border" 
                                                 style="max-height: 500px;">
                                        </div>
                                        <div class="modal-footer border-top bg-white">
                                            <a href="{{ asset('storage/' . $emp->ktp_path) }}" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold">
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

                    <td class="fw-medium text-dark">{{ $emp->nik_ktp }}</td>
                    <td>
                        <div class="fw-bold text-dark">{{ $emp->full_name }}</div>
                        <small class="text-muted">{{ $emp->gender == 'L' ? 'Laki-laki' : 'Perempuan' }} | {{ ucfirst($emp->marital_status) }}</small>
                    </td>
                    <td>
                        <div><i class="fa-solid fa-phone text-muted me-1 small"></i> {{ $emp->phone_number ?? '-' }}</div>
                        <small class="text-muted"><i class="fa-solid fa-envelope me-1 small"></i> {{ $emp->email ?? '-' }}</small>
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark">{{ $emp->city->name ?? '-' }}</div>
                        <small class="text-muted">{{ $emp->province->name ?? '-' }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $emp->bank_name ?? '-' }}</div>
                        <small class="text-muted">{{ $emp->bank_account_number ?? '-' }}</small>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="{{ route('employees.edit', $emp->uuid) }}" class="btn btn-sm btn-outline-warning rounded-2" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <form action="{{ route('employees.destroy', $emp->uuid) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data karyawan ini?');">
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
                        Belum ada data karyawan. Klik **Impor Excel** atau **Tambah Karyawan**.
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
</div> <!-- Penutup card-custom -->

<!-- MODAL POPUP IMPORT EXCEL / CSV -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalImportExcelLabel">
                    <i class="fa-solid fa-file-excel text-success me-2"></i> Impor Master Karyawan
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('employees.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i> Gunakan format template resmi agar susunan kolom data terbaca dengan sempurna oleh sistem.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Download Format Template</label>
                        <div>
                            <a href="{{ route('employees.download-template') }}" class="btn btn-sm btn-light border text-primary fw-semibold rounded-2">
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
@endsection