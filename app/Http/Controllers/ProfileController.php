<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        // 1. Ambil data user yang sedang login beserta relasi karyawannya
        $user = Auth::user();
        
        // Eager load relasi jika user terhubung dengan karyawan
        if ($user->employee_id) {
            $user->load('employee.activeContract', 'employee.city', 'employee.province');
        }

        // 2. WAJIB PASSING $user KE VIEW MENGGUNAKAN compact('user')
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'avatar'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'phone_number'    => 'nullable|string|max:20',
            'address_domicile'=> 'nullable|string',
            'bank_name'       => 'nullable|string|max:50',
            'bank_account'    => 'nullable|string|max:50',
            'current_password'=> 'nullable|required_with:new_password',
            'new_password'    => 'nullable|min:6|confirmed',
        ]);

        // 1. Upload Foto Profil
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('users/avatars', 'public');
            $user->save();
        }

        // 2. Update Password Akun jika diisi
        if ($request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return redirect()->back()->with('error', 'Password lama tidak sesuai!');
            }
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);
        }

        // 3. Update Data Kontak & Bank Personal di Tabel Employee (jika terhubung)
        if ($user->employee) {
            $user->employee->update([
                'phone_number'        => $request->phone_number,
                'address_domicile'    => $request->address_domicile,
                'bank_name'           => $request->bank_name,
                'bank_account_number' => $request->bank_account,
            ]);
        }

        return redirect()->back()->with('success', 'Profil dan informasi akun berhasil diperbarui!');
    }
}