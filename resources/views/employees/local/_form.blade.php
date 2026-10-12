<div class="row g-4 employee-master-form">
    @if ($errors->any())
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-0" role="alert">
                <div class="fw-semibold mb-1"><i class="fa-solid fa-circle-exclamation me-2"></i>Data belum berhasil disimpan.</div>
                <div class="small mb-2">Periksa kembali bagian berikut:</div>
                <ul class="mb-0 ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        </div>
    @endif
    <div class="col-lg-6">
        <section class="card-custom border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex align-items-start gap-3 border-bottom pb-3 mb-4">
                <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3 flex-shrink-0">
                    <i class="fa-solid fa-id-card fa-lg"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-primary mb-1">Identitas Personal & Dokumen</h6>
                    <p class="text-muted small mb-0">Masukkan data identitas sesuai dokumen resmi karyawan.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label for="no_kk" class="form-label fw-semibold text-dark">Nomor Kartu Keluarga (KK)</label>
                    <input id="no_kk" type="text" name="no_kk" class="form-control @error('no_kk') is-invalid @enderror" value="{{ old('no_kk', $employee->no_kk ?? '') }}" placeholder="16 digit nomor Kartu Keluarga" maxlength="16">
                    @error('no_kk')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="nik_ktp" class="form-label fw-semibold text-dark">NIK KTP <span class="text-danger">*</span></label>
                    <input id="nik_ktp" type="text" name="nik_ktp" class="form-control @error('nik_ktp') is-invalid @enderror" value="{{ old('nik_ktp', $employee->nik_ktp ?? '') }}" placeholder="16 digit angka KTP" maxlength="16" required>
                    @error('nik_ktp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="full_name" class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input id="full_name" type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $employee->full_name ?? '') }}" placeholder="Nama lengkap sesuai KTP" required>
                    @error('full_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label fw-semibold text-dark">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                        <option value="L" {{ old('gender', $employee->gender ?? 'L') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender', $employee->gender ?? 'L') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    @error('gender')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="religion" class="form-label fw-semibold text-dark">Agama</label>
                    <select id="religion" name="religion" class="form-select @error('religion') is-invalid @enderror">
                        <option value="">-- Pilih Agama --</option>
                        @foreach(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'] as $rel)
                            <option value="{{ $rel }}" {{ old('religion', $employee->religion ?? '') == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                    @error('religion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="birth_place" class="form-label fw-semibold text-dark">Tempat Lahir <span class="text-danger">*</span></label>
                    <input id="birth_place" type="text" name="birth_place" class="form-control @error('birth_place') is-invalid @enderror" value="{{ old('birth_place', $employee->birth_place ?? '') }}" placeholder="Kota kelahiran" required>
                    @error('birth_place')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="birth_date" class="form-label fw-semibold text-dark">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input id="birth_date" type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $employee->birth_date ?? '') }}" placeholder="" required>
                    @error('birth_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="marital_status" class="form-label fw-semibold text-dark">Status Pernikahan <span class="text-danger">*</span></label>
                    <select id="marital_status" name="marital_status" class="form-select @error('marital_status') is-invalid @enderror" required>
                        <option value="single" {{ old('marital_status', $employee->marital_status ?? 'single') == 'single' ? 'selected' : '' }}>Belum Menikah (Single)</option>
                        <option value="married" {{ old('marital_status', $employee->marital_status ?? 'single') == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status', $employee->marital_status ?? 'single') == 'divorced' ? 'selected' : '' }}>Cerai</option>
                    </select>
                    @error('marital_status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 pt-2">
                    <label for="ktp_file" class="form-label fw-semibold text-dark">Upload Scan / Foto KTP</label>
                    <input id="ktp_file" type="file" name="ktp_file" class="form-control @error('ktp_file') is-invalid @enderror" accept="image/*,.pdf">
                    <div class="form-text">Format yang disarankan: JPG, PNG, atau PDF. Maksimal 5 MB.</div>
                    @error('ktp_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($employee) && !empty($employee->ktp_path))
                        <div class="mt-3">
                            <a href="{{ asset('storage/' . $employee->ktp_path) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="fa-regular fa-file-lines me-2"></i>Lihat dokumen KTP saat ini
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="card-custom border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex align-items-start gap-3 border-bottom pb-3 mb-4">
                <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3 flex-shrink-0">
                    <i class="fa-solid fa-map-location-dot fa-lg"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-primary mb-1">Kontak, Alamat & Rekening Payroll</h6>
                    <p class="text-muted small mb-0">Lengkapi informasi komunikasi, alamat, dan rekening penerima gaji.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="phone_number" class="form-label fw-semibold text-dark">No. HP / WhatsApp</label>
                    <input id="phone_number" type="text" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" value="{{ old('phone_number', $employee->phone_number ?? '') }}" placeholder="08123456789">
                    @error('phone_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold text-dark">Email</label>
                    <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $employee->email ?? '') }}" placeholder="karyawan@batukarang.com">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="address_ktp" class="form-label fw-semibold text-dark">Alamat KTP <span class="text-danger">*</span></label>
                    <textarea id="address_ktp" name="address_ktp" class="form-control @error('address_ktp') is-invalid @enderror" rows="2" placeholder="Alamat lengkap sesuai KTP" required>{{ old('address_ktp', $employee->address_ktp ?? '') }}</textarea>
                    @error('address_ktp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="address_domicile" class="form-label fw-semibold text-dark">Alamat Domisili (opsional)</label>
                    <textarea id="address_domicile" name="address_domicile" class="form-control @error('address_domicile') is-invalid @enderror" rows="2" placeholder="Isi jika alamat domisili berbeda dengan KTP">{{ old('address_domicile', $employee->address_domicile ?? '') }}</textarea>
                    @error('address_domicile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="npwp_number" class="form-label fw-semibold text-dark">Nomor NPWP</label>
                    <input id="npwp_number" type="text" name="npwp_number" class="form-control @error('npwp_number') is-invalid @enderror" value="{{ old('npwp_number', $employee->npwp_number ?? '') }}" placeholder="00.000.000.0-000.000">
                    @error('npwp_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_name" class="form-label fw-semibold text-dark">Nama Bank</label>
                    <input id="bank_name" type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name', $employee->bank_name ?? '') }}" placeholder="BCA / Mandiri / BRI">
                    @error('bank_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_account_number" class="form-label fw-semibold text-dark">No. Rekening Bank</label>
                    <input id="bank_account_number" type="text" name="bank_account_number" class="form-control @error('bank_account_number') is-invalid @enderror" value="{{ old('bank_account_number', $employee->bank_account_number ?? '') }}" placeholder="1234567890">
                    @error('bank_account_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_account_holder" class="form-label fw-semibold text-dark">Nama Pemilik Rekening</label>
                    <input id="bank_account_holder" type="text" name="bank_account_holder" class="form-control @error('bank_account_holder') is-invalid @enderror" value="{{ old('bank_account_holder', $employee->bank_account_holder ?? '') }}" placeholder="Nama sesuai rekening">
                    @error('bank_account_holder')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </section>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Aktif hanya jika dropdown wilayah tersedia di halaman ini.
    const provinceSelect = document.getElementById('province_code');
    const citySelect = document.getElementById('city_code');
    const districtSelect = document.getElementById('district_code');
    const villageSelect = document.getElementById('village_code');

    if (!provinceSelect || !citySelect || !districtSelect || !villageSelect) return;

    provinceSelect.addEventListener('change', function () {
        const provCode = this.value;
        citySelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
        districtSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        if (!provCode) return;

        fetch(`{{ route('api.cities') }}?province_code=${encodeURIComponent(provCode)}`)
            .then(res => {
                if (!res.ok) throw new Error('Gagal memuat kota.');
                return res.json();
            })
            .then(data => Object.entries(data).forEach(([code, name]) => citySelect.add(new Option(name, code))))
            .catch(err => console.error('Error fetching cities:', err));
    });

    citySelect.addEventListener('change', function () {
        const cityCode = this.value;
        districtSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        if (!cityCode) return;

        fetch(`{{ route('api.districts') }}?city_code=${encodeURIComponent(cityCode)}`)
            .then(res => {
                if (!res.ok) throw new Error('Gagal memuat kecamatan.');
                return res.json();
            })
            .then(data => Object.entries(data).forEach(([code, name]) => districtSelect.add(new Option(name, code))))
            .catch(err => console.error('Error fetching districts:', err));
    });

    districtSelect.addEventListener('change', function () {
        const districtCode = this.value;
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        if (!districtCode) return;

        fetch(`{{ route('api.villages') }}?district_code=${encodeURIComponent(districtCode)}`)
            .then(res => {
                if (!res.ok) throw new Error('Gagal memuat kelurahan.');
                return res.json();
            })
            .then(data => Object.entries(data).forEach(([code, name]) => villageSelect.add(new Option(name, code))))
            .catch(err => console.error('Error fetching villages:', err));
    });
});
</script>
@endpush
