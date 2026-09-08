<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private function cleanNumber($value)
    {
        if (empty($value)) return null;

        $str = (string) $value;
        if (is_numeric($value) && str_contains(strtoupper($str), 'E')) {
            return sprintf('%.0f', (float) $value);
        }

        return trim($str);
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // 1. Ambil & Bersihkan NIK KTP
            $nikKtp = $this->cleanNumber($row['nik_ktp'] ?? $row['nik'] ?? null);

            // Jika NIK Kosong atau Sudah Terdaftar di Database -> SKIP
            if (empty($nikKtp) || Employee::where('nik_ktp', $nikKtp)->exists()) {
                continue;
            }

            // 2. Konversi Tanggal Lahir
            $birthDate = $row['birth_date'] ?? $row['tanggal_lahir'] ?? null;
            if (is_numeric($birthDate)) {
                $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
            } else {
                $birthDate = $birthDate ? date('Y-m-d', strtotime($birthDate)) : now()->format('Y-m-d');
            }

            // 3. Konversi Tanggal Mulai Kontrak (start_date)
            $startDate = $row['start_date'] ?? $row['tanggal_mulai'] ?? null;
            if (is_numeric($startDate)) {
                $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            } else {
                $startDate = $startDate ? date('Y-m-d', strtotime($startDate)) : now()->format('Y-m-d');
            }

            // 4. Konversi Tanggal Berakhir Kontrak (end_date)
            $endDate = $row['end_date'] ?? $row['tanggal_berakhir'] ?? null;
            if (!empty($endDate)) {
                if (is_numeric($endDate)) {
                    $endDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($endDate)->format('Y-m-d');
                } else {
                    $endDate = date('Y-m-d', strtotime($endDate));
                }
            } else {
                $endDate = null;
            }

            // Bungkus dalam Transaction agar aman secara atomic
            DB::transaction(function () use ($row, $nikKtp, $birthDate, $startDate, $endDate) {
                // Simpan data identitas personal karyawan
                $employee = Employee::create([
                    'uuid'                => (string) Str::uuid(),
                    'no_kk'               => $this->cleanNumber($row['no_kk'] ?? $row['nomor_kk'] ?? null),
                    'nik_ktp'             => $nikKtp,
                    'full_name'           => $row['full_name'] ?? $row['nama_lengkap'] ?? $row['nama'] ?? '',
                    'gender'              => strtoupper($row['gender'] ?? $row['jenis_kelamin'] ?? 'L'),
                    'religion'            => $row['religion'] ?? $row['agama'] ?? null,
                    'birth_place'         => $row['birth_place'] ?? $row['tempat_lahir'] ?? '-',
                    'birth_date'          => $birthDate,
                    'marital_status'      => strtolower($row['marital_status'] ?? $row['status_pernikahan'] ?? 'single'),
                    'phone_number'        => $this->cleanNumber($row['phone_number'] ?? $row['no_hp'] ?? null),
                    'email'               => $row['email'] ?? null,
                    'address_ktp'         => $row['address_ktp'] ?? $row['alamat_ktp'] ?? $row['alamat'] ?? '-',
                    'province_code'       => $this->cleanNumber($row['province_code'] ?? $row['kode_provinsi'] ?? null),
                    'city_code'           => $this->cleanNumber($row['city_code'] ?? $row['kode_kota'] ?? null),
                    'district_code'       => $this->cleanNumber($row['district_code'] ?? $row['kode_kecamatan'] ?? null),
                    'village_code'        => $this->cleanNumber($row['village_code'] ?? $row['kode_kelurahan'] ?? null),
                    'address_domicile'    => $row['address_domicile'] ?? $row['alamat_domisili'] ?? null,
                    'npwp_number'         => $this->cleanNumber($row['npwp_number'] ?? $row['npwp'] ?? null),
                    'bank_name'           => $row['bank_name'] ?? $row['nama_bank'] ?? null,
                    'bank_account_number' => $this->cleanNumber($row['bank_account_number'] ?? $row['no_rekening'] ?? null),
                    'bank_account_holder' => $row['bank_account_holder'] ?? $row['pemilik_rekening'] ?? null,
                    'is_active'           => true,
                ]);

                // Nonaktifkan kontrak lama jika ada
                EmployeeContract::where('employee_id', $employee->id_employee)->update(['is_active' => false]);

                // Simpan data kontrak, posisi, acuan finansial, pajak & identitas mesin absensi
                EmployeeContract::create([
                    'employee_id'           => $employee->id_employee,
                    'job_title'             => $row['job_title'] ?? $row['jabatan'] ?? 'Staff',
                    'department'            => $row['department'] ?? $row['divisi'] ?? '-',
                    'placement_area'        => $row['placement_area'] ?? $row['area_penempatan'] ?? '-',
                    
                    // Mapping PIN & NIK Mesin Fingerprint dari Excel
                    'fingerprint_pin'       => $this->cleanNumber($row['fingerprint_pin'] ?? $row['pin'] ?? null),
                    'nik_fingerprint'       => $this->cleanNumber($row['nik_fingerprint'] ?? $row['nik_mesin'] ?? null),
                    
                    'category'              => $row['category'] ?? $row['kategori'] ?? null,
                    'level'                 => isset($row['level']) && is_numeric($row['level']) ? (int)$row['level'] : null,
                    'employment_type'       => $row['employment_type'] ?? $row['status_hubungan_kerja'] ?? 'PKWT',
                    'start_date'            => $startDate,
                    'end_date'              => $endDate,
                    'basic_salary'          => (float) ($row['basic_salary'] ?? $row['gaji_pokok'] ?? 0),
                    'allowance'             => (float) ($row['allowance'] ?? $row['tunjangan_tetap'] ?? $row['tunjangan'] ?? 0),
                    'is_bpjstk_active'      => filter_var($row['is_bpjstk_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'is_bpjs_health_active' => filter_var($row['is_bpjs_health_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'use_manual_bpjs'       => false,
                    'ptkp_status'           => $row['status_karyawan'] ?? $row['ptkp_status'] ?? $row['ptkp'] ?? 'TK0',
                    'is_active'             => true,
                ]);
            });
        }
    }
}