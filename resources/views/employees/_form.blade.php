<div class="row g-4">
    <!-- SECTION 1: IDENTITAS DIRI & DOKUMEN -->
    <div class="col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                <i class="fa-solid fa-id-card me-2"></i> Identitas Personal & Dokumen
            </h6>

            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">NIK KTP <span class="text-danger">*</span></label>
                    <input type="text" name="nik_ktp" class="form-control @error('nik_ktp') is-invalid @enderror" value="{{ old('nik_ktp', $employee->nik_ktp ?? '') }}" maxlength="16" required placeholder="16 digit angka KTP">
                    @error('nik_ktp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $employee->full_name ?? '') }}" required placeholder="Nama lengkap sesuai KTP">
                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-dark">Panggilan</label>
                    <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $employee->nickname ?? '') }}" placeholder="Nama panggilan">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="L" {{ old('gender', $employee->gender ?? '') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender', $employee->gender ?? '') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Agama</label>
                    <select name="religion" class="form-select">
                        <option value="">-- Pilih Agama --</option>
                        @foreach(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'] as $rel)
                            <option value="{{ $rel }}" {{ old('religion', $employee->religion ?? '') == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Tempat Lahir <span class="text-danger">*</span></label>
                    <input type="text" name="birth_place" class="form-control" value="{{ old('birth_place', $employee->birth_place ?? '') }}" required placeholder="Kota kelahiran">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date', $employee->birth_date ?? '') }}" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Status Pernikahan <span class="text-danger">*</span></label>
                    <select name="marital_status" class="form-select" required>
                        <option value="single" {{ old('marital_status', $employee->marital_status ?? '') == 'single' ? 'selected' : '' }}>Belum Menikah (Single)</option>
                        <option value="married" {{ old('marital_status', $employee->marital_status ?? '') == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status', $employee->marital_status ?? '') == 'divorced' ? 'selected' : '' }}>Cerai</option>
                    </select>
                </div>

                <!-- UPLOAD FILE KTP -->
                <div class="col-md-12 mt-3">
                    <label class="form-label fw-semibold text-dark">Upload Scan / Foto KTP (.jpg, .png, .pdf max 5MB)</label>
                    <input type="file" name="ktp_file" class="form-control @error('ktp_file') is-invalid @enderror" accept="image/*,.pdf">
                    @error('ktp_file') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    @if(isset($employee) && $employee->ktp_path)
                        <div class="mt-2">
                            <a href="{{ asset('storage/' . $employee->ktp_path) }}" target="_blank" class="badge bg-info text-dark text-decoration-none p-2">
                                <i class="fa-solid fa-file-pdf me-1"></i> Lihat Dokumen KTP Terupload
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: KONTAK, ALAMAT WILAYAH & REKENING -->
    <div class="col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                <i class="fa-solid fa-map-location-dot me-2"></i> Kontak, Alamat & Rekening Payroll
            </h6>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">No. HP / WhatsApp</label>
                    <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $employee->phone_number ?? '') }}" placeholder="08123456789">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email ?? '') }}" placeholder="karyawan@batukarang.com">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Alamat KTP <span class="text-danger">*</span></label>
                    <textarea name="address_ktp" class="form-control" rows="2" required placeholder="Alamat lengkap sesuai KTP">{{ old('address_ktp', $employee->address_ktp ?? '') }}</textarea>
                </div>

                <!-- DROPDOWN WILAYAH LARAVOLT -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Provinsi</label>
                    <select name="province_code" id="province_code" class="form-select">
                        <option value="">-- Pilih Provinsi --</option>
                        @foreach($provinces as $code => $name)
                            <option value="{{ $code }}" {{ old('province_code', $employee->province_code ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Kabupaten / Kota</label>
                    <select name="city_code" id="city_code" class="form-select">
                        <option value="">-- Pilih Kota --</option>
                        @if(isset($cities))
                            @foreach($cities as $code => $name)
                                <option value="{{ $code }}" {{ old('city_code', $employee->city_code ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Kecamatan</label>
                    <select name="district_code" id="district_code" class="form-select">
                        <option value="">-- Pilih Kecamatan --</option>
                        @if(isset($districts))
                            @foreach($districts as $code => $name)
                                <option value="{{ $code }}" {{ old('district_code', $employee->district_code ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Desa / Kelurahan</label>
                    <select name="village_code" id="village_code" class="form-select">
                        <option value="">-- Pilih Kelurahan --</option>
                        @if(isset($villages))
                            @foreach($villages as $code => $name)
                                <option value="{{ $code }}" {{ old('village_code', $employee->village_code ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Alamat Domisili (opsional)</label>
                    <textarea name="address_domicile" class="form-control" rows="1" placeholder="Isi jika alamat domisili berbeda dengan KTP">{{ old('address_domicile', $employee->address_domicile ?? '') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nomor NPWP</label>
                    <input type="text" name="npwp_number" class="form-control" value="{{ old('npwp_number', $employee->npwp_number ?? '') }}" placeholder="00.000.000.0-000.000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nama Bank</label>
                    <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $employee->bank_name ?? '') }}" placeholder="BCA / Mandiri / BRI">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">No. Rekening Bank</label>
                    <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $employee->bank_account_number ?? '') }}" placeholder="1234567890">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nama Pemilik Rekening</label>
                    <input type="text" name="bank_account_holder" class="form-control" value="{{ old('bank_account_holder', $employee->bank_account_holder ?? '') }}" placeholder="Nama sesuai rekening">
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const provinceSelect = document.getElementById('province_code');
    const citySelect = document.getElementById('city_code');
    const districtSelect = document.getElementById('district_code');
    const villageSelect = document.getElementById('village_code');

    provinceSelect.addEventListener('change', function () {
        let provCode = this.value;
        citySelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
        districtSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';

        if (provCode) {
            fetch(`{{ route('employees.get-cities') }}?province_code=${provCode}`)
                .then(res => res.json())
                .then(data => {
                    Object.entries(data).forEach(([code, name]) => {
                        citySelect.innerHTML += `<option value="${code}">${name}</option>`;
                    });
                })
                .catch(err => console.error("Error fetching cities:", err));
        }
    });

    citySelect.addEventListener('change', function () {
        let cityCode = this.value;
        districtSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';

        if (cityCode) {
            fetch(`{{ route('employees.get-districts') }}?city_code=${cityCode}`)
                .then(res => res.json())
                .then(data => {
                    Object.entries(data).forEach(([code, name]) => {
                        districtSelect.innerHTML += `<option value="${code}">${name}</option>`;
                    });
                })
                .catch(err => console.error("Error fetching districts:", err));
        }
    });

    districtSelect.addEventListener('change', function () {
        let distCode = this.value;
        villageSelect.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';

        if (distCode) {
            fetch(`{{ route('employees.get-villages') }}?district_code=${distCode}`)
                .then(res => res.json())
                .then(data => {
                    Object.entries(data).forEach(([code, name]) => {
                        villageSelect.innerHTML += `<option value="${code}">${name}</option>`;
                    });
                })
                .catch(err => console.error("Error fetching villages:", err));
        }
    });
});
</script>
@endpush