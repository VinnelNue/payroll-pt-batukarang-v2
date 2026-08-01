<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $table = 'company_settings';
    protected $fillable = ['key', 'value', 'description'];

    // Helper untuk ambil setting bernilai angka
    public static function get($key, $default = 0)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}