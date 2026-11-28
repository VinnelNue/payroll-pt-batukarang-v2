<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaternityLeaveSettlement extends Model
{
    protected $table = 'maternity_leave_settlements';

    protected $fillable = [
        'maternity_case_id',
        'employee_id',
        'status',
        'gross_amount',
        'pph21_amount',
        'net_amount',
        'tax_period',
        'taxable_gross_basis',
        'ter_rate',
        'tax_calculated_at',
        'planned_payment_date',
        'paid_by',
        'payment_date',
        'transfer_reference',
        'proof_path',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'pph21_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'taxable_gross_basis' => 'decimal:2',
        'ter_rate' => 'decimal:4',
        'tax_calculated_at' => 'datetime',
        'planned_payment_date' => 'date',
        'payment_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function maternityCase(): BelongsTo
    {
        return $this->belongsTo(MaternityLeaveCase::class, 'maternity_case_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(MaternityLeaveAccrual::class, 'settlement_id', 'id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by', 'id');
    }
}
