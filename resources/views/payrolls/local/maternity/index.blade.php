@extends('layouts.app')



@section('title', 'Pengelolaan Cuti Melahirkan')

@section('page_title', 'Pengelolaan Cuti Melahirkan (CM)')



@section('content')

<div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

        <div>

            <h5 class="fw-bold mb-1">Cuti Melahirkan & Verifikasi Persalinan</h5>

            <div class="text-muted small">CM dibagi menjadi dua tahap terpisah: Tahap 1 diinput HRD (1–45 hari kalender), sedangkan Tahap 2 baru diisi setelah persalinan aktual dan bukti persalinan tersedia, lalu sistem menjadwalkan 45 hari kalender mulai hari berikutnya. Hari Minggu masuk durasi kalender, tetapi tidak dibuat sebagai record CM di absensi.</div>

        </div>

        <a href="{{ route('payrolls.local.create') }}" class="btn btn-outline-secondary btn-sm">

            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Payroll

        </a>

    </div>



    @if(session('success'))

        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>

    @endif

    @if(session('error'))

        <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>

    @endif

    @if(session('warning'))

        <div class="alert alert-warning border-0 shadow-sm">{{ session('warning') }}</div>

    @endif

    @if($errors->any())

        <div class="alert alert-danger border-0 shadow-sm">

            <div class="fw-bold mb-1">Periksa input berikut:</div>

            @foreach($errors->all() as $error)

                <div>{{ $error }}</div>

            @endforeach

        </div>

    @endif



    @if($canManageMaternity)
    <div class="card border-0 shadow-sm rounded-4 mb-4">

        <div class="card-header bg-white border-0 pt-3 px-3">

            <h6 class="fw-bold mb-0">Daftarkan Kasus CM</h6>

        </div>

        <div class="card-body pt-2">

            <form method="POST" action="{{ route('payrolls.local.maternity.store') }}" class="row g-3 align-items-end">

                @csrf

                <div class="col-lg-4">

                    <label class="form-label small fw-semibold">Karyawan</label>

                    <select name="employee_id" class="form-select" required>

                        <option value="">Pilih karyawan</option>

                        @foreach($employees as $employee)

                            <option value="{{ $employee->id_employee }}" @selected(old('employee_id') == $employee->id_employee)>

                                {{ $employee->full_name }} — ID: {{ $employee->id_employee }} — Alamat: {{ $employee->address_ktp ?: 'Alamat belum tersedia' }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-lg-3">

                    <label class="form-label small fw-semibold">Tanggal mulai Tahap 1 (CM)</label>

                    <input type="date" name="leave_start_date" class="form-control" value="{{ old('leave_start_date') }}" required>

                </div>

                <div class="col-lg-2">

                    <label class="form-label small fw-semibold">Durasi Tahap 1 (hari)</label>

                    <input type="number" name="period1_days" class="form-control" min="1" max="45" step="1" value="{{ old('period1_days', 45) }}" required>

                    <div class="form-text">Maksimal 45 hari</div>

                </div>

                <div class="col-lg-2">

                    <label class="form-label small fw-semibold">Perkiraan persalinan (opsional)</label>

                    <input type="date" name="expected_delivery_date" class="form-control" value="{{ old('expected_delivery_date') }}">

                    <div class="form-text">Referensi saja</div>

                </div>

                <div class="col-lg-1 d-grid">

                    <button class="btn btn-primary" type="submit" title="Daftarkan kasus">

                        <i class="fa-solid fa-plus"></i>

                    </button>

                </div>

            </form>

            <div class="small text-muted mt-2">

                <strong>Jadwal dua tahap:</strong> Tahap 1 dimulai dari tanggal CM dengan durasi yang diinput HRD (1–45 hari). Jika persalinan aktual terjadi sebelum Tahap 1 selesai, Tahap 1 dipotong sampai tanggal persalinan agar tidak tumpang tindih. Tahap 2 belum dijadwalkan dari tanggal perkiraan; setelah tanggal persalinan aktual dan bukti persalinan diunggah, sistem menjadwalkan 45 hari mulai sehari setelah persalinan. Hari Minggu tetap masuk hitungan durasi kalender 45 hari, tetapi tidak dibuat sebagai record CM di attendance_records dan tidak menambah paid_days / Sigma maupun hold CM. Tanggal pada payroll yang sudah terkunci tidak diubah.

                Status CM tidak dibuat manual dari kalender atau impor Excel. Daftarkan Tahap 1 melalui form ini agar durasi dan penahanan payroll tercatat dengan benar.

            </div>

        </div>

    </div>
    @endif



    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-header bg-white border-0 pt-3 px-3 d-flex justify-content-between align-items-center">

            <h6 class="fw-bold mb-0">Daftar Kasus Cuti Melahirkan</h6>

            <span class="badge bg-light text-dark border">{{ $cases->total() }} kasus</span>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light small text-uppercase">

                        <tr>

                            <th class="px-3">Karyawan</th>

                            <th>Mulai CM</th>

                            <th>Tahap CM (maks. 45 + 45 Hari)</th>

                            <th>Tanggal Persalinan</th>

                            <th>Status</th>

                            <th class="text-end">Bruto Settlement</th>

                            <th>Status Settlement / Transfer</th>

                            <th style="min-width:360px">Aksi, Dokumen & Tindakan HRD</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($cases as $case)

                            @php

                                $settlement = $case->settlement;
                                $legacyHeldTotal = (float) ($case->legacy_held_total ?? $case->payrolls->sum('maternity_hold_amount'));
                                $settlementGross = (float) ($settlement?->gross_amount ?? $legacyHeldTotal);
                                $settlementStatus = $settlement?->status ?? 'not_created';

                                $hasPayrollImpact = $case->payrolls->contains(function ($payroll) {
                                    return (float) ($payroll->maternity_hold_days ?? 0) > 0
                                        || (float) ($payroll->maternity_hold_amount ?? 0) > 0
                                        || (float) ($payroll->maternity_release_amount ?? 0) > 0
                                        || (float) ($payroll->maternity_leave_pay ?? 0) > 0
                                        || (bool) ($payroll->is_locked ?? false);
                                });
                                $canEditCase = $canManageMaternity
                                    && $case->status !== 'verified'
                                    && !$case->released_at;
                                $canDeleteCase = $canManageMaternity
                                    && $case->status !== 'verified'
                                    && !$case->released_at
                                    && $case->payrolls->isEmpty()
                                    && !$case->has_payroll_in_schedule;
                                $period1Start = $case->firstPeriodStartDate();

                                $period1End = $case->firstPeriodEndDate();

                                $period2Start = $case->secondPeriodStartDate();

                                $period2End = $case->secondPeriodEndDate();

                                $statusClass = match($case->status) {

                                    'verified' => 'success',

                                    'submitted' => 'info',

                                    'rejected' => 'danger',

                                    default => 'warning',

                                };

                            @endphp

                            <tr>

                                <td class="px-3">

                                    <div class="fw-semibold">{{ $case->employee?->full_name ?? 'Karyawan tidak ditemukan' }}</div>

                                    <div class="text-muted small">Alamat: {{ $case->employee?->address_ktp ?: 'Alamat belum tersedia' }}</div>

                                </td>

                                <td>{{ optional($case->leave_start_date)->format('d/m/Y') }}</td>

                                <td class="text-nowrap small">

                                    <div><span class="fw-semibold">Tahap 1:</span> {{ $period1Start->format('d/m/Y') }} – {{ $period1End->format('d/m/Y') }} ({{ $case->firstPeriodDays() }} dari {{ $case->period1_days ?: 45 }} hari diajukan)</div>

                                    @if($period2Start && $period2End)

                                        <div><span class="fw-semibold">Tahap 2:</span> {{ $period2Start->format('d/m/Y') }} – {{ $period2End->format('d/m/Y') }} (45 hari)</div>

                                    @else

                                        <div><span class="fw-semibold">Tahap 2:</span> Menunggu tanggal persalinan aktual</div>

                                    @endif

                                    <div class="text-muted">Total jadwal: {{ $case->firstPeriodDays() + $case->secondPeriodDays() }} hari kalender</div>

                                </td>

                                <td>{{ optional($case->delivery_date)->format('d/m/Y') ?? 'Belum dicatat' }}</td>

                                <td>

                                    <span class="badge text-bg-{{ $statusClass }}">{{ strtoupper($case->status) }}</span>

                                    @if($case->verified_at)

                                        <div class="text-muted small mt-1">{{ $case->verified_at->format('d/m/Y H:i') }}</div>

                                    @endif

                                </td>

                                <td class="text-end text-nowrap">Rp {{ number_format($settlementGross, 0, ',', '.') }}</td>

                                <td class="text-nowrap">
                                    @if($settlementStatus === 'pending')
                                        <span class="badge text-bg-warning">Menunggu transfer</span>
                                        @if($settlement->tax_calculated_at)
                                            <div class="small text-muted">PPh21: Rp {{ number_format((float) $settlement->pph21_amount, 0, ',', '.') }}</div>
                                            <div class="small fw-semibold">Neto: Rp {{ number_format((float) $settlement->net_amount, 0, ',', '.') }}</div>
                                        @else
                                            <div class="small text-muted">PPh21 belum dihitung Keuangan</div>
                                        @endif
                                    @elseif($settlementStatus === 'paid')
                                        <span class="badge text-bg-success">LUNAS</span>
                                        <div class="small text-muted">{{ optional($settlement->payment_date)->format('d/m/Y') }} · {{ $settlement->transfer_reference }}</div>
                                        <div class="small fw-semibold">Net transfer: Rp {{ number_format((float) $settlement->net_amount, 0, ',', '.') }}</div>
                                    @elseif($settlementStatus === 'no_balance')
                                        <span class="badge text-bg-secondary">Tidak ada saldo</span>
                                    @else
                                        @if($case->status === 'verified')
                                            <span class="badge text-bg-danger">Settlement belum tersedia</span>
                                        @else
                                            <span class="badge text-bg-secondary">Belum diverifikasi</span>
                                        @endif
                                        @if($legacyHeldTotal > 0)
                                            <div class="small text-muted">Hold payroll: Rp {{ number_format($legacyHeldTotal, 0, ',', '.') }}</div>
                                        @endif
                                    @endif
                                </td>

                                <td>

                                    @if($canEditCase)
                                        <details class="border rounded-3 p-2 mb-2">
                                            <summary class="btn btn-outline-primary btn-sm mb-2" style="cursor:pointer">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit data kehamilan / CM
                                            </summary>
                                            <form method="POST"
                                                  action="{{ route('payrolls.local.maternity.update', $case) }}"
                                                  enctype="multipart/form-data"
                                                  class="row g-2 mt-2">
                                                @csrf
                                                @method('PUT')
                                                @if($hasPayrollImpact)
                                                    <div class="col-12">
                                                        <div class="alert alert-warning py-2 small mb-1">
                                                            Payroll sudah menghitung CM. Tanggal perkiraan dan dokumen referensi masih dapat diperbarui, tetapi tanggal mulai/durasi/tanggal persalinan aktual dikunci sampai payroll draft terkait dikoreksi.
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Tanggal mulai CM</label>
                                                    <input type="date" name="leave_start_date" class="form-control form-control-sm"
                                                           value="{{ optional($case->leave_start_date)->format('Y-m-d') }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Durasi Tahap 1 (hari)</label>
                                                    <input type="number" name="period1_days" class="form-control form-control-sm"
                                                           min="1" max="45" step="1" value="{{ $case->period1_days ?: 45 }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Perkiraan persalinan</label>
                                                    <input type="date" name="expected_delivery_date" class="form-control form-control-sm"
                                                           value="{{ optional($case->expected_delivery_date)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Tanggal persalinan aktual</label>
                                                    <input type="date" name="delivery_date" class="form-control form-control-sm"
                                                           value="{{ optional($case->delivery_date)->format('Y-m-d') }}"
                                                           min="{{ optional($case->leave_start_date)->format('Y-m-d') }}"
                                                           max="{{ now()->toDateString() }}">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1">Ganti bukti persalinan (opsional)</label>
                                                    <input type="file" name="certificate" class="form-control form-control-sm"
                                                           accept=".pdf,.jpg,.jpeg,.png">
                                                    <div class="form-text">Wajib unggah dokumen baru jika tanggal persalinan aktual diubah. Kosongkan tanggal aktual untuk membatalkan Tahap 2.</div>
                                                </div>
                                                <div class="col-12 d-flex justify-content-end">
                                                    <button class="btn btn-primary btn-sm" type="submit">
                                                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </details>
                                    @elseif($case->status === 'verified' || $case->released_at)
                                        <div class="small text-muted mb-2">Data terkunci setelah verifikasi atau pelepasan CM.</div>
                                    @else
                                        <div class="small text-muted mb-2">Edit dibatasi karena kasus sudah memengaruhi payroll atau periode terkait terkunci.</div>
                                    @endif

                                    @if($canDeleteCase)
                                        <form method="POST"
                                              action="{{ route('payrolls.local.maternity.destroy', $case) }}"
                                              class="mb-2"
                                              onsubmit="return confirm('Hapus kasus CM ini secara permanen? Data kasus serta path dokumen akan dihapus dari database, file bukti persalinan dari storage lokal akan dihapus, dan absensi asal dipulihkan dari snapshot.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit">
                                                <i class="fa-solid fa-trash me-1"></i> Hapus Permanen
                                            </button>
                                        </form>
                                    @elseif($case->payrolls->isNotEmpty() || $case->has_payroll_in_schedule)
                                        <div class="small text-muted mb-2">Hapus dinonaktifkan karena salah satu periode jadwal CM sudah memiliki data payroll.</div>
                                    @elseif($case->status === 'verified' || $case->released_at)
                                        <div class="small text-muted mb-2">Hapus dinonaktifkan karena kasus sudah diverifikasi atau saldonya dilepas.</div>
                                    @endif

                                    @if($case->certificate_path)

                                        <div class="mb-2">

                                            <a href="{{ route('payrolls.local.maternity.certificate.download', $case) }}" class="link-primary small">

                                                <i class="fa-solid fa-file-arrow-down me-1"></i>{{ $case->certificate_original_name ?: 'Unduh bukti persalinan' }}

                                            </a>

                                        </div>

                                    @endif



                                    @if($canManageMaternity && $case->status !== 'verified')

                                        <form method="POST" action="{{ route('payrolls.local.maternity.certificate.upload', $case) }}" enctype="multipart/form-data" class="border rounded-3 p-2 mb-2">

                                            @csrf

                                            <div class="row g-2">

                                                <div class="col-md-5">

                                                    <label class="form-label small mb-1">Tanggal persalinan aktual (untuk Tahap 2)</label>

                                                    <input type="date" name="delivery_date" class="form-control form-control-sm" value="{{ old('delivery_date', optional($case->delivery_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required>

                                                </div>

                                                <div class="col-md-7">

                                                    <label class="form-label small mb-1">Bukti persalinan (PDF/JPG/PNG, maks. 10 MB)</label>

                                                    <input type="file" name="certificate" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>

                                                </div>

                                                <div class="col-12 d-flex justify-content-end">

                                                    <button class="btn btn-outline-primary btn-sm" type="submit">

                                                        <i class="fa-solid fa-upload me-1"></i> Unggah / Ganti Dokumen

                                                    </button>

                                                </div>

                                            </div>

                                        </form>



                                        @if($case->status === 'submitted' && $case->certificate_path)

                                            <div class="d-flex flex-wrap gap-2">

                                                <form method="POST" action="{{ route('payrolls.local.maternity.verify', $case) }}" onsubmit="return confirm('Verifikasi dokumen persalinan ini? Sistem akan langsung membuat settlement dari seluruh hari CM yang sudah diperoleh sebelum tanggal verifikasi.');">

                                                    @csrf

                                                    <button class="btn btn-success btn-sm" type="submit">

                                                        <i class="fa-solid fa-check me-1"></i> Verifikasi HRD

                                                    </button>

                                                </form>

                                                <form method="POST" action="{{ route('payrolls.local.maternity.reject', $case) }}" class="d-flex gap-1">

                                                    @csrf

                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Alasan penolakan" required maxlength="1000">

                                                    <button class="btn btn-outline-danger btn-sm" type="submit">Tolak</button>

                                                </form>

                                            </div>

                                        @endif

                                    @elseif($case->status === 'verified')

                                        <div class="small text-success fw-semibold">

                                            <i class="fa-solid fa-circle-check me-1"></i> Dokumen telah diverifikasi HRD

                                        </div>

                                        <div class="small text-muted">Verifikasi: {{ optional($case->verified_at)->format('d/m/Y H:i') }}</div>

                                    @endif



                                    @if($canManagePayments && $settlement && $settlement->status === 'pending')
                                        <div class="border rounded-3 p-2 mt-2">
                                            <div class="fw-semibold small mb-1">Proses transfer Keuangan</div>
                                            <div class="small text-muted mb-2">Bruto: Rp {{ number_format((float) $settlement->gross_amount, 0, ',', '.') }}</div>
                                            <div class="small text-warning mb-2">Preview PPh21 memakai masa pajak tanggal transfer. Pastikan Finance/Tax sudah menetapkan bahwa gaji CM yang ditahan belum terutang sebelum verifikasi.</div>
                                            <form method="POST" action="{{ route('payrolls.local.maternity.settlements.preview-tax', $settlement) }}" class="row g-2 mb-2">
                                                @csrf
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Tanggal rencana transfer</label>
                                                    <input type="date" name="planned_payment_date" class="form-control form-control-sm" value="{{ optional($settlement->planned_payment_date)->format('Y-m-d') ?? now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                                </div>
                                                <div class="col-md-6 d-flex align-items-end">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Hitung PPh21 & Neto</button>
                                                </div>
                                            </form>
                                            @if($settlement->tax_calculated_at)
                                                <div class="small mb-2">
                                                    <div>PPh21: <strong>Rp {{ number_format((float) $settlement->pph21_amount, 0, ',', '.') }}</strong></div>
                                                    <div>Net transfer: <strong class="text-success">Rp {{ number_format((float) $settlement->net_amount, 0, ',', '.') }}</strong></div>
                                                    <div class="text-muted">Masa pajak {{ $settlement->tax_period }} · Basis bruto {{ number_format((float) $settlement->taxable_gross_basis, 0, ',', '.') }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('payrolls.local.maternity.settlements.paid', $settlement) }}" enctype="multipart/form-data" class="row g-2">
                                                    @csrf
                                                    <div class="col-md-5">
                                                        <label class="form-label small mb-1">Tanggal transfer aktual</label>
                                                        <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ optional($settlement->planned_payment_date)->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <label class="form-label small mb-1">Nomor referensi transfer</label>
                                                        <input type="text" name="transfer_reference" class="form-control form-control-sm" maxlength="120" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small mb-1">Nominal bersih yang ditransfer</label>
                                                        <input type="number" name="actual_net_amount" class="form-control form-control-sm" min="0" step="0.01" value="{{ number_format((float) $settlement->net_amount, 2, '.', '') }}" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small mb-1">Bukti transfer (PDF/JPG/PNG)</label>
                                                        <input type="file" name="payment_proof" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Konfirmasi bahwa transfer sebesar nominal bersih di atas benar-benar sudah dilakukan?')">Konfirmasi transfer lunas</button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    @elseif($canManagePayments && $settlement && $settlement->status === 'paid')
                                        <div class="small text-success mt-2">Dibayar oleh user #{{ $settlement->paid_by }} pada {{ optional($settlement->paid_at)->format('d/m/Y H:i') }}.</div>
                                        @if($settlement->proof_path)
                                            <a class="small" href="{{ route('payrolls.local.maternity.settlements.proof', $settlement) }}">Unduh bukti transfer</a>
                                        @endif
                                    @endif

                                    @if($case->rejection_reason)

                                        <div class="small text-danger mt-1">Ditolak: {{ $case->rejection_reason }}</div>

                                    @endif

                                    @if($case->released_at)

                                        <div class="small text-success mt-1">Dilepas pada {{ $case->released_at->format('d/m/Y H:i') }}</div>

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="8" class="text-center text-muted py-5">Belum ada kasus cuti melahirkan.</td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

        @if($cases->hasPages())

            <div class="card-footer bg-white">{{ $cases->links() }}</div>

        @endif

    </div>



    <div class="alert alert-light border mt-3 small mb-0">

        <strong>Jadwal CM:</strong> Tahap 1 memakai tanggal mulai dan durasi yang diinput HRD (maksimal 45 hari). Jika persalinan aktual lebih awal, Tahap 1 berakhir pada tanggal persalinan. Tahap 2 baru dibuat setelah tanggal persalinan aktual tersedia dan berjalan 45 hari mulai hari berikutnya. Tanggal perkiraan hanya menjadi referensi dan tidak membuat jadwal Tahap 2. Hari Minggu tetap dihitung dalam durasi kalender, tetapi tidak dibuat sebagai record CM di attendance_records dan tidak menambah Sigma maupun hold CM. Payroll yang sudah terkunci tidak diubah.

        <br><br>

        <strong>Alur pembayaran:</strong> sebelum verifikasi HRD, hak gaji CM yang telah diperoleh ditahan dalam payroll. Saat HRD memverifikasi dokumen, sistem langsung membuat satu settlement dari hari kerja CM sebelum tanggal verifikasi. Keuangan menghitung PPh21 pada masa pajak transfer, lalu mengonfirmasi pembayaran dengan referensi dan bukti transfer. Hari CM setelah tanggal verifikasi dibayarkan melalui payroll normal.

    </div>

</div>

@endsection
