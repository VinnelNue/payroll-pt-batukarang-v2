<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    /**
     * Helper konversi scientific notation (e.g. 3.57812E+15) atau float Excel ke string angka murni
     */
    private function cleanNumber($value)
    {
        if (empty($value)) return null;

        $str = (string) $value;
        // Jika terdeteksi Notasi Ilmiah E+ dari Excel
        if (is_numeric($value) && str_contains(strtoupper($str), 'E')) {
            return sprintf('%.0f', (float) $value);
        }

        return trim($str);
    }

    public function model(array $row)
    {
        // 1. Bersihkan NIK KTP & NO KK dari Scientific Notation
        $nikKtp = $this->cleanNumber($row['nik_ktp'] ?? $row['nik'] ?? null);
        $noKk   = $this->cleanNumber($row['no_kk'] ?? $row['nomor_kk'] ?? null);

        // 2. Jika NIK Kosong atau Sudah Terdaftar di Database -> SKIP (Cegah Duplicate Exception)
        if (empty($nikKtp) || Employee::where('nik_ktp', $nikKtp)->exists()) {
            return null;
        }

        // 3. Tangani Konversi Tanggal Lahir (Excel Serial Number vs String)
        $birthDate = $row['tanggal_lahir'] ?? null;
        if (is_numeric($birthDate)) {
            $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
        } else {
            $birthDate = $birthDate ? date('Y-m-d', strtotime($birthDate)) : now()->format('Y-m-d');
        }

        return new Employee([
            'uuid'                => (string) Str::uuid(),
            'nik_ktp'             => $nikKtp,
            'no_kk'               => $noKk, // Tambahkan NO KK
            'full_name'           => $row['nama_lengkap'] ?? $row['nama_len'] ?? '',
            // 'nickname' dihapus
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
    }
}