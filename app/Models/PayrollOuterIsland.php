<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollOuterIsland extends Model
{
    use HasFactory;

    protected $table = 'payrolls_outer_island';

    protected $primaryKey = 'id_payroll_outer_island';

    protected $casts = [
        'daily_attendance' => 'array',

        'is_locked' =>
            'boolean',

        'unlock_requested' =>
            'boolean',

        'is_bpjs_override' =>
            'boolean',
    ];

    protected $fillable = [
        'employee_outer_island_id',

        'period_month',

        'cutoff_day',

        'daily_attendance',

        'work_days',

        'unpaid_leave',

        'gantungan_days',

        'overtime_hours',

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

    /*
    |--------------------------------------------------------------------------
    | Relasi ke Employee Outer Island
    |--------------------------------------------------------------------------
    */

    public function employee()
    {
        return $this->belongsTo(
            EmployeeOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }
}