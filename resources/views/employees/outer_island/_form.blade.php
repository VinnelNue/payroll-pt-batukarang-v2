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
                    <h6 class="fw-bold text-primary mb-1">Identitas Personal & Dokumen Luar Pulau</h6>
                    <p class="text-muted small mb-0">Masukkan data identitas sesuai dokumen resmi karyawan.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label for="no_kk_outer" class="form-label fw-semibold text-dark">Nomor Kartu Keluarga (KK)</label>
                    <input id="no_kk_outer" type="text" name="no_kk_outer" class="form-control @error('no_kk_outer') is-invalid @enderror" value="{{ old('no_kk_outer', $employee->no_kk_outer ?? '') }}" placeholder="16 digit nomor Kartu Keluarga" maxlength="16">
                    @error('no_kk_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="nik_ktp_outer" class="form-label fw-semibold text-dark">NIK KTP <span class="text-danger">*</span></label>
                    <input id="nik_ktp_outer" type="text" name="nik_ktp_outer" class="form-control @error('nik_ktp_outer') is-invalid @enderror" value="{{ old('nik_ktp_outer', $employee->nik_ktp_outer ?? '') }}" placeholder="16 digit angka KTP" maxlength="16" required>
                    @error('nik_ktp_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="full_name_outer" class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input id="full_name_outer" type="text" name="full_name_outer" class="form-control @error('full_name_outer') is-invalid @enderror" value="{{ old('full_name_outer', $employee->full_name_outer ?? '') }}" placeholder="Nama lengkap sesuai KTP" required>
                    @error('full_name_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="gender_outer" class="form-label fw-semibold text-dark">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select id="gender_outer" name="gender_outer" class="form-select @error('gender_outer') is-invalid @enderror" required>
                        <option value="L" {{ old('gender_outer', $employee->gender_outer ?? 'L') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('gender_outer', $employee->gender_outer ?? 'L') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    @error('gender_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="religion_outer" class="form-label fw-semibold text-dark">Agama</label>
                    <select id="religion_outer" name="religion_outer" class="form-select @error('religion_outer') is-invalid @enderror">
                        <option value="">-- Pilih Agama --</option>
                        @foreach(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'] as $rel)
                            <option value="{{ $rel }}" {{ old('religion_outer', $employee->religion_outer ?? '') == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                    @error('religion_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="birth_place_outer" class="form-label fw-semibold text-dark">Tempat Lahir <span class="text-danger">*</span></label>
                    <input id="birth_place_outer" type="text" name="birth_place_outer" class="form-control @error('birth_place_outer') is-invalid @enderror" value="{{ old('birth_place_outer', $employee->birth_place_outer ?? '') }}" placeholder="Kota kelahiran" required>
                    @error('birth_place_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="birth_date_outer" class="form-label fw-semibold text-dark">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input id="birth_date_outer" type="date" name="birth_date_outer" class="form-control @error('birth_date_outer') is-invalid @enderror" value="{{ old('birth_date_outer', $employee->birth_date_outer ?? '') }}" placeholder="" required>
                    @error('birth_date_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="marital_status_outer" class="form-label fw-semibold text-dark">Status Pernikahan <span class="text-danger">*</span></label>
                    <select id="marital_status_outer" name="marital_status_outer" class="form-select @error('marital_status_outer') is-invalid @enderror" required>
                        <option value="single" {{ old('marital_status_outer', $employee->marital_status_outer ?? 'single') == 'single' ? 'selected' : '' }}>Belum Menikah (Single)</option>
                        <option value="married" {{ old('marital_status_outer', $employee->marital_status_outer ?? 'single') == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status_outer', $employee->marital_status_outer ?? 'single') == 'divorced' ? 'selected' : '' }}>Cerai</option>
                    </select>
                    @error('marital_status_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 pt-2">
                    <label for="ktp_path_outer" class="form-label fw-semibold text-dark">Upload Scan / Foto KTP</label>
                    <input id="ktp_path_outer" type="file" name="ktp_path_outer" class="form-control @error('ktp_path_outer') is-invalid @enderror" accept="image/*,.pdf">
                    <div class="form-text">Format yang disarankan: JPG, PNG, atau PDF. Maksimal 5 MB.</div>
                    @error('ktp_path_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($employee) && !empty($employee->ktp_path_outer))
                        <div class="mt-3">
                            <a href="{{ asset('storage/' . $employee->ktp_path_outer) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary rounded-pill">
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
                    <label for="phone_number_outer" class="form-label fw-semibold text-dark">No. HP / WhatsApp</label>
                    <input id="phone_number_outer" type="text" name="phone_number_outer" class="form-control @error('phone_number_outer') is-invalid @enderror" value="{{ old('phone_number_outer', $employee->phone_number_outer ?? '') }}" placeholder="08123456789">
                    @error('phone_number_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email_outer" class="form-label fw-semibold text-dark">Email</label>
                    <input id="email_outer" type="email" name="email_outer" class="form-control @error('email_outer') is-invalid @enderror" value="{{ old('email_outer', $employee->email_outer ?? '') }}" placeholder="karyawan@batukarang.com">
                    @error('email_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="address_ktp_outer" class="form-label fw-semibold text-dark">Alamat KTP <span class="text-danger">*</span></label>
                    <textarea id="address_ktp_outer" name="address_ktp_outer" class="form-control @error('address_ktp_outer') is-invalid @enderror" rows="2" placeholder="Alamat lengkap sesuai KTP" required>{{ old('address_ktp_outer', $employee->address_ktp_outer ?? '') }}</textarea>
                    @error('address_ktp_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="address_domicile_outer" class="form-label fw-semibold text-dark">Alamat Domisili (opsional)</label>
                    <textarea id="address_domicile_outer" name="address_domicile_outer" class="form-control @error('address_domicile_outer') is-invalid @enderror" rows="2" placeholder="Isi jika alamat domisili berbeda dengan KTP">{{ old('address_domicile_outer', $employee->address_domicile_outer ?? '') }}</textarea>
                    @error('address_domicile_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="npwp_number_outer" class="form-label fw-semibold text-dark">Nomor NPWP</label>
                    <input id="npwp_number_outer" type="text" name="npwp_number_outer" class="form-control @error('npwp_number_outer') is-invalid @enderror" value="{{ old('npwp_number_outer', $employee->npwp_number_outer ?? '') }}" placeholder="00.000.000.0-000.000">
                    @error('npwp_number_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_name_outer" class="form-label fw-semibold text-dark">Nama Bank</label>
                    <input id="bank_name_outer" type="text" name="bank_name_outer" class="form-control @error('bank_name_outer') is-invalid @enderror" value="{{ old('bank_name_outer', $employee->bank_name_outer ?? '') }}" placeholder="BCA / Mandiri / BRI">
                    @error('bank_name_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_account_number_outer" class="form-label fw-semibold text-dark">No. Rekening Bank</label>
                    <input id="bank_account_number_outer" type="text" name="bank_account_number_outer" class="form-control @error('bank_account_number_outer') is-invalid @enderror" value="{{ old('bank_account_number_outer', $employee->bank_account_number_outer ?? '') }}" placeholder="1234567890">
                    @error('bank_account_number_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="bank_account_holder_outer" class="form-label fw-semibold text-dark">Nama Pemilik Rekening</label>
                    <input id="bank_account_holder_outer" type="text" name="bank_account_holder_outer" class="form-control @error('bank_account_holder_outer') is-invalid @enderror" value="{{ old('bank_account_holder_outer', $employee->bank_account_holder_outer ?? '') }}" placeholder="Nama sesuai rekening">
                    @error('bank_account_holder_outer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </section>
    </div>
</div>
