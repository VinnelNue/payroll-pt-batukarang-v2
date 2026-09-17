@extends('layouts.app')

@section('title', 'Manajemen Penempatan & Kontrak (Outer Island)')
@section('page_title', 'Manajemen Penempatan & Kontrak Kerja - Outer Island')

@section('content')
<div class="card-custom p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-file-signature text-primary me-2"></i> Daftar Penempatan & Gaji Acuan (Outer Island)
            </h5>
            <small class="text-muted">Kelola status hubungan kerja, divisi, area penempatan, acuan Gapok, Tunjangan, BPJS & PPh 21</small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle border-top">
            <thead class="table-light">
                <tr>
                    <th class="py-3 text-center" style="width: 50px;">No</th>
                    <th class="py-3">Karyawan</th>
                    <th class="py-3">Jabatan, Divisi & Level</th>
                    <th class="py-3 text-center">Status Kerja</th>
                    <th class="py-3 text-end">Gaji Pokok (GAPOK)</th>
                    <th class="py-3 text-end">Tunjangan (TJ)</th>
                    <th class="py-3 text-center">PTKP / TER</th>
                    <th class="py-3 text-center">Status BPJS</th>
                    <th class="py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $index => $emp)
                @php 
                    $contract = $emp->activeContract; 
                    $contractLevel = $contract->level ?? null;
                    $isHighLevel = !is_null($contractLevel) && $contractLevel !== '' && (int)$contractLevel > 13;

                    // Logika Hak Akses Finansial
                    $userRole = Auth::user()->role ?? '';
                    $canEditHighLevel = in_array($userRole, ['super_admin', 'manager_keuangan']);

                    $canSeeSalary = false;
                    if ($canEditHighLevel) {
                        $canSeeSalary = true;
                    } elseif ($userRole === 'head_hrd') {
                        $canSeeSalary = !$isHighLevel;
                    } elseif ($userRole === 'hrd') {
                        $canSeeSalary = false;
                    }

                    // Pemetaan PTKP & Kategori TER
                    $ptkpRaw = $contract->ptkp_status ?? 'TK0';
                    $ptkpClean = str_replace('/', '', strtoupper($ptkpRaw));
                    
                    $terCategory = match($ptkpClean) {
                        'TK0', 'TK1', 'K0' => 'TER A',
                        'TK2', 'TK3', 'K1', 'K2' => 'TER B',
                        'K3', 'K01', 'K02', 'K03' => 'TER C',
                        default => 'TER A'
                    };
                @endphp
                <tr>
                    <td class="text-center fw-semibold text-muted">
                        {{ $employees->firstItem() ? $employees->firstItem() + $index : $index + 1 }}
                    </td>
                    <td>
                        <div class="fw-bold text-dark">{{ $emp->full_name_outer }}</div>
                        <small class="text-muted">NIK: {{ $emp->nik_ktp_outer }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $contract?->job_title ?? '-' }}</div>
                        <small class="text-muted d-block">
                            Div: {{ $contract?->department ?? '-' }} 
                            @if(!empty($contract?->placement_area)) 
                                | Area: {{ $contract->placement_area }} 
                            @endif
                        </small>
                        @if($contract?->level || $contract?->category)
                            <small class="text-primary fw-medium">
                                Cat: {{ $contract->category ?? '-' }} | Lvl: {{ $contract->level ?? '-' }}
                            </small>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($contract)
                            @if(in_array($contract->employment_type, ['PHK', 'Resign', 'Pensiun', 'End_Contract']))
                                <span class="badge bg-danger px-2 py-1" title="Alasan: {{ $contract->exit_reason ?? '-' }}">
                                    <i class="fa-solid fa-user-slash me-1"></i> {{ $contract->employment_type }}
                                </span>
                            @else
                                <span class="badge bg-primary px-2 py-1">{{ $contract->employment_type }}</span>
                            @endif
                        @else
                            <span class="badge bg-secondary px-2 py-1">Belum Set</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold text-dark">
                        @if($canSeeSalary)
                            Rp {{ number_format($contract->basic_salary ?? 0, 0, ',', '.') }}
                        @else
                            <span class="text-muted small"><i class="fa-solid fa-lock me-1"></i>Rp **********</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold text-dark">
                        @if($canSeeSalary)
                            Rp {{ number_format($contract->allowance ?? 0, 0, ',', '.') }}
                        @else
                            <span class="text-muted small"><i class="fa-solid fa-lock me-1"></i>Rp **********</span>
                        @endif
                    </td>
                    
                    <td class="text-center">
                        @if($contract && $contract->ptkp_status)
                            <div class="fw-bold text-dark small">{{ $contract->ptkp_status }}</div>
                            <span class="badge bg-dark px-2 py-0" style="font-size: 0.7rem;">
                                {{ $terCategory }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border px-2 py-1">Belum Set</span>
                        @endif
                    </td>

                    <td class="text-center">
                        @if($contract)
                            @if($contract->use_manual_bpjs)
                                <span class="badge bg-warning text-dark border border-warning" title="Menggunakan Nominal BPJS Manual Input">BPJS Manual</span>
                            @else
                                <span class="badge {{ ($contract->is_bpjstk_active ?? false) ? 'bg-success' : 'bg-light text-muted border' }}" title="BPJS Ketenagakerjaan">BPJS TK</span>
                                <span class="badge {{ ($contract->is_bpjs_health_active ?? false) ? 'bg-info text-dark' : 'bg-light text-muted border' }}" title="BPJS Kesehatan">BPJS KS</span>
                            @endif
                        @else
                            <span class="badge bg-light text-muted border px-2 py-1">Belum Set</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('contracts.outer_island.edit', $emp->uuid) }}" class="btn btn-sm btn-outline-primary rounded-2" title="Kelola Kontrak & Gaji Outer Island">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Kontrak
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        Belum ada data karyawan Outer Island.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $employees->links() }}
    </div>
</div>
@endsection