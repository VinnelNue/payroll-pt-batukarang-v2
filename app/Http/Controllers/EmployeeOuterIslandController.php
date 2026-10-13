<?php



namespace App\Http\Controllers;



use App\Imports\EmployeeOuterIslandImport;

use App\Models\EmployeeOuterIsland;

use Carbon\Carbon;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use Maatwebsite\Excel\Facades\Excel;



class EmployeeOuterIslandController extends Controller

{

    /*

    |--------------------------------------------------------------------------

    | 1. INDEX

    |--------------------------------------------------------------------------

    |

    | Struktur:

    |

    | Employee

    |   └── Contract Master

    |         └── Current History

    |

    */



    public function index(Request $request)

    {

        $search = $request->get('search');



        $employees = EmployeeOuterIsland::with([

            'contractMaster.currentHistory',

        ])

            ->when($search, function ($query) use ($search) {

                $query->where(function ($q) use ($search) {

                    $q->where(

                        'full_name_outer',

                        'LIKE',

                        "%{$search}%"

                    )

                        ->orWhere(

                            'nik_ktp_outer',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhere(

                            'no_kk_outer',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhere(

                            'email_outer',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhere(

                            'phone_number_outer',

                            'LIKE',

                            "%{$search}%"

                        )

                        ->orWhereHas(

                            'contractMaster.currentHistory',

                            function ($contract) use ($search) {

                                $contract

                                    ->where(

                                        'job_title',

                                        'LIKE',

                                        "%{$search}%"

                                    )

                                    ->orWhere(

                                        'department',

                                        'LIKE',

                                        "%{$search}%"

                                    )

                                    ->orWhere(

                                        'placement_area',

                                        'LIKE',

                                        "%{$search}%"

                                    );

                            }

                        );

                });

            })

            ->oldest('id_employee_outer_island')

            ->paginate(10)

            ->withQueryString();



        return view(

            'employees.outer_island.index',

            compact(

                'employees',

                'search'

            )

        );

    }



    /*

    |--------------------------------------------------------------------------

    | 2. CREATE

    |--------------------------------------------------------------------------

    */



    public function create()

    {

        return view(

            'employees.outer_island.create'

        );

    }



    /*

    |--------------------------------------------------------------------------

    | 3. STORE EMPLOYEE

    |--------------------------------------------------------------------------

    |

    | Employee dibuat terlebih dahulu.

    |

    | Setelah employee berhasil dibuat:

    |

    | Employee

    |     ↓

    | Contract Master

    |

    | Contract History BELUM dibuat.

    |

    | History dibuat dari Contract Controller.

    |

    */



    public function store(Request $request)

    {

        $validated = $request->validate([

            /*

            |--------------------------------------------------------------------------

            | DATA EMPLOYEE

            |--------------------------------------------------------------------------

            */



            'nik_ktp_outer' => [

                'required',

                'string',

                'size:16',

                'unique:employees_outer_island,nik_ktp_outer',

            ],



            'no_kk_outer' => [

                'nullable',

                'string',

                'size:16',

            ],



            'full_name_outer' => [

                'required',

                'string',

                'max:255',

            ],



            'gender_outer' => [

                'required',

                'in:L,P',

            ],



            'birth_place_outer' => [

                'required',

                'string',

                'max:255',

            ],



            'birth_date_outer' => [

                'required',

                'date',

            ],



            'religion_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'marital_status_outer' => [

                'required',

                'in:single,married,divorced',

            ],



            'phone_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'email_outer' => [

                'nullable',

                'email',

                'max:255',

            ],



            'address_ktp_outer' => [

                'required',

                'string',

            ],



            'address_domicile_outer' => [

                'nullable',

                'string',

            ],



            'npwp_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_name_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_account_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_account_holder_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'ktp_path_outer' => [

                'nullable',

                'file',

                'mimes:jpeg,png,jpg,webp,pdf',

                'max:5000',

            ],



            'is_active' => [

                'nullable',

                'boolean',

            ],

        ]);



        DB::beginTransaction();



        try {

            /*

            |--------------------------------------------------------------------------

            | UUID EMPLOYEE

            |--------------------------------------------------------------------------

            */



            $validated['uuid'] = (string) Str::uuid();



            /*

            |--------------------------------------------------------------------------

            | DEFAULT ACTIVE

            |--------------------------------------------------------------------------

            */



            // Form master tidak mengirim is_active saat ini; karyawan baru aktif secara default.
            $validated['is_active'] = $request->exists('is_active')
                ? $request->boolean('is_active')
                : true;



            /*

            |--------------------------------------------------------------------------

            | FILE KTP

            |--------------------------------------------------------------------------

            */



            if ($request->hasFile('ktp_path_outer')) {

                $validated['ktp_path_outer'] =

                    $request

                        ->file('ktp_path_outer')

                        ->store(

                            'employees_outer/ktp',

                            'public'

                        );

            }



            /*

            |--------------------------------------------------------------------------

            | CREATE EMPLOYEE

            |--------------------------------------------------------------------------

            */



            $employee = EmployeeOuterIsland::create(

                $validated

            );



            /*

            |--------------------------------------------------------------------------

            | CREATE CONTRACT MASTER

            |--------------------------------------------------------------------------

            |

            | Satu employee hanya mempunyai satu master.

            |

            | History BELUM dibuat.

            |

            */



            $employee->contractMaster()->create([

                'uuid' => (string) Str::uuid(),

                'is_active' => true,

            ]);



            DB::commit();



            return redirect()

                ->route(

                    'employees.outer_island.index'

                )

                ->with(

                    'success',

                    'Data Karyawan Luar Pulau berhasil ditambahkan!'

                );

        } catch (\Throwable $e) {

            DB::rollBack();



            return redirect()

                ->back()

                ->with(

                    'error',

                    'Gagal menyimpan data: ' .

                    $e->getMessage()

                )

                ->withInput();

        }

    }



