<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    /**
     * Role yang diperbolehkan mengelola Master Hari Libur.
     */
    private const ALLOWED_ROLES = [
        'manager_keuangan',
        'super_admin',
    ];

    /**
     * Memastikan user memiliki hak akses.
     */
    private function authorizeFinance(): void
    {
        abort_unless(
            auth()->check()
            && in_array(auth()->user()->role, self::ALLOWED_ROLES, true),
            403
        );
    }

    /**
     * Menampilkan daftar hari libur.
     */
    public function index()
    {
        $this->authorizeFinance();

        $holidays = Holiday::query()
            ->orderBy('holiday_date', 'desc')
            ->get();

        return view('holidays.index', compact('holidays'));
    }

    /**
     * Form tambah hari libur.
     */
    public function create()
    {
        $this->authorizeFinance();

        return view('holidays.create');
    }

    /**
     * Menyimpan hari libur baru.
     */
    public function store(Request $request)
    {
        $this->authorizeFinance();

        $validated = $request->validate([
            'holiday_date' => [
                'required',
                'date',
                'unique:holidays,holiday_date',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                Rule::in([
                    'national',
                    'collective_leave',
                    'company',
                    'other',
                ]),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ], [
            'holiday_date.required' => 'Tanggal hari libur wajib diisi.',
            'holiday_date.date' => 'Tanggal hari libur tidak valid.',
            'holiday_date.unique' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.',

            'name.required' => 'Nama hari libur wajib diisi.',
            'name.max' => 'Nama hari libur maksimal 150 karakter.',

            'type.required' => 'Jenis hari libur wajib dipilih.',
            'type.in' => 'Jenis hari libur tidak valid.',
        ]);

        Holiday::create([
            'holiday_date' => $validated['holiday_date'],
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('holidays.index')
            ->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Form edit hari libur.
     */
    public function edit(Holiday $holiday)
    {
        $this->authorizeFinance();

        return view('holidays.edit', compact('holiday'));
    }

    /**
     * Memperbarui hari libur.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $this->authorizeFinance();

        $validated = $request->validate([
            'holiday_date' => [
                'required',
                'date',
                Rule::unique('holidays', 'holiday_date')
                    ->ignore($holiday->id),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                Rule::in([
                    'national',
                    'collective_leave',
                    'company',
                    'other',
                ]),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ], [
            'holiday_date.required' => 'Tanggal hari libur wajib diisi.',
            'holiday_date.date' => 'Tanggal hari libur tidak valid.',
            'holiday_date.unique' => 'Tanggal tersebut sudah digunakan oleh hari libur lain.',

            'name.required' => 'Nama hari libur wajib diisi.',
            'name.max' => 'Nama hari libur maksimal 150 karakter.',

            'type.required' => 'Jenis hari libur wajib dipilih.',
            'type.in' => 'Jenis hari libur tidak valid.',
        ]);

        $holiday->update([
            'holiday_date' => $validated['holiday_date'],
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('holidays.index')
            ->with('success', 'Hari libur berhasil diperbarui.');
    }

    /**
     * Menghapus hari libur.
     */
    public function destroy(Holiday $holiday)
    {
        $this->authorizeFinance();

        $year = $holiday->holiday_date->format('Y');

        $holiday->delete();

        return redirect()
            ->route('holidays.index', [
                'year' => $year,
            ])
            ->with('success', 'Hari libur berhasil dihapus.');
    }

    /**
     * Mengubah status aktif/nonaktif.
     */
    public function toggleStatus(Holiday $holiday)
    {
        $this->authorizeFinance();

        $holiday->update([
            'is_active' => ! $holiday->is_active,
        ]);

        return redirect()
            ->route('holidays.index')
            ->with(
                'success',
                $holiday->is_active
                    ? 'Hari libur berhasil diaktifkan.'
                    : 'Hari libur berhasil dinonaktifkan.'
            );
    }
}
