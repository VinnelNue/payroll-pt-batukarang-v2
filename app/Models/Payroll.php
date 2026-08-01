<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';
    protected $primaryKey = 'id_payroll';

    protected $fillable = [
        'employee_id',
        'period_month',
        'work_days',
        'unpaid_leave',
        'overtime_hours',
        'basic_salary',
        'allowance',
        'overtime_pay',
        'maternity_leave_pay', // <-- TAMBAHKAN INI
        'incentive',
        'cash_advance',
        'other_deductions',
        'bpjs_tk_deduction',
        'bpjs_ks_deduction',
        'pph21_deduction',
        'gross_salary',
        'net_salary',
        'status',
    ];

    // Relasi ke Employee (BENAR)
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }
}