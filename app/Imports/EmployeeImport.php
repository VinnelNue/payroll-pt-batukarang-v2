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
    /**
     * Helper konversi scientific notation (e.g. 3.57812E+15) atau float Excel ke string angka murni
     */
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
            // 1. Bersihkan NIK KTP & NO KK dari Scientific Notation
            $nikKtp = $this->cleanNumber($row['nik_ktp'] ?? $row['nik'] ?? null);
            $noKk   = $this->cleanNumber($row['no_kk'] ?? $row['nomor_kk'] ?? null);

            // 2. Jika NIK Kosong atau Sudah Terdaftar -> SKIP
            if (empty($nikKtp) || Employee::where('nik_ktp', $nikKtp)->exists()) {
                continue;
            }

            // 3. Tangani Konversi Tanggal Lahir
            $birthDate = $row['tanggal_lahir'] ?? null;
            if (is_numeric($birthDate)) {
                $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
            } else {
                $birthDate = $birthDate ? date('Y-m-d', strtotime($birthDate)) : now()->format('Y-m-d');
            }

            // 4. Tangani Konversi Tanggal Mulai Kontrak (jika ada di kolom Excel)
            $startDate = $row['start_date'] ?? $row['tanggal_mulai'] ?? null;
            if (is_numeric($startDate)) {
                $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            } else {
                $startDate = $startDate ? date('Y-m-d', strtotime($startDate)) : now()->format('Y-m-d');
            }

            // Bungkus dalam Transaction agar aman (atomic)
            DB::transaction(function () use ($row, $nikKtp, $noKk, $birthDate, $startDate) {
                // Buat data Employee
                $employee = Employee::create([
                    'uuid'                => (string) Str::uuid(),
                    'nik_ktp'             => $nikKtp,
                    'no_kk'               => $noKk,
                    'full_name'           => $row['nama_lengkap'] ?? $row['nama_len'] ?? '',
                    'gender'              => strtoupper($row['jenis_kelamin'] ?? 'L'),
                    'birth_place'         => $row['tempat_lahir'] ?? '-',
                    'birth_date'          => $birthDate,
                    'religion'            => $row['agama'] ?? null,
                    'marital_status'      => strtolower($row['status_pernikahan'] ?? $row['status_pe'] ?? 'single'),
                    'phone_number'        => $this->cleanNumber($row['no_hp'] ?? null),
                    'email'               => $row['email'] ?? null,
                    'address_ktp'         => $row['alamat_ktp'] ?? $row['alamat_kt'] ?? '-',
                    'address_domicile'    => $row['alamat_domisili'] ?? $row['alamat_domisi'] ?? null,
                    'province_code'       => $this->cleanNumber($row['kode_provinsi'] ?? $row['kode_pro'] ?? null),
                    'city_code'           => $this->cleanNumber($row['kode_kota'] ?? null),
                    'district_code'       => $this->cleanNumber($row['kode_kecamatan'] ?? $row['kode_kec'] ?? null),
                    'village_code'        => $this->cleanNumber($row['kode_kelurahan'] ?? $row['kode_kelu'] ?? null),
                    'npwp_number'         => $this->cleanNumber($row['npwp'] ?? null),
                    'bank_name'           => $row['nama_bank'] ?? $row['nama_bar'] ?? null,
                    'bank_account_number' => $this->cleanNumber($row['no_rekening'] ?? $row['no_rekeni'] ?? null),
                    'bank_account_holder' => $row['pemilik_rekening'] ?? null,
                    'is_active'           => true,
                ]);

                // Nonaktifkan kontrak lama (jika ada)
                EmployeeContract::where('employee_id', $employee->id_employee)->update(['is_active' => false]);

                // Otomatis buat data Contract / Posisi / Gaji dari kolom Excel yang sama
                EmployeeContract::create([
                    'employee_id'     => $employee->id_employee,
                    'job_title'       => $row['job_title'] ?? $row['jabatan'] ?? 'Staff',
                    'department'      => $row['department'] ?? $row['divisi'] ?? '-',
                    'placement_area'  => $row['placement_area'] ?? $row['area_penempatan'] ?? '-',
                    'basic_salary'    => (float) ($row['basic_salary'] ?? $row['gaji_pokok'] ?? 0),
                    'allowance'       => (float) ($row['allowance'] ?? $row['tunjangan'] ?? 0),
                    'employment_type' => $row['employment_type'] ?? $row['status_kontrak'] ?? 'PKWT',
                    'start_date'      => $startDate,
                    'ptkp_status'     => $row['ptkp_status'] ?? $row['ptkp'] ?? 'TK/0',
                    'is_active'       => true,
                ]);
            });
        }
    }
}