<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EmployeeImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Tangani konversi tanggal dari serial number Excel maupun format string biasa
        $birthDate = $row['tanggal_lahir'] ?? null;
        if (is_numeric($birthDate)) {
            $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
        } else {
            $birthDate = $birthDate ? date('Y-m-d', strtotime($birthDate)) : now()->format('Y-m-d');
        }

        return new Employee([
            'uuid'                => (string) Str::uuid(),
            'nik_ktp'             => (string) ($row['nik_ktp'] ?? ''),
            'full_name'           => $row['nama_lengkap'] ?? '',
            'nickname'            => $row['panggilan'] ?? null,
            'gender'              => strtoupper($row['jenis_kelamin'] ?? 'L'),
            'birth_place'         => $row['tempat_lahir'] ?? '-',
            'birth_date'          => $birthDate,
            'religion'            => $row['agama'] ?? null,
            'marital_status'      => strtolower($row['status_pernikahan'] ?? 'single'),
            'phone_number'        => $row['no_hp'] ?? null,
            'email'               => $row['email'] ?? null,
            'address_ktp'         => $row['alamat_ktp'] ?? '-',
            'address_domicile'    => $row['alamat_domisili'] ?? null,
            'province_code'       => $row['kode_provinsi'] ?? null,
            'city_code'           => $row['kode_kota'] ?? null,
            'district_code'       => $row['kode_kecamatan'] ?? null,
            'village_code'        => $row['kode_kelurahan'] ?? null,
            'npwp_number'         => $row['npwp'] ?? null,
            'bank_name'           => $row['nama_bank'] ?? null,
            'bank_account_number' => $row['no_rekening'] ?? null,
            'bank_account_holder' => $row['pemilik_rekening'] ?? null,
            'is_active'           => true,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.nik_ktp'       => 'required|unique:employees,nik_ktp',
            '*.nama_lengkap'  => 'required|string|max:255',
            '*.jenis_kelamin' => 'required|in:L,P,l,p',
            '*.tempat_lahir'  => 'required',
            '*.tanggal_lahir' => 'required',
            '*.alamat_ktp'    => 'required',
        ];
    }
}