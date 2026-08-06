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
        'is_locked'        => 'boolean',
        'unlock_requested' => 'boolean',
    ];

    protected $fillable = [
        'employee_id',
        'period_month',
        'daily_attendance', // <-- MASUKKAN INI AGAR TERSIMPAN
        'work_days',
        'unpaid_leave',
        'overtime_hours',
        'basic_salary',
        'allowance',
        'overtime_pay',
        'maternity_leave_pay',
        'incentive',
        'cash_advance',
        'other_deductions',
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
    ];

    // Relasi ke Employee
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }
}