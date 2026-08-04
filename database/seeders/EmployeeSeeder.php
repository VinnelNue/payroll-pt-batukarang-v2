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

        // Ambil beberapa sampel wilayah untuk di-assign secara acak
        $provinces = Province::limit(10)->get();

        if ($provinces->isEmpty()) {
            $this->command->error('Tabel wilayah Indonesia masih kosong! Jalankan seeder Laravolt terlebih dahulu.');
            return;
        }

        $banks = ['BCA', 'Mandiri', 'BRI', 'BNI', 'CIMB Niaga'];
        $religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha'];

        // Sample wilayah untuk Yohana
        $provYohana = $provinces->random();
        $cityYohana = City::where('province_code', $provYohana->code)->inRandomOrder()->first();
        $distYohana = $cityYohana ? District::where('city_code', $cityYohana->code)->inRandomOrder()->first() : null;
        $villYohana = $distYohana ? Village::where('district_code', $distYohana->code)->inRandomOrder()->first() : null;

        // ==========================================
        // 1. DATA KHUSUS: YOHANA FERNANDEZ
        // ==========================================
        Employee::updateOrCreate(
            ['email' => 'yohanafernandez@gmail.com'], // Patokan unik agar tidak bentrok
            [
                'uuid'                => (string) Str::uuid(),
                'nik_ktp'             => '35' . $faker->unique()->numerify('##############'),
                'full_name'           => 'Yohana Fernandez',
                'nickname'            => 'Yohana',
                'gender'              => 'P',
                'birth_place'         => $faker->city,
                'birth_date'          => '1996-05-15',
                'religion'            => 'Katolik',
                'marital_status'      => 'single',
                'phone_number'        => '08' . $faker->numerify('##########'),
                'address_ktp'         => $faker->streetAddress,
                'address_domicile'    => null,
                'province_code'       => $provYohana->code,
                'city_code'           => $cityYohana?->code,
                'district_code'       => $distYohana?->code,
                'village_code'        => $villYohana?->code,
                'npwp_number'         => $faker->numerify('##.###.###.#-###.###'),
                'bank_name'           => 'BCA',
                'bank_account_number' => $faker->bankAccountNumber,
                'bank_account_holder' => 'YOHANA FERNANDEZ',
                'ktp_path'            => null,
                'is_active'           => true,
            ]
        );

        // ==========================================
        // 2. GENERATE 14 DUMMY KARYAWAN LAINNYA
        // ==========================================
        for ($i = 1; $i <= 14; $i++) {
            $gender = $faker->randomElement(['L', 'P']);
            $firstName = $gender == 'L' ? $faker->firstNameMale : $faker->firstNameFemale;
            $fullName = $firstName . ' ' . $faker->lastName;

            // Pilih provinsi acak dan cascading child-nya
            $province = $provinces->random();
            $city = City::where('province_code', $province->code)->inRandomOrder()->first();
            $district = $city ? District::where('city_code', $city->code)->inRandomOrder()->first() : null;
            $village = $district ? Village::where('district_code', $district->code)->inRandomOrder()->first() : null;

            Employee::create([
                'uuid'                => (string) Str::uuid(),
                'nik_ktp'             => '35' . $faker->unique()->numerify('##############'),
                'full_name'           => $fullName,
                'nickname'            => $firstName,
                'gender'              => $gender,
                'birth_place'         => $faker->city,
                'birth_date'          => $faker->dateTimeBetween('-40 years', '-20 years')->format('Y-m-d'),
                'religion'            => $faker->randomElement($religions),
                'marital_status'      => $faker->randomElement(['single', 'married', 'divorced']),
                'phone_number'        => '08' . $faker->numerify('##########'),
                'email'               => strtolower(Str::slug($fullName)) . '@batukarang.com',
                'address_ktp'         => $faker->streetAddress,
                'address_domicile'    => $faker->optional(0.3)->streetAddress,
                'province_code'       => $province->code,
                'city_code'           => $city?->code,
                'district_code'       => $district?->code,
                'village_code'        => $village?->code,
                'npwp_number'         => $faker->numerify('##.###.###.#-###.###'),
                'bank_name'           => $faker->randomElement($banks),
                'bank_account_number' => $faker->bankAccountNumber,
                'bank_account_holder' => strtoupper($fullName),
                'ktp_path'            => null,
                'is_active'           => true,
            ]);
        }
    }
}   