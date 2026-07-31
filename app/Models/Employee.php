<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;

class Employee extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_employee';

    // Kolom yang dilindungi dari mass assignment
    protected $guarded = ['id_employee'];

    /**
     * Auto-generate UUID saat membuat data baru
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

    /**
     * Menggunakan UUID untuk Route Model Binding di URL (misal: /employees/{uuid}/edit)
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | RELASI WILAYAH LARAVOLT INDONESIA
    |--------------------------------------------------------------------------
    */

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_code', 'code');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_code', 'code');
    }

    public function district()
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    public function village()
    {
        return $this->belongsTo(Village::class, 'village_code', 'code');
    }

    /*
    |--------------------------------------------------------------------------
    | RELASI MODUL JABATAN & KONTRAK (MODUL 2)
    |--------------------------------------------------------------------------
    */

    public function jobPositions()
    {
        return $this->hasMany(EmployeeJobPosition::class, 'employee_id', 'id_employee');
    }

    public function activeJobPosition()
    {
        return $this->hasOne(EmployeeJobPosition::class, 'employee_id', 'id_employee')->where('is_active', true);
    }
    public function activeContract()
    {
        return $this->hasOne(EmployeeContract::class, 'employee_id', 'id_employee')->where('is_active', true);
    }
}