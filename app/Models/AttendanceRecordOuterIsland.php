<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecordOuterIsland extends Model
{
    use HasFactory;

    protected $table = 'attendance_records_outer_island';

    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_outer_island_id',
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
            EmployeeOuterIsland::class,
            'employee_outer_island_id',
            'id_employee_outer_island'
        );
    }
}