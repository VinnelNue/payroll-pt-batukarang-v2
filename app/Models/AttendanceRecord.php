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

    protected static function booted(): void
    {
        static::updating(function (AttendanceRecord $record): void {
            // A scheduled maternity day remains CM even if a later fingerprint
            // or payroll-calendar import tries to replace it with H/manual status.
            if (
                $record->getOriginal('source') === 'maternity'
                && $record->source !== 'maternity'
            ) {
                $record->status = 'CM';
                $record->source = 'maternity';
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'id_employee'
        );
    }
}
