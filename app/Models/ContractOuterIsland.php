<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractOuterIsland extends Model
{
    use HasFactory;

    protected $table = 'employee_contracts_outer_island';
    protected $primaryKey = 'id_contract_outer_island';
    protected $guarded = ['id_contract_outer_island'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'start_date'              => 'date',
        'end_date'                => 'date',
        'exit_date'               => 'date',
        'basic_salary'            => 'decimal:2',
        'allowance'               => 'decimal:2',
        'manual_bpjs_tk_employee' => 'decimal:2',
        'manual_bpjs_ks_employee' => 'decimal:2',
        'manual_bpjs_company'     => 'decimal:2',
        'is_bpjstk_active'        => 'boolean',
        'is_bpjs_health_active'   => 'boolean',
        'use_manual_bpjs'         => 'boolean',
        'is_active'               => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeOuterIsland::class, 'employee_outer_island_id', 'id_employee_outer_island');
    }
}

