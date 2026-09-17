@extends('layouts.app')

@section('title', 'Edit Master Karyawan Luar Pulau')
@section('page_title', 'Edit Data Diri Karyawan Luar Pulau')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h5 class="fw-bold text-dark m-0">Edit Data Diri: {{ $employee->full_name_outer }}</h5>
        <small class="text-muted">Perbarui data identitas pribadi, berkas KTP atau rekening payroll karyawan Luar Pulau</small>
    </div>
    <a href="{{ route('employees.outer_island.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<form action="{{ route('employees.outer_island.update', $employee->uuid) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @include('employees.outer_island._form')

    <div class="col-12 text-end my-4">
        <a href="{{ route('employees.outer_island.index') }}" class="btn btn-light border px-4 me-2">Batal</a>
        <button type="submit" class="btn btn-warning px-5 fw-bold text-dark">
            <i class="fa-solid fa-pen-to-square me-1"></i> Simpan Perubahan
        </button>
    </div>
</form>
@endsection