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

        // Generate 15 Dummy Karyawan PT Batu Karang
        for ($i = 1; $i <= 15; $i++) {
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
                'address_domicile'    => $faker->optional(0.3)->streetAddress, // 30% kemungkinan beda domisili
                'province_code'       => $province->code,
                'city_code'           => $city?->code,
                'district_code'       => $district?->code,
                'village_code'        => $village?->code,
                'npwp_number'         => $faker->numerify('##.###.###.#-###.###'),
                'bank_name'           => $faker->randomElement($banks),
                'bank_account_number' => $faker->bankAccountNumber,
                'bank_account_holder' => strtoupper($fullName),
                'ktp_path'            => null, // Tanpa foto KTP sesuai request
                'is_active'           => true,
            ]);
        }
    }
}