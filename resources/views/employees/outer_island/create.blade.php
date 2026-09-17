@extends('layouts.app')

@section('title', 'Tambah Master Karyawan Baru Luar Pulau')
@section('page_title', 'Tambah Data Karyawan Baru Luar Pulau')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h5 class="fw-bold text-dark m-0">Form Data Diri Master Karyawan Baru Luar Pulau</h5>
        <small class="text-muted">Isi kelengkapan dokumen identitas pribadi, berkas KTP & rekening payroll Baru Luar Pulau</small>
    </div>
    <a href="{{ route('employees.outer_island.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<form action="{{ route('employees.outer_island.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    
    @include('employees.outer_island._form')

    <div class="col-12 text-end my-4">
        <a href="{{ route('employees.outer_island.index') }}" class="btn btn-light border px-4 me-2">Batal</a>
        <button type="submit" class="btn btn-primary px-5 fw-bold">
            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Karyawan
        </button>
    </div>
</form>
@endsection