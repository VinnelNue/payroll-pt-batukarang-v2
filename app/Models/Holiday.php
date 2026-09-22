<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'holiday_date',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];
}