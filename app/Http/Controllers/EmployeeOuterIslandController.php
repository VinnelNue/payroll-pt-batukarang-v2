<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland;
use App\Imports\EmployeeOuterIslandImport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class EmployeeOuterIslandController extends Controller
{
    // 1. Index: Fitur Search, Pengurutan & Relasi Kontrak
    public function index(Request $request)
    {
        $search = $request->get('search');

        $employees = EmployeeOuterIsland::with(['latestContract'])
            ->when($search, function ($query) use ($search) {
                $query->where('full_name_outer', 'LIKE', "%{$search}%")
                    ->orWhere('nik_ktp_outer', 'LIKE', "%{$search}%")
                    ->orWhere('no_kk_outer', 'LIKE', "%{$search}%")
                    ->orWhere('email_outer', 'LIKE', "%{$search}%")
                    ->orWhere('phone_number_outer', 'LIKE', "%{$search}%")
                    ->orWhereHas('latestContract', function ($q) use ($search) {
                        $q->where('job_title', 'LIKE', "%{$search}%")
                            ->orWhere('department', 'LIKE', "%{$search}%")
                            ->orWhere('placement_area', 'LIKE', "%{$search}%");
                    });
            })
            ->oldest('id_employee_outer_island')
            ->paginate(10)
            ->withQueryString();

        return view('employees.outer_island.index', compact('employees', 'search'));
    }

    public function create()
    {
        return view('employees.outer_island.create');
    }

    // 2. Store: Simpan Data Karyawan Luar Pulau & Kontrak Awal (Opsional)
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Validasi Data Karyawan
            'nik_ktp_outer'             => 'required|string|size:16|unique:employees_outer_island,nik_ktp_outer',
            'no_kk_outer'               => 'nullable|string|size:16',
            'full_name_outer'           => 'required|string|max:255',
            'gender_outer'              => 'required|in:L,P',
            'birth_place_outer'         => 'required|string|max:255',
            'birth_date_outer'          => 'required|date',
            'religion_outer'            => 'nullable|string|max:255',
            'marital_status_outer'      => 'required|in:single,married,divorced',
            'phone_number_outer'        => 'nullable|string|max:255',
            'email_outer'               => 'nullable|email|max:255',
            'address_ktp_outer'         => 'required|string',
            'address_domicile_outer'    => 'nullable|string',
            'npwp_number_outer'         => 'nullable|string|max:255',
            'bank_name_outer'           => 'nullable|string|max:255',
            'bank_account_number_outer' => 'nullable|string|max:255',
            'bank_account_holder_outer' => 'nullable|string|max:255',
            'ktp_path_outer'            => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5000',
            'is_active'                 => 'boolean',

            // Validasi Kontrak Outer Island (Jika diisi bersamaan saat pembuatan karyawan)
            'contract_number'           => 'nullable|string|max:255',
            'start_date'                => 'required_with:contract_number|nullable|date',
            'end_date'                  => 'required_with:contract_number|nullable|date|after_or_equal:start_date',
            'position'                  => 'nullable|string|max:255',
            'placement'                 => 'nullable|string|max:255',
            'contract_file'             => 'nullable|file|mimes:pdf|max:5000',
        ]);

        DB::beginTransaction();
        try {
            $validated['uuid'] = (string) Str::uuid();

            if ($request->hasFile('ktp_path_outer')) {
                $validated['ktp_path_outer'] = $request->file('ktp_path_outer')->store('employees_outer/ktp', 'public');
            }

            $employee = EmployeeOuterIsland::create($validated);

            // Simpan Kontrak jika nomor kontrak diisi
            if (!empty($request->contract_number)) {
                $contractPath = null;
                if ($request->hasFile('contract_file')) {
                    $contractPath = $request->file('contract_file')->store('contracts_outer', 'public');
                }

                $employee->contracts()->create([
                    'uuid'            => (string) Str::uuid(),
                    'contract_number' => $request->contract_number,
                    'start_date'      => $request->start_date,
                    'end_date'        => $request->end_date,
                    'position'        => $request->position,
                    'placement'       => $request->placement,
                    'contract_path'   => $contractPath,
                    'is_active'       => true,
                ]);
            }

            DB::commit();

            return redirect()->route('employees.outer_island.index')
                ->with('success', 'Data Karyawan Luar Pulau berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    // 3. Edit Form
    public function edit($outer_island)
    {
        $employee = $outer_island instanceof EmployeeOuterIsland
            ? $outer_island
            : EmployeeOuterIsland::where('uuid', $outer_island)
                ->orWhere('id_employee_outer_island', $outer_island)
                ->firstOrFail();

        $employee->load(['contracts', 'latestContract']);

        return view('employees.outer_island.edit', compact('employee'));
    }

    // 4. Update Data Karyawan Luar Pulau
    public function update(Request $request, $outer_island)
    {
        $employee = $outer_island instanceof EmployeeOuterIsland
            ? $outer_island
            : EmployeeOuterIsland::where('uuid', $outer_island)
                ->orWhere('id_employee_outer_island', $outer_island)
                ->firstOrFail();

        $validated = $request->validate([
            'nik_ktp_outer'             => 'required|string|size:16|unique:employees_outer_island,nik_ktp_outer,' . $employee->id_employee_outer_island . ',id_employee_outer_island',
            'no_kk_outer'               => 'nullable|string|size:16',
            'full_name_outer'           => 'required|string|max:255',
            'gender_outer'              => 'required|in:L,P',
            'birth_place_outer'         => 'required|string|max:255',
            'birth_date_outer'          => 'required|date',
            'religion_outer'            => 'nullable|string|max:255',
            'marital_status_outer'      => 'required|in:single,married,divorced',
            'phone_number_outer'        => 'nullable|string|max:255',
            'email_outer'               => 'nullable|email|max:255',
            'address_ktp_outer'         => 'required|string',
            'address_domicile_outer'    => 'nullable|string',
            'npwp_number_outer'         => 'nullable|string|max:255',
            'bank_name_outer'           => 'nullable|string|max:255',
            'bank_account_number_outer' => 'nullable|string|max:255',
            'bank_account_holder_outer' => 'nullable|string|max:255',
            'ktp_path_outer'            => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5000',
            'is_active'                 => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('ktp_path_outer')) {
                if ($employee->ktp_path_outer && Storage::disk('public')->exists($employee->ktp_path_outer)) {
                    Storage::disk('public')->delete($employee->ktp_path_outer);
                }
                $validated['ktp_path_outer'] = $request->file('ktp_path_outer')->store('employees_outer/ktp', 'public');
            }

            $employee->update($validated);

            DB::commit();

            return redirect()->route('employees.outer_island.index')
                ->with('success', 'Data Karyawan Luar Pulau berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    // 5. Destroy Data Karyawan Luar Pulau
    public function destroy($outer_island)
    {
        $employee = $outer_island instanceof EmployeeOuterIsland
            ? $outer_island
            : EmployeeOuterIsland::where('uuid', $outer_island)
                ->orWhere('id_employee_outer_island', $outer_island)
                ->firstOrFail();

        DB::beginTransaction();
        try {
            // Hapus file KTP
            if ($employee->ktp_path_outer && Storage::disk('public')->exists($employee->ktp_path_outer)) {
                Storage::disk('public')->delete($employee->ktp_path_outer);
            }

            // Hapus file berkas kontrak terkait jika ada
            foreach ($employee->contracts as $contract) {
                if ($contract->contract_path && Storage::disk('public')->exists($contract->contract_path)) {
                    Storage::disk('public')->delete($contract->contract_path);
                }
            }

            $employee->delete();

            DB::commit();

            return redirect()->route('employees.outer_island.index')
                ->with('success', 'Data Karyawan Luar Pulau berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    // 6. Import CSV/Excel
    public function import(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        $file = $request->file('file_excel');
        $extension = strtolower($file->getClientOriginalExtension());

        $cleanNumber = function ($value) {
            if (empty($value)) return null;
            $str = trim((string) $value);
            if (is_numeric($value) && str_contains(strtoupper($str), 'E')) {
                return sprintf('%.0f', (float)$value);
            }
            return $str !== '' ? $str : null;
        };

        $parseDate = function ($value) {
            if (empty($value)) return now()->format('Y-m-d');
            try {
                if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', trim($value))) {
                    return Carbon::createFromFormat('d/m/Y', str_replace('-', '/', trim($value)))->format('Y-m-d');
                }
                return date('Y-m-d', strtotime($value));
            } catch (\Exception $e) {
                return now()->format('Y-m-d');
            }
        };

        if ($extension === 'csv' || $extension === 'txt') {
            DB::beginTransaction();
            try {
                $filePath = $file->getRealPath();
                $firstLine = file_get_contents($filePath, false, null, 0, 1000);
                $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

                $handle = fopen($filePath, 'r');
                fgetcsv($handle, 2000, $delimiter); // Skip header

                $successCount = 0;
                while (($row = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
                    if (!array_filter($row)) continue;

                    $nikKtp = $cleanNumber($row[0] ?? '');
                    if (empty($nikKtp)) continue;

                    if (EmployeeOuterIsland::where('nik_ktp_outer', $nikKtp)->exists()) {
                        continue;
                    }

                    EmployeeOuterIsland::create([
                        'uuid'                      => (string) Str::uuid(),
                        'nik_ktp_outer'             => $nikKtp,
                        'no_kk_outer'               => $cleanNumber($row[1] ?? null),
                        'full_name_outer'           => $row[2] ?? '',
                        'gender_outer'              => strtoupper($row[3] ?? 'L'),
                        'birth_place_outer'         => $row[4] ?? '-',
                        'birth_date_outer'          => $parseDate($row[5] ?? null),
                        'religion_outer'            => $row[6] ?? null,
                        'marital_status_outer'      => strtolower($row[7] ?? 'single'),
                        'phone_number_outer'        => $cleanNumber($row[8] ?? null),
                        'email_outer'               => $row[9] ?? null,
                        'address_ktp_outer'         => $row[10] ?? '-',
                        'address_domicile_outer'    => $row[11] ?? null,
                        'npwp_number_outer'         => $cleanNumber($row[12] ?? null),
                        'bank_name_outer'           => $row[13] ?? null,
                        'bank_account_number_outer' => $cleanNumber($row[14] ?? null),
                        'bank_account_holder_outer' => $row[15] ?? null,
                        'is_active'                 => true,
                    ]);

                    $successCount++;
                }
                fclose($handle);
                DB::commit();

                return redirect()->route('employees.outer_island.index')
                    ->with('success', "Berhasil mengimpor {$successCount} data karyawan luar pulau!");
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Gagal memproses file CSV: ' . $e->getMessage());
            }
        }

        try {
            Excel::import(new EmployeeOuterIslandImport, $file);
            return redirect()->route('employees.outer_island.index')
                ->with('success', 'Data karyawan luar pulau berhasil diimpor!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengimpor file: ' . $e->getMessage());
        }
    }

    // 7. Export CSV (Termasuk Informasi Kontrak Terakhir)
    public function export()
    {
        $filename = 'Export_Karyawan_Luar_Pulau_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $employees = EmployeeOuterIsland::with('latestContract')
            ->latest('id_employee_outer_island')
            ->get();

        $columns = [
            'NIK KTP', 'No KK', 'Nama Lengkap', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir',
            'Agama', 'Status Pernikahan', 'No HP', 'Email', 'Alamat KTP', 'Alamat Domisili',
            'NPWP', 'Nama Bank', 'No Rekening', 'Pemilik Rekening', 'No Kontrak Terakhir', 'Jabatan', 'Penempatan', 'Status Aktif'
        ];

        $callback = function () use ($employees, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($employees as $emp) {
                fputcsv($file, [
                    $emp->nik_ktp_outer,
                    $emp->no_kk_outer,
                    $emp->full_name_outer,
                    $emp->gender_outer,
                    $emp->birth_place_outer,
                    $emp->birth_date_outer?->format('Y-m-d'),
                    $emp->religion_outer,
                    $emp->marital_status_outer,
                    $emp->phone_number_outer,
                    $emp->email_outer,
                    $emp->address_ktp_outer,
                    $emp->address_domicile_outer,
                    $emp->npwp_number_outer,
                    $emp->bank_name_outer,
                    $emp->bank_account_number_outer,
                    $emp->bank_account_holder_outer,
                    $emp->latestContract?->contract_number ?? '-',
                    $emp->latestContract?->position ?? '-',
                    $emp->latestContract?->placement ?? '-',
                    $emp->is_active ? 'Aktif' : 'Non-Aktif',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // 8. Download Template CSV
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_Import_Karyawan_Luar_Pulau.csv"',
        ];

        $columns = [
            'nik_ktp_outer', 'no_kk_outer', 'full_name_outer', 'gender_outer', 'birth_place_outer', 'birth_date_outer',
            'religion_outer', 'marital_status_outer', 'phone_number_outer', 'email_outer', 'address_ktp_outer', 'address_domicile_outer',
            'npwp_number_outer', 'bank_name_outer', 'bank_account_number_outer', 'bank_account_holder_outer'
        ];

        $sampleData = [
            '6371123456780001', '6371123456780002', 'Andi Pratama', 'L', 'Banjarmasin', '1996-05-20',
            'Islam', 'single', '081298765432', 'andi@example.com', 'Jl. Ahmad Yani No. 12', 'Jl. Ahmad Yani No. 12',
            '98.765.432.1-012.000', 'BCA', '9876543210', 'ANDI PRATAMA'
        ];

        $callback = function () use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);
            fputcsv($file, $sampleData);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}