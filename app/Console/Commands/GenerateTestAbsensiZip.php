<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class GenerateTestAbsensiZip extends Command
{
    protected $signature = 'generate:absensi-zip';
    protected $description = 'Generate file ZIP absensi testing untuk 01 & 02 Agustus 2026';

    public function handle()
    {
        $nikList = [
            ['nik' => '14SR05', 'name' => 'PEDRO LAKSANA PUTRA'],
            ['nik' => '15GB01', 'name' => 'SUSIANTO'],
            ['nik' => '15GB02', 'name' => 'Viky Tridianto'],
            ['nik' => '15GB03', 'name' => 'AGUNG HARI WIBOWO'],
            ['nik' => '16RJ01', 'name' => 'SUGENG HARIYANTO'],
            ['nik' => '17GP03', 'name' => 'EMI SUGIARTI'],
            ['nik' => '18RT07', 'name' => 'MOHAMMAD YUSSRIL'],
        ];

        // Buat folder temp sementara
        $tempDir = storage_path('app/temp_zip_test');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // 1. Buat File Log Hari 1 (01-08-2026)
        $file1 = $tempDir . '/010826.csv';
        $handle1 = fopen($file1, 'w');
        fputcsv($handle1, ['nik', 'nama', 'tanggal', 'jam']);
        foreach ($nikList as $emp) {
            fputcsv($handle1, [$emp['nik'], $emp['name'], '01-08-2026', '07:45:00']);
            fputcsv($handle1, [$emp['nik'], $emp['name'], '01-08-2026', '16:30:00']);
        }
        fclose($handle1);

        // 2. Buat File Log Hari 2 (02-08-2026)
        $file2 = $tempDir . '/020826.csv';
        $handle2 = fopen($file2, 'w');
        fputcsv($handle2, ['nik', 'nama', 'tanggal', 'jam']);
        foreach ($nikList as $emp) {
            fputcsv($handle2, [$emp['nik'], $emp['name'], '02-08-2026', '07:50:00']);
            fputcsv($handle2, [$emp['nik'], $emp['name'], '02-08-2026', '17:00:00']);
        }
        fclose($handle2);

        // 3. Compress kedua file menjadi TEST_ABSENSI_AGUSTUS.zip di folder public/downloads/
        $zipPath = public_path('TEST_ABSENSI_AGUSTUS.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($file1, '010826.csv');
            $zip->addFile($file2, '020826.csv');
            $zip->close();

            // Hapus file csv temporary
            unlink($file1);
            unlink($file2);
            rmdir($tempDir);

            $this->info("BERHASIL! File ZIP testing berhasil dibuat di: " . $zipPath);
        } else {
            $this->error("Gagal membuat file ZIP.");
        }
    }
}