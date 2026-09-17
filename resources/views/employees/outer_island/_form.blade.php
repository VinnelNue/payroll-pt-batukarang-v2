<div class="row g-4">
    <!-- SECTION 1: IDENTITAS DIRI & DOKUMEN LUAR PULAU -->
    <div class="col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                <i class="fa-solid fa-id-card me-2"></i> Identitas Personal & Dokumen Luar Pulau
            </h6>

            <div class="row g-3">
                <!-- NOMOR KK -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Nomor Kartu Keluarga (KK)</label>
                    <input type="text" name="no_kk_outer" class="form-control @error('no_kk_outer') is-invalid @enderror" 
                        value="{{ old('no_kk_outer', $employee->no_kk_outer ?? '') }}" maxlength="16" placeholder="16 digit nomor Kartu Keluarga">
                    @error('no_kk_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- NIK KTP -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">NIK KTP <span class="text-danger">*</span></label>
                    <input type="text" name="nik_ktp_outer" class="form-control @error('nik_ktp_outer') is-invalid @enderror" 
                        value="{{ old('nik_ktp_outer', $employee->nik_ktp_outer ?? '') }}" maxlength="16" required placeholder="16 digit angka KTP">
                    @error('nik_ktp_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- NAMA LENGKAP -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="full_name_outer" class="form-control @error('full_name_outer') is-invalid @enderror" 
                        value="{{ old('full_name_outer', $employee->full_name_outer ?? '') }}" required placeholder="Nama lengkap sesuai KTP">
                    @error('full_name_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- JENIS KELAMIN -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="gender_outer" class="form-select @error('gender_outer') is-invalid @enderror" required>
                        <option value="L" {{ old('gender_outer', $employee->gender_outer ?? '') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender_outer', $employee->gender_outer ?? '') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    @error('gender_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- AGAMA -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Agama</label>
                    <select name="religion_outer" class="form-select @error('religion_outer') is-invalid @enderror">
                        <option value="">-- Pilih Agama --</option>
                        @foreach(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'] as $rel)
                            <option value="{{ $rel }}" {{ old('religion_outer', $employee->religion_outer ?? '') == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                    @error('religion_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- TEMPAT LAHIR -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Tempat Lahir <span class="text-danger">*</span></label>
                    <input type="text" name="birth_place_outer" class="form-control @error('birth_place_outer') is-invalid @enderror" 
                        value="{{ old('birth_place_outer', $employee->birth_place_outer ?? '') }}" required placeholder="Kota kelahiran">
                    @error('birth_place_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- TANGGAL LAHIR -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input type="date" name="birth_date_outer" class="form-control @error('birth_date_outer') is-invalid @enderror" 
                        value="{{ old('birth_date_outer', $employee->birth_date_outer ?? '') }}" required>
                    @error('birth_date_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- STATUS PERNIKAHAN -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Status Pernikahan <span class="text-danger">*</span></label>
                    <select name="marital_status_outer" class="form-select @error('marital_status_outer') is-invalid @enderror" required>
                        <option value="single" {{ old('marital_status_outer', $employee->marital_status_outer ?? '') == 'single' ? 'selected' : '' }}>Belum Menikah (Single)</option>
                        <option value="married" {{ old('marital_status_outer', $employee->marital_status_outer ?? '') == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status_outer', $employee->marital_status_outer ?? '') == 'divorced' ? 'selected' : '' }}>Cerai</option>
                    </select>
                    @error('marital_status_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- UPLOAD FILE KTP -->
                <div class="col-md-12 mt-3">
                    <label class="form-label fw-semibold text-dark">Upload Scan / Foto KTP (.jpg, .png, .pdf max 5MB)</label>
                    <input type="file" name="ktp_file_outer" class="form-control @error('ktp_file_outer') is-invalid @enderror" accept="image/*,.pdf">
                    @error('ktp_file_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror

                    @if(isset($employee) && !empty($employee->ktp_path))
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

    <!-- SECTION 2: KONTAK, ALAMAT & REKENING -->
    <div class="col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                <i class="fa-solid fa-map-location-dot me-2"></i> Kontak, Alamat & Rekening Payroll
            </h6>

            <div class="row g-3">
                <!-- TELEPON -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">No. HP / WhatsApp</label>
                    <input type="text" name="phone_number_outer" class="form-control @error('phone_number_outer') is-invalid @enderror" 
                        value="{{ old('phone_number_outer', $employee->phone_number_outer ?? '') }}" placeholder="08123456789">
                    @error('phone_number_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- EMAIL -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Email</label>
                    <input type="email" name="email_outer" class="form-control @error('email_outer') is-invalid @enderror" 
                        value="{{ old('email_outer', $employee->email_outer ?? '') }}" placeholder="karyawan@batukarang.com">
                    @error('email_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- ALAMAT KTP -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Alamat KTP <span class="text-danger">*</span></label>
                    <textarea name="address_ktp_outer" class="form-control @error('address_ktp_outer') is-invalid @enderror" rows="2" required placeholder="Alamat lengkap sesuai KTP">{{ old('address_ktp_outer', $employee->address_ktp_outer ?? '') }}</textarea>
                    @error('address_ktp_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- ALAMAT DOMISILI -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-dark">Alamat Domisili (opsional)</label>
                    <textarea name="address_domicile_outer" class="form-control @error('address_domicile_outer') is-invalid @enderror" rows="1" placeholder="Isi jika alamat domisili berbeda dengan KTP">{{ old('address_domicile_outer', $employee->address_domicile_outer ?? '') }}</textarea>
                    @error('address_domicile_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <!-- REKENING & NPWP -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nomor NPWP</label>
                    <input type="text" name="npwp_number_outer" class="form-control @error('npwp_number_outer') is-invalid @enderror" 
                        value="{{ old('npwp_number_outer', $employee->npwp_number_outer ?? '') }}" placeholder="00.000.000.0-000.000">
                    @error('npwp_number_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nama Bank</label>
                    <input type="text" name="bank_name_outer" class="form-control @error('bank_name_outer') is-invalid @enderror" 
                        value="{{ old('bank_name_outer', $employee->bank_name_outer ?? '') }}" placeholder="BCA / Mandiri / BRI">
                    @error('bank_name_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">No. Rekening Bank</label>
                    <input type="text" name="bank_account_number_outer" class="form-control @error('bank_account_number_outer') is-invalid @enderror" 
                        value="{{ old('bank_account_number_outer', $employee->bank_account_number_outer ?? '') }}" placeholder="1234567890">
                    @error('bank_account_number_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nama Pemilik Rekening</label>
                    <input type="text" name="bank_account_holder_outer" class="form-control @error('bank_account_holder_outer') is-invalid @enderror" 
                        value="{{ old('bank_account_holder_outer', $employee->bank_account_holder_outer ?? '') }}" placeholder="Nama sesuai rekening">
                    @error('bank_account_holder_outer') 
                        <div class="invalid-feedback">{{ $message }}</div> 
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>