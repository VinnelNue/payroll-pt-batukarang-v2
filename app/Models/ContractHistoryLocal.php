<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContractHistoryLocal extends Model
{
    protected $table = 'employee_contract_histories';

    protected $primaryKey = 'id_contract_history';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'uuid',
        'contract_id',
        'nik_fingerprint',
        'fingerprint_pin',
        'job_title',
        'department',
        'placement_area',
        'category',
        'level',
        'basic_salary',
        'allowance',
        'is_bpjstk_active',
        'is_bpjs_health_active',
        'ptkp_status',
        'ter_category',
        'use_manual_bpjs',
        'manual_bpjs_tk_employee',
        'manual_bpjs_ks_employee',
        'manual_bpjs_company',
        'employment_type',
        'pkwt_sequence',
        'start_date',
        'end_date',
        'exit_date',
        'exit_reason',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'is_bpjstk_active' => 'boolean',
        'is_bpjs_health_active' => 'boolean',
        'use_manual_bpjs' => 'boolean',
        'manual_bpjs_tk_employee' => 'decimal:2',
        'manual_bpjs_ks_employee' => 'decimal:2',
        'manual_bpjs_company' => 'decimal:2',
        'pkwt_sequence' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'exit_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $history): void {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(
            ContractLocal::class,
            'contract_id',
            'id_contract'
        );
    }
}
