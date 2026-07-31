<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Imports\EmployeeImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with(['province', 'city'])->latest('id_employee')->paginate(10);
        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        $provinces = Province::pluck('name', 'code');
        return view('employees.create', compact('provinces'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        $file = $request->file('file_excel');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv' || $extension === 'txt') {
            try {
                $handle = fopen($file->getRealPath(), 'r');
                $header = fgetcsv($handle, 2000, ',');

                $successCount = 0;
                while (($row = fgetcsv($handle, 2000, ',')) !== FALSE) {
                    if (!array_filter($row)) continue;

                    $nikKtp = trim($row[0] ?? '');
                    if (empty($nikKtp)) continue;

                    if (Employee::where('nik_ktp', $nikKtp)->exists()) {
                        continue;
                    }

                    Employee::create([
                        'uuid'                => (string) Str::uuid(),
                        'nik_ktp'             => $nikKtp,
                        'full_name'           => $row[1] ?? '',
                        'nickname'            => $row[2] ?? null,
                        'gender'              => strtoupper($row[3] ?? 'L'),
                        'birth_place'         => $row[4] ?? '-',
                        'birth_date'          => !empty($row[5]) ? date('Y-m-d', strtotime($row[5])) : now()->format('Y-m-d'),
                        'religion'            => $row[6] ?? null,
                        'marital_status'      => strtolower($row[7] ?? 'single'),
                        'phone_number'        => $row[8] ?? null,
                        'email'               => $row[9] ?? null,
                        'address_ktp'         => $row[10] ?? '-',
                        'address_domicile'    => $row[11] ?? null,
                        'province_code'       => $row[12] ?? null,
                        'city_code'           => $row[13] ?? null,
                        'district_code'       => $row[14] ?? null,
                        'village_code'        => $row[15] ?? null,
                        'npwp_number'         => $row[16] ?? null,
                        'bank_name'           => $row[17] ?? null,
                        'bank_account_number' => $row[18] ?? null,
                        'bank_account_holder' => $row[19] ?? null,
                        'is_active'           => true,
                    ]);

                    $successCount++;
                }
                fclose($handle);

                return redirect()->route('employees.index')->with('success', "Berhasil mengimpor $successCount data karyawan via CSV!");
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Gagal memproses file CSV: ' . $e->getMessage());
            }
        }

        try {
            Excel::import(new EmployeeImport, $file);
            return redirect()->route('employees.index')->with('success', 'Data Master Karyawan berhasil diimpor via Excel!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMsg = 'Gagal impor! NIK KTP pada baris ' . $failures[0]->row() . ' sudah terdaftar atau format data salah.';
            return redirect()->back()->with('error', $errorMsg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat membaca file Excel: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Import_Karyawan_PT_Batu_Karang.csv"',
        ];

        $columns = [
            'nik_ktp', 'nama_lengkap', 'panggilan', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'agama', 'status_pernikahan', 'no_hp', 'email', 'alamat_ktp', 'alamat_domisili',
            'kode_provinsi', 'kode_kota', 'kode_kecamatan', 'kode_kelurahan',
            'npwp', 'nama_bank', 'no_rekening', 'pemilik_rekening'
        ];

        $sampleData = [
            '3578123456780001', 'Budi Santoso', 'Budi', 'L', 'Surabaya', '1995-08-17',
            'Islam', 'single', '081234567890', 'budi@batukarang.com', 'Jl. Merdeka No. 45', 'Jl. Merdeka No. 45',
            '35', '3578', '357801', '3578011001',
            '12.345.678.9-012.000', 'BCA', '1234567890', 'BUDI SANTOSO'
        ];

        $callback = function () use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, $sampleData);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik_ktp'             => 'required|digits:16|unique:employees,nik_ktp',
            'full_name'           => 'required|string|max:255',
            'nickname'            => 'nullable|string|max:100',
            'gender'              => 'required|in:L,P',
            'birth_place'         => 'required|string|max:100',
            'birth_date'          => 'required|date',
            'religion'            => 'nullable|string',
            'marital_status'      => 'required|in:single,married,divorced',
            'phone_number'        => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address_ktp'         => 'required|string',
            'address_domicile'    => 'nullable|string',
            'province_code'       => 'nullable|string',
            'city_code'           => 'nullable|string',
            'district_code'       => 'nullable|string',
            'village_code'        => 'nullable|string',
            'npwp_number'         => 'nullable|string|max:30',
            'bank_name'           => 'nullable|string|max:50',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_holder' => 'nullable|string|max:255',
            'ktp_file'            => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        // Auto-generate UUID jika belum di-set oleh Eloquent observer
        $validated['uuid'] = (string) Str::uuid();

        // Simpan File KTP jika diunggah
        if ($request->hasFile('ktp_file')) {
            $validated['ktp_path'] = $request->file('ktp_file')->store('employees/ktp', 'public');
        }

        // Unset input 'ktp_file' karena nama kolom di database adalah 'ktp_path'
        unset($validated['ktp_file']);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Data Master Karyawan berhasil ditambahkan!');
    }

    public function edit(Employee $employee)
    {
        $provinces = Province::pluck('name', 'code');
        $cities = $employee->province_code ? City::where('province_code', $employee->province_code)->pluck('name', 'code') : [];
        $districts = $employee->city_code ? District::where('city_code', $employee->city_code)->pluck('name', 'code') : [];
        $villages = $employee->district_code ? Village::where('district_code', $employee->district_code)->pluck('name', 'code') : [];

        return view('employees.edit', compact('employee', 'provinces', 'cities', 'districts', 'villages'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'nik_ktp'             => 'required|digits:16|unique:employees,nik_ktp,' . $employee->id_employee . ',id_employee',
            'full_name'           => 'required|string|max:255',
            'nickname'            => 'nullable|string|max:100',
            'gender'              => 'required|in:L,P',
            'birth_place'         => 'required|string|max:100',
            'birth_date'          => 'required|date',
            'religion'            => 'nullable|string',
            'marital_status'      => 'required|in:single,married,divorced',
            'phone_number'        => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address_ktp'         => 'required|string',
            'address_domicile'    => 'nullable|string',
            'province_code'       => 'nullable|string',
            'city_code'           => 'nullable|string',
            'district_code'       => 'nullable|string',
            'village_code'        => 'nullable|string',
            'npwp_number'         => 'nullable|string|max:30',
            'bank_name'           => 'nullable|string|max:50',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_holder' => 'nullable|string|max:255',
            'ktp_file'            => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5000',
        ]);

        if ($request->hasFile('ktp_file')) {
            if ($employee->ktp_path && Storage::disk('public')->exists($employee->ktp_path)) {
                Storage::disk('public')->delete($employee->ktp_path);
            }
            $validated['ktp_path'] = $request->file('ktp_file')->store('employees/ktp', 'public');
        }

        unset($validated['ktp_file']);

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Data Master Karyawan berhasil diperbarui!');
    }

    public function destroy(Employee $employee)
    {
        if ($employee->ktp_path && Storage::disk('public')->exists($employee->ktp_path)) {
            Storage::disk('public')->delete($employee->ktp_path);
        }

        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Data Master Karyawan berhasil dihapus!');
    }

    public function getCities(Request $request)
    {
        $cities = City::where('province_code', $request->province_code)->pluck('name', 'code');
        return response()->json($cities);
    }

    public function getDistricts(Request $request)
    {
        $districts = District::where('city_code', $request->city_code)->pluck('name', 'code');
        return response()->json($districts);
    }

    public function getVillages(Request $request)
    {
        $villages = Village::where('district_code', $request->district_code)->pluck('name', 'code');
        return response()->json($villages);
    }
}