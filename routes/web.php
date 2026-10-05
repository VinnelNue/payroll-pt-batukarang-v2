<?php



use Illuminate\Support\Facades\Route;



use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\DashboardController;



use App\Http\Controllers\EmployeeController;

use App\Http\Controllers\EmployeeOuterIslandController;



use App\Http\Controllers\EmployeeContractController;



use App\Http\Controllers\PayrollController;

use App\Http\Controllers\PayrollOuterIslandController;



use App\Http\Controllers\ProfileController;

use App\Http\Controllers\UserController;



use App\Http\Controllers\ContractLocalController;

use App\Http\Controllers\ContractOuterIslandController;



use App\Http\Controllers\HolidayController;





/*

|--------------------------------------------------------------------------

| ROUTE GUEST (LOGIN & LOGOUT)

|--------------------------------------------------------------------------

*/



Route::get('/login', [

    LoginController::class,

    'showLoginForm'

])->name('login');



Route::post('/login', [

    LoginController::class,

    'login'

])->name('login.perform');



Route::post('/logout', [

    LoginController::class,

    'logout'

])->name('logout');





/*

|--------------------------------------------------------------------------

| ROUTE TERKUNCI (MIDDLEWARE AUTH)

|--------------------------------------------------------------------------

*/



Route::middleware(['auth'])->group(function () {





    /*

    |--------------------------------------------------------------------------

    | 1. DASHBOARD UTAMA

    |--------------------------------------------------------------------------

    */



    Route::get('/', [

        DashboardController::class,

        'index'

    ])->name('dashboard');





    /*

    |--------------------------------------------------------------------------

    | 2A. MODUL MASTER KARYAWAN LOKAL

    |--------------------------------------------------------------------------

    */



    Route::prefix('employees/local')

        ->name('employees.local.')

        ->group(function () {



            Route::get('export', [

                EmployeeController::class,

                'export'

            ])->name('export');



            Route::post('import', [

                EmployeeController::class,

                'import'

            ])->name('import');



            Route::get('download-template', [

                EmployeeController::class,

                'downloadTemplate'

            ])->name('download-template');

        });





    Route::resource(

        'employees/local',

        EmployeeController::class

    )->parameters([

        'local' => 'employee'

    ])->names(

        'employees.local'

    );





    /*

    |--------------------------------------------------------------------------

    | 2B. MODUL MASTER KARYAWAN LUAR PULAU

    |--------------------------------------------------------------------------

    */



    Route::prefix('employees/outer_island')

        ->name('employees.outer_island.')

        ->group(function () {



            Route::get('export', [

                EmployeeOuterIslandController::class,

                'export'

            ])->name('export');



            Route::post('import', [

                EmployeeOuterIslandController::class,

                'import'

            ])->name('import');



            Route::get('download-template', [

                EmployeeOuterIslandController::class,

                'downloadTemplate'

            ])->name('download-template');

        });





    Route::resource(

        'employees/outer_island',

        EmployeeOuterIslandController::class

    )->names(

        'employees.outer_island'

    );





    /*

    |--------------------------------------------------------------------------

    | 3. API REGION INDONESIA

    |--------------------------------------------------------------------------

    */



    Route::prefix('api')

        ->name('api.')

        ->group(function () {



            Route::get('cities', [

                EmployeeController::class,

                'getCities'

            ])->name('cities');



            Route::get('districts', [

                EmployeeController::class,

                'getDistricts'

            ])->name('districts');



            Route::get('villages', [

                EmployeeController::class,

                'getVillages'

            ])->name('villages');

        });





    /*

    |--------------------------------------------------------------------------

    | LOCAL PAYROLL CUTOFF DAY

    |--------------------------------------------------------------------------

    */



    Route::post(

        '/payrolls/local/cutoff-day',

        [

            PayrollController::class,

            'updateCutoffDay'

        ]

    )

        ->name('payrolls.local.cutoff.update');





    /*

    |--------------------------------------------------------------------------

    | 4. MODUL KONTRAK KERJA LOKAL

    |--------------------------------------------------------------------------

    |

    | Struktur:

    |

    | Employee

    |     ↓

    | Contract Master

    |     ↓

    | Contract History / Period

    |

    | Satu employee hanya mempunyai SATU Contract Master.

    |

    | Perpanjangan / perubahan kontrak membuat History baru.

    |

    | Tabel:

    |

    | employee_contracts

    | employee_contract_histories

    |

    |--------------------------------------------------------------------------

    */



    Route::prefix('contracts/local')

        ->name('contracts.local.')

        ->group(function () {





            /*

            |--------------------------------------------------------------------------

            | CONTRACT INDEX

            |--------------------------------------------------------------------------

            |

            | Menampilkan daftar employee dan contract.

            |

            */



            Route::get('/', [

                ContractLocalController::class,

                'index'

            ])->name('index');





            /*

            |--------------------------------------------------------------------------

            | EDIT / CURRENT CONTRACT

            |--------------------------------------------------------------------------

            |

            | Menampilkan:

            |

            | Contract Master

            | Current Contract History

            | History sebelumnya

            |

            */



            Route::get('{employee}/edit', [

                ContractLocalController::class,

                'edit'

            ])->name('edit');





            /*

            |--------------------------------------------------------------------------

            | UPDATE CURRENT CONTRACT

            |--------------------------------------------------------------------------

            |

            | Mengubah current contract history.

            |

            */



            Route::put('{employee}', [

                ContractLocalController::class,

                'update'

            ])->name('update');





            /*

            |--------------------------------------------------------------------------

            | CREATE CONTRACT PERIOD

            |--------------------------------------------------------------------------

            |

            | Membuka form untuk membuat history baru.

            |

            */



            Route::get('{employee}/period/create', [

                ContractLocalController::class,

                'createPeriod'

            ])->name('period.create');





            /*

            |--------------------------------------------------------------------------

            | STORE CONTRACT PERIOD

            |--------------------------------------------------------------------------

            |

            | Menyimpan history kontrak baru.

            |

            | Contoh:

            |

            | History 1

            |     ↓

            | History 2

            |     ↓

            | History 3

            |

            */



            Route::post('{employee}/period', [

                ContractLocalController::class,

                'storePeriod'

            ])->name('period.store');





            /*

            |--------------------------------------------------------------------------

            | SHOW CONTRACT HISTORY

            |--------------------------------------------------------------------------

            |

            | Melihat history kontrak lama.

            |

            */



            Route::get('{employee}/period/{history}', [

                ContractLocalController::class,

                'showHistory'

            ])->name('period.show');



        });





    /*

    |--------------------------------------------------------------------------

    | 4B. MODUL KONTRAK KERJA OUTER ISLAND

    |--------------------------------------------------------------------------

    |

    | Struktur:

    |

    | Employee

    |     ↓

    | Contract Master

    |     ↓

    | Contract History / Period

    |

    |--------------------------------------------------------------------------

    */



    Route::prefix('contracts/outer_island')

        ->name('contracts.outer_island.')

        ->group(function () {





            /*

            |--------------------------------------------------------------------------

            | CONTRACT INDEX

            |--------------------------------------------------------------------------

            */



            Route::get('/', [

                ContractOuterIslandController::class,

                'index'

            ])->name('index');





            /*

            |--------------------------------------------------------------------------

            | EDIT / CURRENT CONTRACT

            |--------------------------------------------------------------------------

            */



            Route::get('{employee}/edit', [

                ContractOuterIslandController::class,

                'edit'

            ])->name('edit');





            /*

            |--------------------------------------------------------------------------

            | UPDATE CURRENT CONTRACT

            |--------------------------------------------------------------------------

            */



            Route::put('{employee}', [

                ContractOuterIslandController::class,

                'update'

            ])->name('update');





            /*

            |--------------------------------------------------------------------------

            | CREATE CONTRACT PERIOD

            |--------------------------------------------------------------------------

            */



            Route::get('{employee}/period/create', [

                ContractOuterIslandController::class,

                'createPeriod'

            ])->name('period.create');





            /*

            |--------------------------------------------------------------------------

            | STORE CONTRACT PERIOD

            |--------------------------------------------------------------------------

            */



            Route::post('{employee}/period', [

                ContractOuterIslandController::class,

                'storePeriod'

            ])->name('period.store');





            /*

            |--------------------------------------------------------------------------

            | SHOW CONTRACT HISTORY

            |--------------------------------------------------------------------------

            */



            Route::get('{employee}/period/{history}', [

                ContractOuterIslandController::class,

                'showHistory'

            ])->name('period.show');



        });





    /*

    |--------------------------------------------------------------------------

    | 5. MODUL PAYROLL & TAX - LOCAL

    |--------------------------------------------------------------------------

    */



    Route::prefix('payrolls/local')

        ->name('payrolls.local.')

        ->group(function () {





            /*

            |--------------------------------------------------------------------------

            | PAYROLL INDEX / REKAP

            |--------------------------------------------------------------------------

            */



            Route::get('/', [

                PayrollController::class,

                'index'

            ])->name('index');





            /*

            |--------------------------------------------------------------------------

            | INPUT ABSENSI & VARIABEL PAYROLL

            |--------------------------------------------------------------------------

            */



            Route::get('create', [

                PayrollController::class,

                'create'

            ])->name('create');





            /*

            |--------------------------------------------------------------------------

            | SIMPAN PAYROLL

            |--------------------------------------------------------------------------

            */



            Route::post('store', [

                PayrollController::class,

                'store'

            ])->name('store');





            /*

            |--------------------------------------------------------------------------

            | IMPORT ABSENSI

            |--------------------------------------------------------------------------

            */



            Route::post('import', [

                PayrollController::class,

                'import'

            ])->name('import');





            /*

            |--------------------------------------------------------------------------

            | EXPORT BCA

            |--------------------------------------------------------------------------

            */



            Route::get('export-bca', [

                PayrollController::class,

                'exportBca'

            ])->name('export-bca');





            /*

            |--------------------------------------------------------------------------

            | LOCK & UNLOCK

            |--------------------------------------------------------------------------

            */



            Route::post('lock', [

                PayrollController::class,

                'lockCalculation'

            ])->name('lock');





            Route::post('request-unlock', [

                PayrollController::class,

                'requestUnlock'

            ])->name('requestUnlock');





            Route::post('unlock', [

                PayrollController::class,

                'unlockCalculation'

            ])->name('unlock');





            Route::post('reject-unlock', [

                PayrollController::class,

                'rejectUnlock'

            ])->name('rejectUnlock');





            /*

            |--------------------------------------------------------------------------

            | DOCUMENT OUTPUT

            |--------------------------------------------------------------------------

            */



            Route::get('{uuid}/print-pdf', [

                PayrollController::class,

                'printPdf'

            ])->name('print-pdf');





            Route::get('{uuid}/send-email', [

                PayrollController::class,

                'sendEmail'

            ])->name('send-email');



        });





    /*

    |--------------------------------------------------------------------------

    | 5B. MODUL PAYROLL & TAX - OUTER ISLAND

    |--------------------------------------------------------------------------

    */



    Route::prefix('payrolls/outer_island')

        ->name('payrolls.outer_island.')

        ->group(function () {





            /*

            |--------------------------------------------------------------------------

            | PAYROLL INDEX / REKAP

            |--------------------------------------------------------------------------

            */



            Route::get('/', [

                PayrollOuterIslandController::class,

                'index'

            ])->name('index');





            /*

            |--------------------------------------------------------------------------

            | INPUT ABSENSI & VARIABEL PAYROLL

            |--------------------------------------------------------------------------

            */



            Route::get('create', [

                PayrollOuterIslandController::class,

                'create'

            ])->name('create');





            /*

            |--------------------------------------------------------------------------

            | SIMPAN PAYROLL

            |--------------------------------------------------------------------------

            */



            Route::post('store', [

                PayrollOuterIslandController::class,

                'store'

            ])->name('store');





            /*

            |--------------------------------------------------------------------------

            | EXPORT BCA

            |--------------------------------------------------------------------------

            */



            Route::get('export-bca', [

                PayrollOuterIslandController::class,

                'exportBca'

            ])->name('export-bca');





            /*

            |--------------------------------------------------------------------------

            | LOCK & UNLOCK

            |--------------------------------------------------------------------------

            */



            Route::post('lock', [

                PayrollOuterIslandController::class,

                'lockCalculation'

            ])->name('lock');





            Route::post('request-unlock', [

                PayrollOuterIslandController::class,

                'requestUnlock'

            ])->name('requestUnlock');





            Route::post('unlock', [

                PayrollOuterIslandController::class,

                'unlockCalculation'

            ])->name('unlock');





            Route::post('reject-unlock', [

                PayrollOuterIslandController::class,

                'rejectUnlock'

            ])->name('rejectUnlock');





            /*

            |--------------------------------------------------------------------------

            | DOCUMENT OUTPUT

            |--------------------------------------------------------------------------

            */



            Route::get('{uuid}/print-pdf', [

                PayrollOuterIslandController::class,

                'printPdf'

            ])->name('print-pdf');





            Route::get('{uuid}/send-email', [

                PayrollOuterIslandController::class,

                'sendEmail'

            ])->name('send-email');





            /*

            |--------------------------------------------------------------------------

            | CUTOFF DAY

            |--------------------------------------------------------------------------

            */



            Route::post('cutoff-day', [

                PayrollOuterIslandController::class,

                'updateCutoffDay'

            ])->name('cutoff.update');





            /*

            |--------------------------------------------------------------------------

            | GENERATE ATTENDANCE

            |--------------------------------------------------------------------------

            */



            Route::post(

                'generate-attendance',

                [

                    PayrollOuterIslandController::class,

                    'generateAttendance'

                ]

            )->name('generate_attendance');



        });





    /*

    |--------------------------------------------------------------------------

    | 6. TAX & BPJS MASTER

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/tax-bpjs-master',

        [

            PayrollController::class,

            'taxBpjsMaster'

        ]

    )->name('tax-bpjs.index');





    Route::post(

        '/tax-bpjs-master/update-bpjs',

        [

            PayrollController::class,

            'updateBpjsSetting'

        ]

    )->name('tax-bpjs.update-bpjs');





    /*

    |--------------------------------------------------------------------------

    | 7. MASTER HARI LIBUR

    |--------------------------------------------------------------------------

    */



    Route::prefix('holidays')

        ->name('holidays.')

        ->group(function () {



            Route::get('/', [

                HolidayController::class,

                'index'

            ])->name('index');



            Route::get('/create', [

                HolidayController::class,

                'create'

            ])->name('create');



            Route::post('/', [

                HolidayController::class,

                'store'

            ])->name('store');



            Route::get('/{holiday}/edit', [

                HolidayController::class,

                'edit'

            ])->name('edit');



            Route::put('/{holiday}', [

                HolidayController::class,

                'update'

            ])->name('update');



            Route::delete('/{holiday}', [

                HolidayController::class,

                'destroy'

            ])->name('destroy');



            Route::patch('/{holiday}/toggle-status', [

                HolidayController::class,

                'toggleStatus'

            ])->name('toggleStatus');



        });





    /*

    |--------------------------------------------------------------------------

    | 8. PROFILE SETTINGS

    |--------------------------------------------------------------------------

    */



    Route::get('/profile', [

        ProfileController::class,

        'edit'

    ])->name('profile.edit');





    Route::put('/profile', [

        ProfileController::class,

        'update'

    ])->name('profile.update');





    /*

    |--------------------------------------------------------------------------

    | 9. USER MANAGEMENT

    |--------------------------------------------------------------------------

    */



    Route::resource(

        'users',

        UserController::class

    )->only([

        'index',

        'store',

        'destroy'

    ]);



});