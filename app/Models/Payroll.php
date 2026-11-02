<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';
    protected $primaryKey = 'id_payroll';

    protected $casts = [
        'daily_attendance' => 'array',
        'sunday_overtime_details' => 'array',
        'is_locked' => 'boolean',
        'unlock_requested' => 'boolean',
        'is_bpjs_override' => 'boolean',
        'paid_days' => 'float',
        'unpaid_days' => 'float',
        'standard_work_days' => 'integer',
    ];

    protected $fillable = [
        'employee_id',
        'period_month',
        'cutoff_day',
        'standard_work_days',
        'daily_attendance',
        'work_days',
        'paid_days',
        'unpaid_days',
        'unpaid_leave',
        'gantungan_days',
        'overtime_hours',
        'weekday_overtime_hours',
        'sunday_overtime_details',
        'basic_salary',
        'allowance',
        'overtime_pay',
        'maternity_leave_pay',
        'incentive',
        'cash_advance',
        'other_deductions',
        'gantungan_deduction',
        'previous_gantungan_deduction',
        'bpjs_tk_deduction',
        'bpjs_ks_deduction',
        'pph21_deduction',
        'gross_salary',
        'net_salary',
        'status',
        'is_locked',
        'locked_at',
        'locked_by',
        'unlock_requested',
        'unlock_reason',
        'requested_by',
        'is_bpjs_override',
    ];

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'id_employee'
        );
    }
}
