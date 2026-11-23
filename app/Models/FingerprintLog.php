<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintLog extends Model
{
    protected $table = 'fingerprint_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'scan_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }

    public function contractHistory()
    {
        return $this->belongsTo(
            ContractHistoryLocal::class,
            'contract_history_id',
            'id_contract_history'
        );
    }
}
