@extends('layouts.app')

@section('title', 'Tambah Master Karyawan')
@section('page_title', 'Tambah Master Karyawan Baru')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h5 class="fw-bold text-dark m-0">Form Data Diri Master Karyawan</h5>
        <small class="text-muted">Isi kelengkapan dokumen identitas pribadi, berkas KTP & rekening payroll</small>
    </div>
    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<form action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    
    @include('employees._form')

    <div class="col-12 text-end my-4">
        <a href="{{ route('employees.index') }}" class="btn btn-light border px-4 me-2">Batal</a>
        <button type="submit" class="btn btn-primary px-5 fw-bold">
            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Karyawan
        </button>
    </div>
</form>
@endsection