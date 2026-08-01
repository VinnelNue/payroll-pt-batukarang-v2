<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_id',
        'avatar_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id_employee');
    }

    // Helper untuk mengambil URL Foto Profil (Prioritas: Avatar User -> Foto KTP -> Default Inisial)
    public function getAvatarUrlAttribute()
        {
            if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
                return asset('storage/' . $this->avatar_path);
            }

            // Return null jika belum upload foto profil (agar di-fallback ke inisial nama)
            return null;
        }
}