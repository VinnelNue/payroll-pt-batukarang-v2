<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'status',
        'source',
    ];

    protected $casts = [
        'attendance_date' => 'date',
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