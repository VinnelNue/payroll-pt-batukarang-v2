@extends('layouts.app')

@section('title', 'Master Hari Libur')

@section('content')

<div class="container-fluid">

{{-- HEADER --}}
<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h4 class="mb-1">
            <i class="fas fa-calendar-alt me-2"></i>
            Master Hari Libur
        </h4>

        <p class="text-muted mb-0">
            Kelola hari besar dan hari libur yang digunakan dalam perhitungan payroll.
        </p>
    </div>

    <a href="{{ route('holidays.create') }}"
       class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>
        Tambah Hari Libur
    </a>

</div>


{{-- FLASH MESSAGE --}}
@if(session('success'))

    <div class="alert alert-success alert-dismissible fade show"
         role="alert">

        <i class="fas fa-check-circle me-2"></i>

        {{ session('success') }}

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert">
        </button>

    </div>

@endif


{{-- VALIDATION ERROR --}}
@if($errors->any())

    <div class="alert alert-danger">

        <strong>
            <i class="fas fa-exclamation-triangle me-1"></i>
            Terjadi kesalahan:
        </strong>

        <ul class="mb-0 mt-2">

            @foreach($errors->all() as $error)

                <li>{{ $error }}</li>

            @endforeach

        </ul>

    </div>

@endif


{{-- CARD --}}
<div class="card shadow-sm border-0">

    {{-- CARD HEADER --}}
    <div class="card-header bg-white">

        <div class="d-flex justify-content-between align-items-center">

            <div>
                <strong>
                    Daftar Hari Libur
                </strong>

                <div class="small text-muted">
                    Total {{ $holidays->count() }} hari
                </div>
            </div>

        </div>

    </div>


    {{-- TABLE --}}
    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th class="text-center"
                            style="width: 60px;">
                            No
                        </th>

                        <th style="width: 160px;">
                            Tanggal
                        </th>

                        <th>
                            Nama Hari Libur
                        </th>

                        <th style="width: 180px;">
                            Jenis
                        </th>

                        <th class="text-center"
                            style="width: 120px;">
                            Status
                        </th>

                        <th class="text-center"
                            style="width: 260px;">
                            Aksi
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($holidays as $holiday)

                        <tr>

                            {{-- NO --}}
                            <td class="text-center">
                                {{ $loop->iteration }}
                            </td>


                            {{-- DATE --}}
                            <td>

                                <div class="fw-semibold">
                                    {{ $holiday->holiday_date->format('d/m/Y') }}
                                </div>

                                <div class="small text-muted">
                                    {{ $holiday->holiday_date->translatedFormat('l') }}
                                </div>

                            </td>


                            {{-- NAME --}}
                            <td>

                                <div class="fw-semibold">
                                    {{ $holiday->name }}
                                </div>

                            </td>


                            {{-- TYPE --}}
                            <td>

                                @php

                                    $typeLabels = [
                                        'national' => 'Nasional',
                                        'collective_leave' => 'Cuti Bersama',
                                        'company' => 'Perusahaan',
                                        'other' => 'Lainnya',
                                    ];

                                @endphp

                                <span class="badge bg-light text-dark border">

                                    {{ $typeLabels[$holiday->type] ?? ucfirst($holiday->type) }}

                                </span>

                            </td>


                            {{-- STATUS --}}
                            <td class="text-center">

                                @if($holiday->is_active)

                                    <span class="badge bg-success">
                                        Aktif
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        Nonaktif
                                    </span>

                                @endif

                            </td>


                            {{-- ACTION --}}
                            <td class="text-center">

                                <div class="d-flex justify-content-center gap-1">

                                    {{-- EDIT --}}
                                    <a href="{{ route('holidays.edit', $holiday) }}"
                                       class="btn btn-sm btn-warning"
                                       title="Edit">

                                        <i class="fas fa-edit"></i>

                                    </a>


                                    {{-- TOGGLE --}}
                                    <form method="POST"
                                          action="{{ route('holidays.toggleStatus', $holiday) }}">

                                        @csrf
                                        @method('PATCH')

                                        @if($holiday->is_active)

                                            <button type="submit"
                                                    class="btn btn-sm btn-secondary"
                                                    title="Nonaktifkan">

                                                <i class="fas fa-ban"></i>

                                            </button>

                                        @else

                                            <button type="submit"
                                                    class="btn btn-sm btn-success"
                                                    title="Aktifkan">

                                                <i class="fas fa-check"></i>

                                            </button>

                                        @endif

                                    </form>


                                    {{-- DELETE --}}
                                    <form method="POST"
                                          action="{{ route('holidays.destroy', $holiday) }}"
                                          onsubmit="return confirm('Yakin ingin menghapus hari libur {{ addslashes($holiday->name) }}?');">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="Hapus">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6"
                                class="text-center py-5">

                                <div class="text-muted">

                                    <i class="fas fa-calendar-times fa-3x mb-3"></i>

                                    <div class="fw-semibold">
                                        Belum ada hari libur
                                    </div>

                                    <div class="small">
                                        Silakan tambahkan hari libur terlebih dahulu.
                                    </div>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>

