<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_import_template_csv()
    {
        $response = $this->get(route('employees.download-template'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_user_can_import_employees_from_valid_csv_file()
    {
        $header = "nik_ktp,nama_lengkap,panggilan,jenis_kelamin,tempat_lahir,tanggal_lahir,agama,status_pernikahan,no_hp,email,alamat_ktp,alamat_domisili,kode_provinsi,kode_kota,kode_kecamatan,kode_kelurahan,npwp,nama_bank,no_rekening,pemilik_rekening\n";
        $row1   = "3578123456780001,Budi Santoso,Budi,L,Surabaya,1995-08-17,Islam,single,081234567890,budi@batukarang.com,Jl. Merdeka No. 45,Jl. Merdeka No. 45,35,3578,357801,3578011001,12.345.678.9-012.000,BCA,1234567890,BUDI SANTOSO\n";
        $row2   = "3578123456780002,Siti Aminah,Siti,P,Malang,1998-12-01,Islam,married,089876543210,siti@batukarang.com,Jl. Mawar No. 12,Jl. Mawar No. 12,35,3579,357901,3579011001,,Mandiri,0987654321,SITI AMINAH\n";

        $content = $header . $row1 . $row2;
        $file = UploadedFile::fake()->createWithContent('import_karyawan_test.csv', $content);

        $response = $this->post(route('employees.import'), [
            'file_excel' => $file,
        ]);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'nik_ktp'   => '3578123456780001',
            'full_name' => 'Budi Santoso',
        ]);

        $this->assertDatabaseHas('employees', [
            'nik_ktp'   => '3578123456780002',
            'full_name' => 'Siti Aminah',
        ]);
    }

    public function test_import_fails_if_file_extension_is_invalid()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->post(route('employees.import'), [
            'file_excel' => $file,
        ]);

        $response->assertSessionHasErrors(['file_excel']);
    }

    public function test_import_skips_duplicate_nik_ktp()
    {
        Employee::create([
            'nik_ktp'        => '3578123456780001',
            'full_name'      => 'Karyawan Lama',
            'gender'         => 'L',
            'birth_place'    => 'Surabaya',
            'birth_date'     => '1990-01-01',
            'marital_status' => 'single',
            'address_ktp'    => 'Alamat Lama',
            'is_active'      => true,
        ]);

        $header = "nik_ktp,nama_lengkap,panggilan,jenis_kelamin,tempat_lahir,tanggal_lahir,agama,status_pernikahan,no_hp,email,alamat_ktp,alamat_domisili,kode_provinsi,kode_kota,kode_kecamatan,kode_kelurahan,npwp,nama_bank,no_rekening,pemilik_rekening\n";
        $row    = "3578123456780001,Nama Baru Duplikat,Budi,L,Surabaya,1995-08-17,Islam,single,081234567890,budi@batukarang.com,Jl. Merdeka No. 45,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('import_duplicate.csv', $header . $row);

        $this->post(route('employees.import'), [
            'file_excel' => $file,
        ]);

        $this->assertDatabaseHas('employees', [
            'nik_ktp'   => '3578123456780001',
            'full_name' => 'Karyawan Lama',
        ]);

        $this->assertDatabaseMissing('employees', [
            'full_name' => 'Nama Baru Duplikat',
        ]);
    }
}