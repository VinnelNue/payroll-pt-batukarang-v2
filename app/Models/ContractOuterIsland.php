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

    protected $guarded = [
        'id_contract_outer_island',
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

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ==========================================
    // EMPLOYEE
    // ==========================================

    /**
     * Contract Master → Employee
     */
    public function employee()
    {
        return $this->belongsTo(
            EmployeeOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }

    // ==========================================
    // CONTRACT HISTORY
    // ==========================================

    /**
     * Contract Master → seluruh riwayat kontrak.
     *
     * Contoh:
     *
     * Master #2
     * ├── History #1 PKWT 1
     * ├── History #2 PKWT 2
     * └── History #3 PKWT 3 ← current
     */
    public function histories()
    {
        return $this->hasMany(
            ContractHistoryOuterIsland::class,
            'contract_outer_island_id',
            'id_contract_outer_island'
        )->orderBy('start_date');
    }

    /**
     * Contract Master → History yang sedang
     * ditunjuk sebagai current.
     *
     * Current ditentukan oleh:
     * current_contract_history_id
     *
     * BUKAN berdasarkan latest ID atau latest date.
     */
    public function currentHistory()
    {
        return $this->belongsTo(
            ContractHistoryOuterIsland::class,
            'current_contract_history_id',
            'id_contract_history_outer_island'
        );
    }
}