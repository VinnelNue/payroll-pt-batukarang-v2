<?php

namespace App\Imports;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland; // Pastikan model contract di-import
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeOuterIslandImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
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
            // 1. Matchmaking & Clean NIK KTP
            $nikKtp = $this->cleanNumber(
                $row['nik_ktp_outer'] ?? $row['nik_ktp'] ?? $row['nik'] ?? null
            );

            // Jika NIK Kosong atau Sudah Terdaftar di Database Outer -> SKIP
            if (empty($nikKtp) || EmployeeOuterIsland::where('nik_ktp_outer', $nikKtp)->exists()) {
                continue;
            }

            // 2. Matchmaking & Konversi Tanggal Lahir
            $birthDateRaw = $row['birth_date_outer'] ?? $row['birth_date'] ?? $row['tanggal_lahir'] ?? null;
            if (is_numeric($birthDateRaw)) {
                $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDateRaw)->format('Y-m-d');
            } else {
                $birthDate = $birthDateRaw ? date('Y-m-d', strtotime($birthDateRaw)) : now()->format('Y-m-d');
            }

            // 3. Konversi Tanggal Mulai Kontrak (start_date)
            $startDateRaw = $row['start_date'] ?? $row['tanggal_mulai'] ?? null;
            if (is_numeric($startDateRaw)) {
                $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDateRaw)->format('Y-m-d');
            } else {
                $startDate = $startDateRaw ? date('Y-m-d', strtotime($startDateRaw)) : now()->format('Y-m-d');
            }

            // 4. Konversi Tanggal Berakhir Kontrak (end_date)
            $endDateRaw = $row['end_date'] ?? $row['tanggal_berakhir'] ?? null;
            if (!empty($endDateRaw)) {
                if (is_numeric($endDateRaw)) {
                    $endDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($endDateRaw)->format('Y-m-d');
                } else {
                    $endDate = date('Y-m-d', strtotime($endDateRaw));
                }
            } else {
                $endDate = null;
            }

            // 5. Normalisasi Gender (L/P)
            $rawGender = strtoupper(trim($row['gender_outer'] ?? $row['gender'] ?? $row['jenis_kelamin'] ?? 'L'));
            $gender = (str_starts_with($rawGender, 'P') || str_starts_with($rawGender, 'F')) ? 'P' : 'L';

            // 6. Normalisasi Status Pernikahan (single, married, divorced)
            $rawStatus = strtolower(trim($row['marital_status_outer'] ?? $row['marital_status'] ?? $row['status_pernikahan'] ?? 'single'));
            if (in_array($rawStatus, ['married', 'menikah', 'kawin'])) {
                $maritalStatus = 'married';
            } elseif (in_array($rawStatus, ['divorced', 'duda', 'janda', 'cerai'])) {
                $maritalStatus = 'divorced';
            } else {
                $maritalStatus = 'single';
            }

            // 7. Simpan Data dengan Atomic Transaction per Baris
            DB::transaction(function () use ($row, $nikKtp, $birthDate, $gender, $maritalStatus, $startDate, $endDate) {
                // Simpan identitas personal Outer Island
                $employee = EmployeeOuterIsland::create([
                    'uuid'                      => (string) Str::uuid(),
                    'no_kk_outer'               => $this->cleanNumber($row['no_kk_outer'] ?? $row['no_kk'] ?? $row['nomor_kk'] ?? null),
                    'nik_ktp_outer'             => $nikKtp,
                    'full_name_outer'           => $row['full_name_outer'] ?? $row['full_name'] ?? $row['nama_lengkap'] ?? $row['nama'] ?? '',
                    'gender_outer'              => $gender,
                    'birth_place_outer'         => $row['birth_place_outer'] ?? $row['birth_place'] ?? $row['tempat_lahir'] ?? '-',
                    'birth_date_outer'          => $birthDate,
                    'religion_outer'            => $row['religion_outer'] ?? $row['religion'] ?? $row['agama'] ?? null,
                    'marital_status_outer'      => $maritalStatus,
                    'phone_number_outer'        => $this->cleanNumber($row['phone_number_outer'] ?? $row['phone_number'] ?? $row['no_hp'] ?? null),
                    'email_outer'               => $row['email_outer'] ?? $row['email'] ?? null,
                    'address_ktp_outer'         => $row['address_ktp_outer'] ?? $row['address_ktp'] ?? $row['alamat_ktp'] ?? $row['alamat'] ?? '-',
                    'address_domicile_outer'    => $row['address_domicile_outer'] ?? $row['address_domicile'] ?? $row['alamat_domisili'] ?? null,
                    'npwp_number_outer'         => $this->cleanNumber($row['npwp_number_outer'] ?? $row['npwp_number'] ?? $row['npwp'] ?? null),
                    'bank_name_outer'           => $row['bank_name_outer'] ?? $row['bank_name'] ?? $row['nama_bank'] ?? null,
                    'bank_account_number_outer' => $this->cleanNumber($row['bank_account_number_outer'] ?? $row['bank_account_number'] ?? $row['no_rekening'] ?? null),
                    'bank_account_holder_outer' => $row['bank_account_holder_outer'] ?? $row['bank_account_holder'] ?? $row['pemilik_rekening'] ?? null,
                    'ktp_path_outer'            => null,
                    'is_active'                 => true,
                ]);

                // Nonaktifkan kontrak lama jika ada (untuk jaga-jaga)
                ContractOuterIsland::where('employee_outer_island_id', $employee->id_employee_outer_island)
                    ->update(['is_active' => false]);

                // Simpan data kontrak awal Outer Island (Menyesuaikan dengan struktur migrasi employee_contracts_outer_island)
                ContractOuterIsland::create([
                    'uuid'                      => (string) Str::uuid(),
                    'employee_outer_island_id'  => $employee->id_employee_outer_island,
                    'job_title'                 => $row['job_title'] ?? $row['jabatan'] ?? 'Staff',
                    'department'                => $row['department'] ?? $row['divisi'] ?? '-',
                    'placement_area'            => $row['placement_area'] ?? $row['area_penempatan'] ?? '-',
                    'fingerprint_pin'           => $this->cleanNumber($row['fingerprint_pin'] ?? $row['pin'] ?? null),
                    'nik_fingerprint'           => $this->cleanNumber($row['nik_fingerprint'] ?? $row['nik_mesin'] ?? null),
                    'category'                  => $row['category'] ?? $row['kategori'] ?? null,
                    'level'                     => isset($row['level']) && is_numeric($row['level']) ? (int)$row['level'] : null,
                    'employment_type'           => $row['employment_type'] ?? $row['status_hubungan_kerja'] ?? 'PKWT',
                    'start_date'                => $startDate,
                    'end_date'                  => $endDate,
                    'basic_salary'              => (float) ($row['basic_salary'] ?? $row['gaji_pokok'] ?? 0),
                    'allowance'                 => (float) ($row['allowance'] ?? $row['tunjangan_tetap'] ?? $row['tunjangan'] ?? 0),
                    'is_bpjstk_active'          => filter_var($row['is_bpjstk_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'is_bpjs_health_active'     => filter_var($row['is_bpjs_health_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'use_manual_bpjs'           => false,
                    'ptkp_status'               => $row['status_karyawan'] ?? $row['ptkp_status'] ?? $row['ptkp'] ?? 'TK/0',
                    'is_active'                 => true,
                ]);
            });
        }
    }
}