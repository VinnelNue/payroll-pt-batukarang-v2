<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternityLeaveAccrual extends Model
{
    protected $table = 'maternity_leave_accruals';

    protected $fillable = [
        'maternity_case_id',
        'employee_id',
        'attendance_date',
        'contract_history_id',
        'payroll_id',
        'offset_payroll_id',
        'standard_work_days',
        'basic_salary',
        'allowance',
        'daily_rate',
        'amount',
        'settlement_id',
        'status',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'standard_work_days' => 'integer',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function maternityCase(): BelongsTo
    {
        return $this->belongsTo(MaternityLeaveCase::class, 'maternity_case_id', 'id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(MaternityLeaveSettlement::class, 'settlement_id', 'id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id', 'id_payroll');
    }

    public function offsetPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'offset_payroll_id', 'id_payroll');
    }

    public function contractHistory(): BelongsTo
    {
        return $this->belongsTo(ContractHistoryLocal::class, 'contract_history_id', 'id_contract_history');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }
}
