@extends('layouts.app')

@section('title', 'Edit Hari Libur')
@section('page_title', 'Edit Hari Libur')

@section('content')

<div class="container-fluid">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="mb-1">
                <i class="fas fa-calendar-pen me-2"></i>
                Edit Hari Libur
            </h4>

            <p class="text-muted mb-0">
                Perbarui informasi hari libur yang tersimpan.
            </p>
        </div>

        <a href="{{ route('holidays.index') }}"
           class="btn btn-light border">

            <i class="fas fa-arrow-left me-1"></i>
            Kembali

        </a>

    </div>


    {{-- VALIDATION ERROR --}}
    @if($errors->any())

        <div class="alert alert-danger alert-dismissible fade show"
             role="alert">

            <div class="fw-semibold mb-2">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Data belum dapat diperbarui.
            </div>

            <ul class="mb-0">

                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>

                @endforeach

            </ul>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- FORM CARD --}}
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">

            <div class="fw-semibold">
                <i class="fas fa-calendar-check me-2 text-primary"></i>
                Informasi Hari Libur
            </div>

        </div>


        <div class="card-body">

            <form method="POST"
                  action="{{ route('holidays.update', $holiday) }}">

                @csrf
                @method('PUT')


                <div class="row g-4">

                    {{-- TANGGAL --}}
                    <div class="col-md-6">

                        <label for="holiday_date"
                               class="form-label fw-semibold">

                            Tanggal Hari Libur
                            <span class="text-danger">*</span>

                        </label>

                        <input type="date"
                               class="form-control @error('holiday_date') is-invalid @enderror"
                               id="holiday_date"
                               name="holiday_date"
                               value="{{ old('holiday_date', $holiday->holiday_date->format('Y-m-d')) }}"
                               required>

                        @error('holiday_date')

                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>

                        @enderror

                        <div class="form-text">
                            Pastikan tanggal tidak sama dengan hari libur lainnya.
                        </div>

                    </div>


                    {{-- JENIS --}}
                    <div class="col-md-6">

                        <label for="type"
                               class="form-label fw-semibold">

                            Jenis Hari Libur
                            <span class="text-danger">*</span>

                        </label>

                        <select id="type"
                                name="type"
                                class="form-select @error('type') is-invalid @enderror"
                                required>

                            <option value="">
                                -- Pilih Jenis --
                            </option>

                            <option value="national"
                                {{ old('type', $holiday->type) === 'national' ? 'selected' : '' }}>
                                Nasional
                            </option>

                            <option value="collective_leave"
                                {{ old('type', $holiday->type) === 'collective_leave' ? 'selected' : '' }}>
                                Cuti Bersama
                            </option>

                            <option value="company"
                                {{ old('type', $holiday->type) === 'company' ? 'selected' : '' }}>
                                Perusahaan
                            </option>

                            <option value="other"
                                {{ old('type', $holiday->type) === 'other' ? 'selected' : '' }}>
                                Lainnya
                            </option>

                        </select>

                        @error('type')

                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>

                        @enderror

                        <div class="form-text">
                            Tentukan kategori hari libur.
                        </div>

                    </div>


                    {{-- NAMA --}}
                    <div class="col-12">

                        <label for="name"
                               class="form-label fw-semibold">

                            Nama Hari Libur
                            <span class="text-danger">*</span>

                        </label>

                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name', $holiday->name) }}"
                               maxlength="150"
                               placeholder="Contoh: Hari Kemerdekaan Republik Indonesia"
                               required>

                        @error('name')

                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>

                        @enderror

                        <div class="form-text">
                            Nama ini akan digunakan sebagai keterangan pada halaman absensi.
                        </div>

                    </div>


                    {{-- STATUS --}}
                    <div class="col-12">

                        <div class="border rounded-3 p-3">

                            <div class="form-check form-switch">

                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="is_active"
                                       name="is_active"
                                       value="1"
                                       {{ old('is_active', $holiday->is_active) ? 'checked' : '' }}>

                                <label class="form-check-label fw-semibold"
                                       for="is_active">

                                    Aktifkan hari libur

                                </label>

                            </div>

                            <div class="small text-muted mt-1 ms-5">

                                Jika aktif, tanggal ini akan otomatis dianggap
                                <strong>HB (Hari Besar)</strong> pada absensi
                                apabila tidak terdapat absensi eksplisit.

                            </div>

                        </div>

                    </div>

                </div>


                {{-- INFORMATION --}}
                <div class="alert alert-info mt-4 mb-0">

                    <div class="d-flex gap-2">

                        <i class="fas fa-circle-info mt-1"></i>

                        <div>

                            <div class="fw-semibold">
                                Informasi
                            </div>

                            <div class="small">

                                Perubahan hari libur akan memengaruhi
                                <strong>default status HB</strong> pada periode
                                payroll yang menggunakan tanggal tersebut.

                                Status absensi eksplisit seperti
                                <strong>H</strong> tetap memiliki prioritas
                                terhadap hari libur.

                            </div>

                        </div>

                    </div>

                </div>


                {{-- ACTION --}}
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">

                    <a href="{{ route('holidays.index') }}"
                       class="btn btn-light border">

                        <i class="fas fa-times me-1"></i>
                        Batal

                    </a>

                    <button type="submit"
                            class="btn btn-primary">

                        <i class="fas fa-save me-1"></i>
                        Simpan Perubahan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection