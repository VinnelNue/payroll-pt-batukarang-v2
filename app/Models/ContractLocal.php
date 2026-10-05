<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContractLocal extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'employee_contracts';

    protected $primaryKey = 'id_contract';

    public $incrementing = true;

    protected $keyType = 'int';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'uuid',
        'employee_id',
        'current_contract_history_id',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | BOOT
    |--------------------------------------------------------------------------
    |
    | Generate UUID otomatis saat Contract Master dibuat.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (self $contract) {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE
    |--------------------------------------------------------------------------
    |
    | Contract Master → Employee
    |
    */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'id_employee'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CONTRACT HISTORIES
    |--------------------------------------------------------------------------
    |
    | Contract Master → seluruh history/periode kontrak
    |
    */

    public function histories(): HasMany
    {
        return $this->hasMany(
            ContractHistoryLocal::class,
            'contract_id',
            'id_contract'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT HISTORY
    |--------------------------------------------------------------------------
    |
    | Contract Master → history yang sedang aktif/current
    |
    */

    public function currentHistory(): BelongsTo
    {
        return $this->belongsTo(
            ContractHistoryLocal::class,
            'current_contract_history_id',
            'id_contract_history'
        );
    }
}