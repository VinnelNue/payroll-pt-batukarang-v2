<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Middleware pengecekan khusus Manager Keuangan
    private function authorizeManagerKeuangan()
    {
        if (Auth::user()->role !== 'manager_keuangan') {
            abort(403, 'Akses Ditolak! Hanya Manager Keuangan yang berhak mengelola akun pengguna.');
        }
    }

    public function index()
    {
        $this->authorizeManagerKeuangan();

        // SEMBUNYIKAN SUPER ADMIN (BAIK BERDASARKAN ROLE MAUPUN EMAIL UTAMA)
        $users = User::with('employee')
            ->where('role', '!=', 'super_admin')
            ->where('email', '!=', 'admin@batukarang.com') // <--- KUNCI EMAIL SUPER ADMIN
            ->latest()
            ->paginate(10);

        $employees = Employee::whereDoesntHave('user')->get();

        return view('users.index', compact('users', 'employees'));
    }

    public function store(Request $request)
    {
        $this->authorizeManagerKeuangan();

        $request->validate([
            'employee_id' => 'required|exists:employees,id_employee|unique:users,employee_id',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6',
            'role'        => 'required|in:manager_keuangan,hrd,karyawan', // Super Admin tidak bisa dibuat lagi lewat UI
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        User::create([
            'name'        => $employee->full_name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'employee_id' => $employee->id_employee,
            'role'        => $request->role,
        ]);

        return redirect()->back()->with('success', "Akun login untuk {$employee->full_name} berhasil dibuat!");
    }

    public function destroy(User $user)
    {
        $this->authorizeManagerKeuangan();

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri!');
        }

        if ($user->role === 'super_admin') {
            return redirect()->back()->with('error', 'Akun Super Admin tidak dapat dihapus!');
        }

        $user->delete();
        return redirect()->back()->with('success', 'Akun pengguna berhasil dihapus!');
    }
}