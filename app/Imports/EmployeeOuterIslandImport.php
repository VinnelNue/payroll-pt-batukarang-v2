<?php

namespace App\Imports;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeOuterIslandImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /**
     * Tentukan baris ke berapa header tabel Excel berada (Sesuai gambar di baris 3)
     */
    public function headingRow(): int
    {
        return 3;
    }

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
            // 1. Ambil & Clean NIK (Mendukung berbagai variasi nama kolom)
            $nikKtp = $this->cleanNumber(
                $row['nik'] ?? $row['nik_ktp_outer'] ?? $row['nik_ktp'] ?? null
            );

            // Jika NIK Kosong atau Sudah Terdaftar di Database Outer -> SKIP
            if (empty($nikKtp) || EmployeeOuterIsland::where('nik_ktp_outer', $nikKtp)->exists()) {
                continue;
            }

            // 2. Konversi Tanggal Lahir (Opsional jika ada di Excel, default hari ini jika kosong)
            $birthDateRaw = $row['birth_date_outer'] ?? $row['birth_date'] ?? $row['tanggal_lahir'] ?? null;
            if (!empty($birthDateRaw)) {
                if (is_numeric($birthDateRaw)) {
                    $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDateRaw)->format('Y-m-d');
                } else {
                    $birthDate = date('Y-m-d', strtotime($birthDateRaw));
                }
            } else {
                $birthDate = now()->format('Y-m-d');
            }

            // 3. Konversi Tanggal Mulai Kontrak (start_date)
            $startDateRaw = $row['start_date'] ?? $row['tanggal_mulai'] ?? null;
            if (!empty($startDateRaw)) {
                if (is_numeric($startDateRaw)) {
                    $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDateRaw)->format('Y-m-d');
                } else {
                    $startDate = date('Y-m-d', strtotime($startDateRaw));
                }
            } else {
                $startDate = now()->format('Y-m-d'); // Default hari ini jika tidak ada kolom tanggal mulai
            }

            // 4. Konversi Tanggal Berakhir Kontrak (end_date)
            $endDateRaw = $row['end_date'] ?? $row['tanggal_berakhir'] ?? null;
            $endDate = null;
            if (!empty($endDateRaw)) {
                if (is_numeric($endDateRaw)) {
                    $endDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($endDateRaw)->format('Y-m-d');
                } else {
                    $endDate = date('Y-m-d', strtotime($endDateRaw));
                }
            }

            // 5. Normalisasi Gender (Default L jika tidak ada kolomnya)
            $rawGender = strtoupper(trim($row['gender_outer'] ?? $row['gender'] ?? $row['jenis_kelamin'] ?? 'L'));
            $gender = (str_starts_with($rawGender, 'P') || str_starts_with($rawGender, 'F')) ? 'P' : 'L';

            // 6. Normalisasi Status Pernikahan dari Kolom STATUS KARYAWAN / PTKP (Misal: K01, TK0)
            $statusKaryawanRaw = strtoupper(trim($row['status_karyawan'] ?? $row['ptkp_status'] ?? $row['marital_status'] ?? 'TK0'));
            
            // Tentukan marital status berdasarkan awalan PTKP (K = Married, TK = Single)
            if (str_starts_with($statusKaryawanRaw, 'K')) {
                $maritalStatus = 'married';
            } else {
                $maritalStatus = 'single';
            }

            // 7. Simpan Data dengan Atomic Transaction per Baris
            DB::transaction(function () use ($row, $nikKtp, $birthDate, $gender, $maritalStatus, $startDate, $endDate, $statusKaryawanRaw) {
                
                // Simpan identitas personal Outer Island
                $employee = EmployeeOuterIsland::create([
                    'uuid'                      => (string) Str::uuid(),
                    'no_kk_outer'               => $this->cleanNumber($row['no_kk_outer'] ?? $row['no_kk'] ?? null),
                    'nik_ktp_outer'             => $nikKtp,
                    'full_name_outer'           => $row['nama'] ?? $row['full_name_outer'] ?? $row['full_name'] ?? $row['nama_lengkap'] ?? '',
                    'gender_outer'              => $gender,
                    'birth_place_outer'         => $row['birth_place_outer'] ?? $row['tempat_lahir'] ?? '-',
                    'birth_date_outer'          => $birthDate,
                    'religion_outer'            => $row['religion_outer'] ?? $row['agama'] ?? null,
                    'marital_status_outer'      => $maritalStatus,
                    'phone_number_outer'        => $this->cleanNumber($row['phone_number_outer'] ?? $row['no_hp'] ?? null),
                    'email_outer'               => $row['email_outer'] ?? $row['email'] ?? null,
                    'address_ktp_outer'         => $row['alamat'] ?? $row['address_ktp_outer'] ?? $row['alamat_ktp'] ?? '-',
                    'address_domicile_outer'    => $row['address_domicile_outer'] ?? $row['alamat_domisili'] ?? null,
                    'npwp_number_outer'         => $this->cleanNumber($row['npwp'] ?? $row['npwp_number_outer'] ?? null),
                    'bank_name_outer'           => $row['bank_name_outer'] ?? $row['nama_bank'] ?? null,
                    'bank_account_number_outer' => $this->cleanNumber($row['bank_account_number_outer'] ?? $row['no_rekening'] ?? null),
                    'bank_account_holder_outer' => $row['bank_account_holder_outer'] ?? $row['pemilik_rekening'] ?? null,
                    'ktp_path_outer'            => null,
                    'is_active'                 => true,
                ]);

                // Nonaktifkan kontrak lama jika ada
                ContractOuterIsland::where('employee_outer_island_id', $employee->id_employee_outer_island)
                    ->update(['is_active' => false]);

                // Bersihkan nilai level (ubah tanda strip '-' jadi null atau angka)
                $levelRaw = $row['level'] ?? null;
                $levelClean = (is_numeric($levelRaw)) ? (int)$levelRaw : null;

                // Bersihkan kategori (ubah tanda strip '-' jadi null)
                $categoryRaw = $row['kategori'] ?? $row['category'] ?? null;
                $categoryClean = ($categoryRaw === '-' || empty($categoryRaw)) ? null : trim($categoryRaw);

                // Simpan data kontrak awal Outer Island
                ContractOuterIsland::create([
                    'uuid'                      => (string) Str::uuid(),
                    'employee_outer_island_id'  => $employee->id_employee_outer_island,
                    'job_title'                 => $row['jabatan'] ?? $row['job_title'] ?? 'Staff',
                    'department'                => $row['divisi'] ?? $row['department'] ?? '-',
                    'placement_area'            => $row['area'] ?? $row['placement_area'] ?? '-',
                    'fingerprint_pin'           => $this->cleanNumber($row['fingerprint_pin'] ?? null),
                    'nik_fingerprint'           => $this->cleanNumber($row['nik_fingerprint'] ?? null),
                    'category'                  => $categoryClean,
                    'level'                     => $levelClean,
                    'employment_type'           => $row['employment_type'] ?? 'PKWT',
                    'start_date'                => $startDate,
                    'end_date'                  => $endDate,
                    'basic_salary'              => (float) ($row['basic_salary'] ?? $row['gaji_pokok'] ?? 0),
                    'allowance'                 => (float) ($row['allowance'] ?? $row['tunjangan'] ?? 0),
                    'is_bpjstk_active'          => filter_var($row['is_bpjstk_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'is_bpjs_health_active'     => filter_var($row['is_bpjs_health_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'use_manual_bpjs'           => false,
                    'ptkp_status'               => $statusKaryawanRaw,
                    'is_active'                 => true,
                ]);
            });
        }
    }
}