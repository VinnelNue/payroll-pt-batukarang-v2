<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\PayrollOuterIsland;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;

class EmployeeOuterIsland extends Model
{
    use HasFactory;

    // Nama tabel khusus luar pulau
    protected $table = 'employees_outer_island';

    protected $primaryKey = 'id_employee_outer_island';

    // Kolom yang dilindungi dari mass assignment
    protected $guarded = ['id_employee_outer_island'];

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
     * Menggunakan UUID untuk Route Model Binding di URL
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
    | RELASI MODUL JABATAN, KONTRAK & PAYROLL (OUTER ISLAND)
    |--------------------------------------------------------------------------
    */

    public function jobPositions()
    {
        return $this->hasMany(EmployeeJobPositionOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }

    public function activeJobPosition()
    {
        return $this->hasOne(EmployeeJobPositionOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island')->where('is_active', true);
    }

    public function activeContract()
    {
        return $this->hasOne(EmployeeContractOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island')->where('is_active', true);
    }

    // Relasi ke Payroll Outer Island
    public function payrolls()
    {
        return $this->hasMany(PayrollOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }
}