    /*

    |--------------------------------------------------------------------------

    | 4. EDIT

    |--------------------------------------------------------------------------

    |

    | Load:

    |

    | Employee

    |   └── Contract Master

    |         ├── Current History

    |         └── Histories

    |

    */



    public function edit($outer_island)

    {

        $employee = $this->resolveEmployee(

            $outer_island

        );



        $employee->load([

            'contractMaster.currentHistory',

            'contractMaster.histories',

        ]);



        return view(

            'employees.outer_island.edit',

            compact('employee')

        );

    }



    /*

    |--------------------------------------------------------------------------

    | 5. UPDATE EMPLOYEE

    |--------------------------------------------------------------------------

    |

    | Hanya data employee.

    |

    | Perubahan kontrak dilakukan melalui

    | ContractOuterIslandController.

    |

    */



    public function update(

        Request $request,

        $outer_island

    ) {

        $employee = $this->resolveEmployee(

            $outer_island

        );



        $validated = $request->validate([

            'nik_ktp_outer' => [

                'required',

                'string',

                'size:16',

                'unique:employees_outer_island,nik_ktp_outer,' .

                $employee->id_employee_outer_island .

                ',id_employee_outer_island',

            ],



            'no_kk_outer' => [

                'nullable',

                'string',

                'size:16',

            ],



            'full_name_outer' => [

                'required',

                'string',

                'max:255',

            ],



            'gender_outer' => [

                'required',

                'in:L,P',

            ],



            'birth_place_outer' => [

                'required',

                'string',

                'max:255',

            ],



            'birth_date_outer' => [

                'required',

                'date',

            ],



            'religion_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'marital_status_outer' => [

                'required',

                'in:single,married,divorced',

            ],



            'phone_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'email_outer' => [

                'nullable',

                'email',

                'max:255',

            ],



            'address_ktp_outer' => [

                'required',

                'string',

            ],



            'address_domicile_outer' => [

                'nullable',

                'string',

            ],



            'npwp_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_name_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_account_number_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'bank_account_holder_outer' => [

                'nullable',

                'string',

                'max:255',

            ],



            'ktp_path_outer' => [

                'nullable',

                'file',

                'mimes:jpeg,png,jpg,webp,pdf',

                'max:5000',

            ],



