<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeOuterIsland extends Model
{
    use HasFactory;

    protected $table = 'employees_outer_island';

    protected $primaryKey = 'id_employee_outer_island';

    protected $guarded = [
        'id_employee_outer_island',
    ];

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
     * Route Model Binding menggunakan UUID.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // ==========================================
    // CONTRACT MASTER
    // ==========================================

    /**
     * Satu Employee hanya memiliki SATU Contract Master.
     *
     * Struktur:
     *
     * Employee
     *     ↓
     * Contract Master
     *     ↓
     * Contract Histories
     */
    public function contractMaster()
    {
        return $this->hasOne(
            ContractOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }

    // ==========================================
    // ATTENDANCE OUTER ISLAND
    // ==========================================

    public function attendanceRecords()
    {
        return $this->hasMany(
            AttendanceRecordOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }

    // ==========================================
    // PAYROLL OUTER ISLAND
    // ==========================================

    public function payrolls()
    {
        return $this->hasMany(
            PayrollOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }

    public function latestPayroll()
    {
        return $this->hasOne(
            PayrollOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        )->latestOfMany('period_month');
    }

    // ==========================================
    // USER
    // ==========================================

    public function user()
    {
        return $this->hasOne(
            User::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }
}