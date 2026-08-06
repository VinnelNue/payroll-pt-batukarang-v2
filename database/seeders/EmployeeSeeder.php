<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Ambil beberapa sampel wilayah Laravolt
        $provinces = Province::limit(10)->get();

        if ($provinces->isEmpty()) {
            $this->command->error('Tabel wilayah Indonesia masih kosong! Jalankan seeder Laravolt terlebih dahulu.');
            return;
        }

        $banks = ['BCA', 'Mandiri', 'BRI', 'BNI', 'CIMB Niaga'];
        $religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha'];

        // Daftar 28 Karyawan Asli dari Log Mesin Fingerprint
        $realEmployees = [
            ['nik' => '14SR05', 'name' => 'PEDRO LAKSANA PUTRA', 'gender' => 'L'],
            ['nik' => '15GB01', 'name' => 'SUSIANTO', 'gender' => 'L'],
            ['nik' => '15GB02', 'name' => 'Viky Tridianto', 'gender' => 'L'],
            ['nik' => '15GB03', 'name' => 'AGUNG HARI WIBOWO', 'gender' => 'L'],
            ['nik' => '15GB04', 'name' => 'DEFRI FAUJANTO', 'gender' => 'L'],
            ['nik' => '15GB05', 'name' => 'NANDO RISQI WAHYUDI', 'gender' => 'L'],
            ['nik' => '15GB08', 'name' => 'AYUN DWI TIARANDA', 'gender' => 'P'],
            ['nik' => '15GB09', 'name' => 'Hengki Yohanes Pranata', 'gender' => 'L'],
            ['nik' => '15GB10', 'name' => 'Maulana Nur Pramujah', 'gender' => 'L'],
            ['nik' => '16RJ01', 'name' => 'SUGENG HARIYANTO', 'gender' => 'L'],
            ['nik' => '16RJ02', 'name' => 'VICKY DARMA FIRMANSYA', 'gender' => 'L'],
            ['nik' => '16RJ04', 'name' => 'SUPANDRI', 'gender' => 'L'],
            ['nik' => '16RJ05', 'name' => 'M. ANGGI WIJAKSONO', 'gender' => 'L'],
            ['nik' => '17GP03', 'name' => 'EMI SUGIARTI', 'gender' => 'P'],
            ['nik' => '17GP04', 'name' => 'SUMARTIN', 'gender' => 'P'],
            ['nik' => '17GP05', 'name' => 'YAYUK WIYANTI', 'gender' => 'P'],
            ['nik' => '17GP06', 'name' => 'SANDI MAULANA', 'gender' => 'L'],
            ['nik' => '17GP07', 'name' => 'GILANG SATRIYA FERDIANSAH', 'gender' => 'L'],
            ['nik' => '18HS02', 'name' => 'RIBOWO', 'gender' => 'L'],
            ['nik' => '18HS03', 'name' => 'AHMAD NURUL ANWAR', 'gender' => 'L'],
            ['nik' => '18HS04', 'name' => 'DEBI NUR ALYUBI', 'gender' => 'L'],
            ['nik' => '18RT01', 'name' => 'SUPATMI', 'gender' => 'P'],
            ['nik' => '18RT02', 'name' => 'SUPRAPTI', 'gender' => 'P'],
            ['nik' => '18RT03', 'name' => 'IMAYAH', 'gender' => 'P'],
            ['nik' => '18RT04', 'name' => 'SUMARIYANI', 'gender' => 'P'],
            ['nik' => '18RT06', 'name' => "SAB'I MUBAROK", 'gender' => 'L'],
            ['nik' => '18RT07', 'name' => 'MOHAMMAD YUSSRIL', 'gender' => 'L'],
            ['nik' => '18RT08', 'name' => 'DIAS KHOLIFATUR AKBAR', 'gender' => 'L'],
        ];

        foreach ($realEmployees as $empData) {
            // Pick random location from Laravolt
            $province = $provinces->random();
            $city     = City::where('province_code', $province->code)->inRandomOrder()->first();
            $district = $city ? District::where('city_code', $city->code)->inRandomOrder()->first() : null;
            $village  = $district ? Village::where('district_code', $district->code)->inRandomOrder()->first() : null;

            Employee::updateOrCreate(
                ['nik_ktp' => $empData['nik']], // Kunci unik dari Fingerprint Excel
                [
                    'uuid'                => (string) Str::uuid(),
                    'no_kk'               => '35' . $faker->numerify('##############'),
                    'full_name'           => $empData['name'],
                    // 'nickname' disembunyikan/dihapus sesuai skema baru
                    'gender'              => $empData['gender'],
                    'birth_place'         => $faker->city,
                    'birth_date'          => $faker->dateTimeBetween('-40 years', '-20 years')->format('Y-m-d'),
                    'religion'            => $faker->randomElement($religions),
                    'marital_status'      => $faker->randomElement(['single', 'married']),
                    'phone_number'        => '08' . $faker->numerify('##########'),
                    'email'               => strtolower(Str::slug($empData['name'])) . '@batukarang.com',
                    'address_ktp'         => $faker->streetAddress,
                    'address_domicile'    => null,
                    'province_code'       => $province->code,
                    'city_code'           => $city?->code,
                    'district_code'       => $district?->code,
                    'village_code'        => $village?->code,
                    'npwp_number'         => $faker->numerify('##.###.###.#-###.###'),
                    'bank_name'           => $faker->randomElement($banks),
                    'bank_account_number' => $faker->bankAccountNumber,
                    'bank_account_holder' => strtoupper($empData['name']),
                    'ktp_path'            => null,
                    'is_active'           => true,
                ]
            );
        }
    }
}