@endsection
@extends('layouts.app')

@section('title', 'Master Hari Libur')

@section('content')

<div class="container-fluid">

{{-- HEADER --}}
<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h4 class="mb-1">
            <i class="fas fa-calendar-alt me-2"></i>
            Master Hari Libur
        </h4>

        <p class="text-muted mb-0">
            Kelola hari besar dan hari libur yang digunakan dalam perhitungan payroll.
        </p>
    </div>

    <a href="{{ route('holidays.create') }}"
       class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>
        Tambah Hari Libur
    </a>

</div>


{{-- FLASH MESSAGE --}}
@if(session('success'))

    <div class="alert alert-success alert-dismissible fade show"
         role="alert">

        <i class="fas fa-check-circle me-2"></i>

        {{ session('success') }}

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert">
        </button>

    </div>

@endif


{{-- VALIDATION ERROR --}}
@if($errors->any())

    <div class="alert alert-danger">

        <strong>
            <i class="fas fa-exclamation-triangle me-1"></i>
            Terjadi kesalahan:
        </strong>

        <ul class="mb-0 mt-2">

            @foreach($errors->all() as $error)

                <li>{{ $error }}</li>

            @endforeach

        </ul>

    </div>

@endif


{{-- CARD --}}
<div class="card shadow-sm border-0">

    {{-- CARD HEADER --}}
    <div class="card-header bg-white">

        <div class="d-flex justify-content-between align-items-center">

            <div>
                <strong>
                    Daftar Hari Libur
                </strong>

                <div class="small text-muted">
                    Total {{ $holidays->count() }} hari
                </div>
            </div>

        </div>

    </div>


    {{-- TABLE --}}
    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th class="text-center"
                            style="width: 60px;">
                            No
                        </th>

                        <th style="width: 160px;">
                            Tanggal
                        </th>

                        <th>
                            Nama Hari Libur
                        </th>

                        <th style="width: 180px;">
                            Jenis
                        </th>

                        <th class="text-center"
                            style="width: 120px;">
                            Status
                        </th>

                        <th class="text-center"
                            style="width: 260px;">
                            Aksi
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($holidays as $holiday)

                        <tr>

                            {{-- NO --}}
                            <td class="text-center">
                                {{ $loop->iteration }}
                            </td>


                            {{-- DATE --}}
                            <td>

                                <div class="fw-semibold">
                                    {{ $holiday->holiday_date->format('d/m/Y') }}
                                </div>

                                <div class="small text-muted">
                                    {{ $holiday->holiday_date->translatedFormat('l') }}
                                </div>

                            </td>


                            {{-- NAME --}}
                            <td>

                                <div class="fw-semibold">
                                    {{ $holiday->name }}
                                </div>

                            </td>


                            {{-- TYPE --}}
                            <td>

                                @php

                                    $typeLabels = [
                                        'national' => 'Nasional',
                                        'collective_leave' => 'Cuti Bersama',
                                        'company' => 'Perusahaan',
                                        'other' => 'Lainnya',
                                    ];

                                @endphp

                                <span class="badge bg-light text-dark border">

                                    {{ $typeLabels[$holiday->type] ?? ucfirst($holiday->type) }}

                                </span>

                            </td>


                            {{-- STATUS --}}
                            <td class="text-center">

                                @if($holiday->is_active)

                                    <span class="badge bg-success">
                                        Aktif
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        Nonaktif
                                    </span>

                                @endif

                            </td>


                            {{-- ACTION --}}
                            <td class="text-center">

                                <div class="d-flex justify-content-center gap-1">

                                    {{-- EDIT --}}
                                    <a href="{{ route('holidays.edit', $holiday) }}"
                                       class="btn btn-sm btn-warning"
                                       title="Edit">

                                        <i class="fas fa-edit"></i>

                                    </a>


                                    {{-- TOGGLE --}}
                                    <form method="POST"
                                          action="{{ route('holidays.toggleStatus', $holiday) }}">

                                        @csrf
                                        @method('PATCH')

                                        @if($holiday->is_active)

                                            <button type="submit"
                                                    class="btn btn-sm btn-secondary"
                                                    title="Nonaktifkan">

                                                <i class="fas fa-ban"></i>

                                            </button>

                                        @else

                                            <button type="submit"
                                                    class="btn btn-sm btn-success"
                                                    title="Aktifkan">

                                                <i class="fas fa-check"></i>

                                            </button>

                                        @endif

                                    </form>


                                    {{-- DELETE --}}
                                    <form method="POST"
                                          action="{{ route('holidays.destroy', $holiday) }}"
                                          onsubmit="return confirm('Yakin ingin menghapus hari libur {{ addslashes($holiday->name) }}?');">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="Hapus">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6"
                                class="text-center py-5">

                                <div class="text-muted">

                                    <i class="fas fa-calendar-times fa-3x mb-3"></i>

                                    <div class="fw-semibold">
                                        Belum ada hari libur
                                    </div>

                                    <div class="small">
                                        Silakan tambahkan hari libur terlebih dahulu.
                                    </div>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>

@endsection
