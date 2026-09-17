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
    protected $guarded = ['id_employee_outer_island'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // RELASI
    public function contracts()
    {
        return $this->hasMany(ContractOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }

    public function activeContract()
    {
        return $this->hasOne(ContractOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island')->where('is_active', true);
    }

    public function latestContract()
    {
        return $this->hasOne(ContractOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island')
            ->latestOfMany('id_contract_outer_island');
    }

    public function payrolls()
    {
        return $this->hasMany(PayrollOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }
}