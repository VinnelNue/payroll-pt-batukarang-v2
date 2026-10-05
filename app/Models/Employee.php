<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'employees';

    protected $primaryKey = 'id_employee';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    |
    | id_employee tidak boleh diisi melalui mass assignment.
    |
    */

    protected $guarded = [
        'id_employee',
    ];

    /*
    |--------------------------------------------------------------------------
    | BOOT
    |--------------------------------------------------------------------------
    |
    | Generate UUID otomatis saat Employee dibuat.
    |
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

        });
    }

    /*
    |--------------------------------------------------------------------------
    | ROUTE MODEL BINDING
    |--------------------------------------------------------------------------
    |
    | URL menggunakan UUID.
    |
    | Contoh:
    |
    | /employees/{uuid}/edit
    |
    */

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | RELASI WILAYAH
    |--------------------------------------------------------------------------
    |
    | Relasi wilayah tetap dapat digunakan oleh bagian lain
    | dari aplikasi.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | RELASI MODUL JABATAN
    |--------------------------------------------------------------------------
    */

    public function jobPositions()
    {
        return $this->hasMany(
            EmployeeJobPosition::class,
            'employee_id',
            'id_employee'
        );
    }

    public function activeJobPosition()
    {
        return $this->hasOne(
            EmployeeJobPosition::class,
            'employee_id',
            'id_employee'
        )->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT MASTER
    |--------------------------------------------------------------------------
    |
    | Employee → Contract Master
    |
    | Struktur:
    |
    | employees
    |      ↓
    | employee_contracts
    |
    */

    public function contract()
    {
        return $this->hasOne(
            ContractLocal::class,
            'employee_id',
            'id_employee'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BACKWARD COMPATIBILITY
    |--------------------------------------------------------------------------
    |
    | Dipertahankan sementara agar kode lama yang masih memanggil:
    |
    | $employee->activeContract
    |
    | tidak langsung error.
    |
    | Sekarang activeContract sebenarnya mengambil:
    |
    | employee_contracts
    |
    | melalui ContractLocal.
    |
    */

    public function activeContract()
    {
        return $this->contract();
    }

    /*
    |--------------------------------------------------------------------------
    | PAYROLL
    |--------------------------------------------------------------------------
    */

    public function payrolls()
    {
        return $this->hasMany(
            Payroll::class,
            'employee_id',
            'id_employee'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->hasOne(
            User::class,
            'employee_id',
            'id_employee'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function attendanceRecords()
    {
        return $this->hasMany(
            AttendanceRecord::class,
            'employee_id',
            'id_employee'
        );
    }
}