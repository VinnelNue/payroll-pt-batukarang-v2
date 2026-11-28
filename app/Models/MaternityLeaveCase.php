<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaternityLeaveCase extends Model
{
    use HasFactory;

    protected $table = 'maternity_leave_cases';

    protected $fillable = [
        'employee_id',
        'leave_start_date',
        'period1_days',
        'expected_delivery_date',
        'delivery_date',
        'certificate_path',
        'certificate_original_name',
        'document_uploaded_at',
        'status',
        'verified_at',
        'verified_by',
        'released_at',
        'rejection_reason',
        'created_by',
        'attendance_snapshots',
    ];

    protected $casts = [
        'leave_start_date' => 'date',
        'period1_days' => 'integer',
        'expected_delivery_date' => 'date',
        'delivery_date' => 'date',
        'document_uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'released_at' => 'datetime',
        'attendance_snapshots' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'maternity_case_id', 'id');
    }

    public function accruals()
    {
        return $this->hasMany(MaternityLeaveAccrual::class, 'maternity_case_id', 'id');
    }

    public function settlement()
    {
        return $this->hasOne(MaternityLeaveSettlement::class, 'maternity_case_id', 'id');
    }

    public function firstPeriodStartDate(): Carbon
    {
        return Carbon::parse($this->leave_start_date)->startOfDay();
    }

    /**
     * Tahap 1 memakai jumlah hari yang diinput HRD (maksimal 45 hari).
     * Jika persalinan aktual terjadi lebih awal, Tahap 1 berhenti pada
     * tanggal persalinan agar tidak tumpang tindih dengan Tahap 2.
     */
    public function firstPeriodEndDate(): Carbon
    {
        $start = $this->firstPeriodStartDate();
        $days = max(1, min(45, (int) ($this->period1_days ?: 45)));
        $plannedEnd = $start->copy()->addDays($days - 1);

        if ($this->delivery_date) {
            $actualDelivery = Carbon::parse($this->delivery_date)->startOfDay();
            if ($actualDelivery->gte($start) && $actualDelivery->lt($plannedEnd)) {
                return $actualDelivery;
            }
        }

        return $plannedEnd;
    }

    /** Tahap 2 hanya tersedia setelah tanggal persalinan aktual dicatat. */
    public function deliveryBasisDate(): ?Carbon
    {
        return $this->delivery_date
            ? Carbon::parse($this->delivery_date)->startOfDay()
            : null;
    }

    /** Tahap 2 dimulai sehari setelah tanggal persalinan aktual. */
    public function secondPeriodStartDate(): ?Carbon
    {
        $deliveryDate = $this->deliveryBasisDate();

        return $deliveryDate?->copy()->addDay()->startOfDay();
    }

    /** Tahap 2 berlangsung tepat 45 hari kalender. */
    public function secondPeriodEndDate(): ?Carbon
    {
        $start = $this->secondPeriodStartDate();

        return $start?->copy()->addDays(44);
    }

    public function leaveEndDate(): Carbon
    {
        return $this->secondPeriodEndDate() ?? $this->firstPeriodEndDate();
    }

    public function firstPeriodDays(): int
    {
        return (int) $this->firstPeriodStartDate()->diffInDays($this->firstPeriodEndDate()) + 1;
    }

    public function secondPeriodDays(): int
    {
        return $this->secondPeriodStartDate() ? 45 : 0;
    }

    /** @return array<int, array{start: Carbon, end: Carbon}> */
    public function schedulePeriods(): array
    {
        $periods = [[
            'start' => $this->firstPeriodStartDate(),
            'end' => $this->firstPeriodEndDate(),
        ]];

        $secondStart = $this->secondPeriodStartDate();
        $secondEnd = $this->secondPeriodEndDate();

        if ($secondStart && $secondEnd) {
            $periods[] = [
                'start' => $secondStart,
                'end' => $secondEnd,
            ];
        }

        return $periods;
    }

    public function containsDate(Carbon|string $date): bool
    {
        $date = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();

        foreach ($this->schedulePeriods() as $period) {
            if ($date->gte($period['start']) && $date->lte($period['end'])) {
                return true;
            }
        }

        return false;
    }

    public function intersectsPeriod(Carbon $start, Carbon $end): bool
    {
        foreach ($this->schedulePeriods() as $period) {
            if ($period['start']->lte($end) && $period['end']->gte($start)) {
                return true;
            }
        }

        return false;
    }
}
