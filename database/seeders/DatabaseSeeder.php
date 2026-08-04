<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Panggil Seeder Wilayah Laravolt Terlebih Dahulu
        $this->call([
            \Laravolt\Indonesia\Seeds\ProvincesSeeder::class,
            \Laravolt\Indonesia\Seeds\CitiesSeeder::class,
            \Laravolt\Indonesia\Seeds\DistrictsSeeder::class,
            \Laravolt\Indonesia\Seeds\VillagesSeeder::class,
            EmployeeSeeder::class, // Panggil EmployeeSeeder setelah data wilayah terisi
        ]);

        User::insert([
            [
                'name'       => 'Kevin Immanuel',
                'email'      => 'spradmin@gmail.com',
                'password'   => bcrypt('spradmin'),
                'role'       => 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Yohana Fernandez',
                'email'      => 'yohanafernandez@gmail.com',
                'password'   => bcrypt('yohanafernandez'),
                'role'       => 'manager_keuangan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

    }
}