            'is_active' => [

                'nullable',

                'boolean',

            ],

        ]);



        DB::beginTransaction();



        try {

            /*

            |--------------------------------------------------------------------------

            | DEFAULT ACTIVE

            |--------------------------------------------------------------------------

            */



            // Saat edit, pertahankan status lama jika form tidak mengirim is_active.
            $validated['is_active'] = $request->exists('is_active')
                ? $request->boolean('is_active')
                : (bool) $employee->is_active;



            /*

            |--------------------------------------------------------------------------

            | GANTI KTP JIKA ADA FILE BARU

            |--------------------------------------------------------------------------

            */



            if ($request->hasFile('ktp_path_outer')) {

                if (

                    $employee->ktp_path_outer &&

                    Storage::disk('public')->exists(

                        $employee->ktp_path_outer

                    )

                ) {

                    Storage::disk('public')->delete(

                        $employee->ktp_path_outer

                    );

                }



                $validated['ktp_path_outer'] =

                    $request

                        ->file('ktp_path_outer')

                        ->store(

                            'employees_outer/ktp',

                            'public'

                        );

            }



            /*

            |--------------------------------------------------------------------------

            | UPDATE EMPLOYEE

            |--------------------------------------------------------------------------

            */



            $employee->update(

                $validated

            );



            /*

            |--------------------------------------------------------------------------

            | PASTIKAN CONTRACT MASTER TERSEDIA

            |--------------------------------------------------------------------------

            |

            | Untuk employee lama yang mungkin belum mempunyai

            | Contract Master.

            |

            */



            $this->ensureContractMaster(

                $employee

            );



            DB::commit();



            return redirect()

                ->route(

                    'employees.outer_island.index'

                )

                ->with(

                    'success',

                    'Data Karyawan Luar Pulau berhasil diperbarui!'

                );

        } catch (\Throwable $e) {

            DB::rollBack();



            return redirect()

                ->back()

                ->with(

                    'error',

                    'Gagal memperbarui data: ' .

                    $e->getMessage()

                )

                ->withInput();

        }

    }



    /*

    |--------------------------------------------------------------------------

    | 6. DESTROY

    |--------------------------------------------------------------------------

    |

    | Employee

    |     ↓ cascade

    | Contract Master

    |     ↓ cascade

    | Contract History

    |

    */



    public function destroy($outer_island)

    {

        $employee = $this->resolveEmployee(

            $outer_island

        );



        DB::beginTransaction();



        try {

            /*

            |--------------------------------------------------------------------------

            | HAPUS FILE KTP

            |--------------------------------------------------------------------------

            */



            if (

                $employee->ktp_path_outer &&

                Storage::disk('public')->exists(

                    $employee->ktp_path_outer

                )

            ) {

                Storage::disk('public')->delete(

                    $employee->ktp_path_outer

                );

            }



            /*

            |--------------------------------------------------------------------------

            | HAPUS EMPLOYEE

            |--------------------------------------------------------------------------

            |

            | Contract Master dan History mengikuti

            | foreign key cascade jika migration sudah

            | menggunakan cascadeOnDelete().

            |

            */



            $employee->delete();



            DB::commit();



            return redirect()

                ->route(

                    'employees.outer_island.index'

                )

                ->with(

                    'success',

                    'Data Karyawan Luar Pulau berhasil dihapus!'

                );

        } catch (\Throwable $e) {

            DB::rollBack();



            return redirect()

                ->back()

                ->with(

                    'error',

                    'Gagal menghapus data: ' .

                    $e->getMessage()

                );

        }

    }



    /*

    |--------------------------------------------------------------------------

    | 7. IMPORT CSV / EXCEL

    |--------------------------------------------------------------------------

    |

    | Import hanya data Employee.

    |

    | Setiap employee WAJIB mempunyai satu

    | Contract Master.

    |

    | Contract History tidak dibuat di sini.

    |

    */



    public function import(Request $request)

    {

        $request->validate([

            'file_excel' => [

                'required',

                'file',

                'mimes:xlsx,xls,ods,csv,txt',

                'max:5120',

            ],

        ]);



        $file = $request->file(

            'file_excel'

        );



        $extension = strtolower(

            $file->getClientOriginalExtension()

        );



        /*

        |--------------------------------------------------------------------------

        | HELPER ANGKA

        |--------------------------------------------------------------------------

        */



        $cleanNumber = function ($value) {

            if (

                $value === null ||

                $value === ''

            ) {

                return null;

            }



            $str = trim(

                (string) $value

            );



            /*

            |--------------------------------------------------------------------------

            | Tangani scientific notation dari Excel/CSV

            |--------------------------------------------------------------------------

            */



            if (

                is_numeric($value) &&

                str_contains(

                    strtoupper($str),

                    'E'

                )

            ) {

                return sprintf(

                    '%.0f',

                    (float) $value

                );

            }



            return $str !== ''

                ? $str

                : null;

        };



        /*

        |--------------------------------------------------------------------------

        | HELPER TANGGAL

        |--------------------------------------------------------------------------

        */



        $parseDate = function ($value) {

            if (

                $value === null ||

                trim((string) $value) === ''

            ) {

                return now()->format('Y-m-d');

            }



            try {

                /*

                |--------------------------------------------------------------------------

                | Excel serial number

                |--------------------------------------------------------------------------

                */



                if (

                    is_numeric($value) &&

                    (float) $value > 20000

                ) {

                    return Carbon::create(

                        1899,

                        12,

                        30

                    )

                        ->addDays(

                            (int) $value

                        )

                        ->format('Y-m-d');

                }



                $value = trim(

                    (string) $value

                );



                /*

                |--------------------------------------------------------------------------

                | Format d/m/Y atau d-m-Y

                |--------------------------------------------------------------------------

                */



                if (

                    preg_match(

                        '/^**\d**{1,2}[\/\-]**\d**{1,2}[\/\-]**\d**{4}$/',

                        $value

                    )

                ) {

                    $normalized = str_replace(

                        '-',

                        '/',

                        $value

                    );



                    return Carbon::createFromFormat(

                        'd/m/Y',

                        $normalized

                    )->format('Y-m-d');

                }



                /*

                |--------------------------------------------------------------------------

                | Format Y-m-d atau format Carbon lainnya

                |--------------------------------------------------------------------------

                */



                return Carbon::parse(

                    $value

                )->format('Y-m-d');

            } catch (\Throwable $e) {

                return now()->format(

                    'Y-m-d'

                );

            }

        };



        /*

        |--------------------------------------------------------------------------

        | 7A. CSV / TXT HRIS

        |--------------------------------------------------------------------------

        |

        | CSV hasil ekspor HRIS memiliki urutan kolom berbeda dari template CSV
        | karyawan lama. Gunakan parser HRIS yang sama dengan import Excel agar
        | header tidak pernah disimpan sebagai data, dan agar semua periode
        | Freelance/PKWT ikut diproses.

        |

        */

        if ($extension === 'csv' || $extension === 'txt') {
            DB::beginTransaction();

            try {
                $outerIslandImport = new EmployeeOuterIslandImport();
                $outerIslandImport->importCsvFile($file->getRealPath());

                // Pastikan employee existing yang belum memiliki master tetap memiliki satu master.
                $employeesWithoutMaster = EmployeeOuterIsland::whereDoesntHave('contractMaster')->get();
                foreach ($employeesWithoutMaster as $employee) {
                    $this->ensureContractMaster($employee);
                }

                DB::commit();
                $report = $outerIslandImport->report;
                $summary = sprintf(
                    'Import HRIS CSV selesai: %d employee baru, %d employee diperbarui, %d employee dipulihkan via NIK internal, %d master kontrak baru, %d history dibuat, %d history disinkronkan, %d duplikat history dibersihkan, %d periode lama yang kosong di HRIS dihapus, %d baris dilewati.',
                    $report['employees_created'] ?? 0,
                    $report['employees_updated'] ?? 0,
                    $report['employees_relinked_by_internal_nik'] ?? 0,
                    $report['masters_created'] ?? 0,
                    $report['histories_created'] ?? 0,
                    $report['histories_updated_from_hris'] ?? 0,
                    $report['histories_duplicates_removed'] ?? 0,
                    $report['stale_period_histories_removed'] ?? 0,
                    $report['rows_skipped'] ?? 0
                );

                $warnings = array_slice($report['errors'] ?? [], 0, 5);
                if ($warnings !== []) {
                    $summary .= ' Catatan: ' . implode(' | ', $warnings);
                    if (count($report['errors']) > count($warnings)) {
                        $summary .= sprintf(
                            ' | dan %d catatan tambahan dicatat di log Laravel.',
                            count($report['errors']) - count($warnings)
                        );
                    }
                }

                return redirect()
                    ->route('employees.outer_island.index')
                    ->with('success', $summary);
            } catch (\Throwable $e) {
                DB::rollBack();

                return redirect()
                    ->back()
                    ->with('error', 'Gagal memproses CSV HRIS: ' . $e->getMessage())
                    ->withInput();
            }
        }



                /*

        |--------------------------------------------------------------------------

        | 7B. EXCEL

        |--------------------------------------------------------------------------

        |

        | EmployeeOuterIslandImport menangani data employee.

        |

        | Setelah import selesai, kita memastikan semua employee

        | yang belum mempunyai Contract Master mendapatkan tepat

        | satu Contract Master.

        |

        */



        DB::beginTransaction();



        try {

            $outerIslandImport = new EmployeeOuterIslandImport();

            Excel::import(

                $outerIslandImport,

                $file

            );



            /*

            |--------------------------------------------------------------------------

            | Pastikan SEMUA employee mempunyai Contract Master

            |--------------------------------------------------------------------------

            |

            | Tidak membuat Contract History.

            |

            */



            $employeesWithoutMaster =

                EmployeeOuterIsland::whereDoesntHave(

                    'contractMaster'

                )->get();



            foreach (

                $employeesWithoutMaster as $employee

            ) {

                $this->ensureContractMaster(

                    $employee

                );

            }



            DB::commit();

            $report = $outerIslandImport->report;
            $summary = sprintf(
                'Import selesai: %d employee baru, %d employee diperbarui, %d employee dipulihkan via NIK internal, %d master kontrak baru, %d history dibuat, %d history disinkronkan dari HRIS, %d duplikat history dibersihkan, %d periode lama yang kosong di HRIS dihapus, %d baris dilewati.',
                $report['employees_created'] ?? 0,
                $report['employees_updated'] ?? 0,
                $report['employees_relinked_by_internal_nik'] ?? 0,
                $report['masters_created'] ?? 0,
                $report['histories_created'] ?? 0,
                $report['histories_updated_from_hris'] ?? 0,
                $report['histories_duplicates_removed'] ?? 0,
                $report['stale_period_histories_removed'] ?? 0,
                $report['rows_skipped'] ?? 0
            );

            $warnings = array_slice($report['errors'] ?? [], 0, 4);
            if ($warnings !== []) {
                $summary .= ' Catatan: ' . implode(' | ', $warnings);
                if (count($report['errors']) > count($warnings)) {
                    $summary .= sprintf(' | dan %d catatan lainnya (detail juga dicatat di log Laravel).', count($report['errors']) - count($warnings));
                }
            }

            return redirect()
                ->route('employees.outer_island.index')
                ->with('success', $summary);

        } catch (\Throwable $e) {

            DB::rollBack();



            return redirect()

                ->back()

                ->with(

                    'error',

                    'Terjadi kesalahan saat mengimpor file: ' .

                    $e->getMessage()

                )

                ->withInput();

        }

    }



    /*

    |--------------------------------------------------------------------------

    | 8. EXPORT CSV

    |--------------------------------------------------------------------------

    |

    | Informasi kontrak diambil dari:

    |

    | Contract Master

    |       ↓

    | Current History

    |

    */



    public function export()

    {

        $filename =

            'Export_Karyawan_Luar_Pulau\_' .

            date('Ymd_His') .

            '.csv';



        $headers = [

            'Content-Type' =>

                'text/csv; charset=UTF-8',



            'Content-Disposition' =>

                "attachment; filename=\"{$filename}\"",

        ];



        $employees =

            EmployeeOuterIsland::with([

                'contractMaster.currentHistory',

            ])

                ->latest(

                    'id_employee_outer_island'

                )

                ->get();



        $columns = [

            'NIK KTP',

            'No KK',

            'Nama Lengkap',

            'Jenis Kelamin',

            'Tempat Lahir',

            'Tanggal Lahir',

            'Agama',

            'Status Pernikahan',

            'No HP',

            'Email',

            'Alamat KTP',

            'Alamat Domisili',

            'NPWP',

            'Nama Bank',

            'No Rekening',

            'Pemilik Rekening',

            'Jabatan',

            'Department',

            'Penempatan',

            'Kategori',

            'Level',

            'Gaji Pokok',

            'Jenis Kontrak',

            'PKWT Ke',

            'Tanggal Mulai',

            'Tanggal Berakhir',

            'Status Aktif',

        ];



        $callback = function () use (

            $employees,

            $columns

        ) {

            $file = fopen(

                'php\://output',

                'w'

            );



            /*

            |--------------------------------------------------------------------------

            | UTF-8 BOM

            |--------------------------------------------------------------------------

            */



            fprintf(

                $file,

                chr(0xEF) .

                chr(0xBB) .

                chr(0xBF)

            );



            fputcsv(

                $file,

                $columns

            );



            foreach ($employees as $emp) {

                /*

                |--------------------------------------------------------------------------

                | CURRENT HISTORY

                |--------------------------------------------------------------------------

                */



                $history =

                    $emp

                        ->contractMaster

                        ?->currentHistory;



                fputcsv(

                    $file,

                    [

                        $emp->nik_ktp_outer,

                        $emp->no_kk_outer,

                        $emp->full_name_outer,

                        $emp->gender_outer,

                        $emp->birth_place_outer,



                        $emp->birth_date_outer?->format(

                            'Y-m-d'

                        ),



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



                        /*

                        |--------------------------------------------------------------------------

                        | CONTRACT CURRENT HISTORY

                        |--------------------------------------------------------------------------

                        */



                        $history?->job_title ?? '-',

                        $history?->department ?? '-',

                        $history?->placement_area ?? '-',

                        $history?->category ?? '-',

                        $history?->level ?? '-',

                        $history?->basic_salary ?? 0,

                        $history?->employment_type ?? '-',

                        $history?->pkwt_sequence ?? '-',



                        $history?->start_date?->format(

                            'Y-m-d'

                        ),



                        $history?->end_date?->format(

                            'Y-m-d'

                        ),



                        $emp->is_active

                            ? 'Aktif'

                            : 'Non-Aktif',

                    ]

                );

            }



            fclose($file);

        };



        return response()->stream(

            $callback,

            200,

            $headers

        );

    }



    /*

    |--------------------------------------------------------------------------

    | 9. DOWNLOAD TEMPLATE CSV

    |--------------------------------------------------------------------------

    */



    public function downloadTemplate()

    {

        $headers = [

            'Content-Type' =>

                'text/csv; charset=UTF-8',



            'Content-Disposition' =>

                'attachment; filename="Template_Import_Karyawan_Luar_Pulau.csv"',

        ];



        $columns = [

            'nik_ktp_outer',

            'no_kk_outer',

            'full_name_outer',

            'gender_outer',

            'birth_place_outer',

            'birth_date_outer',

            'religion_outer',

            'marital_status_outer',

            'phone_number_outer',

            'email_outer',

            'address_ktp_outer',

            'address_domicile_outer',

            'npwp_number_outer',

            'bank_name_outer',

            'bank_account_number_outer',

            'bank_account_holder_outer',

        ];



        $sampleData = [

            '6371123456780001',

            '6371123456780002',

            'Andi Pratama',

            'L',

            'Banjarmasin',

            '1996-05-20',

            'Islam',

            'single',

            '081298765432',

            'andi\@example.com',

            'Jl. Ahmad Yani No. 12',

            'Jl. Ahmad Yani No. 12',

            '98.765.432.1-012.000',

            'BCA',

            '9876543210',

            'ANDI PRATAMA',

        ];



        $callback = function () use (

            $columns,

            $sampleData

        ) {

            $file = fopen(

                'php\://output',

                'w'

            );



            /*

            |--------------------------------------------------------------------------

            | UTF-8 BOM

            |--------------------------------------------------------------------------

            */



            fprintf(

                $file,

                chr(0xEF) .

                chr(0xBB) .

                chr(0xBF)

            );



            fputcsv(

                $file,

                $columns

            );



            fputcsv(

                $file,

                $sampleData

            );



            fclose($file);

        };



        return response()->stream(

            $callback,

            200,

            $headers

        );

    }



    /*

    |--------------------------------------------------------------------------

    | PRIVATE HELPER

    |--------------------------------------------------------------------------

    */



    /*\*

     * Resolve employee berdasarkan UUID atau ID.

     */

    private function resolveEmployee(

        $outer_island

    ): EmployeeOuterIsland {

        if (

            $outer_island instanceof EmployeeOuterIsland

        ) {

            return $outer_island;

        }



        return EmployeeOuterIsland::where(

            'uuid',

            $outer_island

        )

            ->orWhere(

                'id_employee_outer_island',

                $outer_island

            )

            ->firstOrFail();

    }



    /*\*

     * Pastikan satu employee hanya mempunyai

     * satu Contract Master.

     *

     * Tidak membuat Contract History.

     */

    private function ensureContractMaster(

        EmployeeOuterIsland $employee

    ) {

        $contract =

            $employee->contractMaster;



        if (!$contract) {

            $contract =

                $employee

                    ->contractMaster()

                    ->create([

                        'uuid' =>

                            (string) Str::uuid(),



                        'is_active' => true,

                    ]);

        }



        return $contract;

    }

}
