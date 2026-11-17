<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MaternityLeaveCase;
use App\Services\MaternityLeaveScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaternityLeaveController extends Controller
{
    private const MANAGEMENT_ROLES = [
        'hrd',
        'head_hrd',
        'kepala_hrd',
        'super_admin',
    ];

    private function authorizeManagement(): void
    {
        abort_unless(
            in_array((string) (Auth::user()->role ?? ''), self::MANAGEMENT_ROLES, true),
            403
        );
    }

    public function index()
    {
        $this->authorizeManagement();

        $cases = MaternityLeaveCase::query()
            ->with([
                'employee:id_employee,full_name,address_ktp',
                'payrolls:id_payroll,maternity_case_id,maternity_hold_amount,maternity_release_amount,period_month',
            ])
            ->orderByDesc('leave_start_date')
            ->orderByDesc('id')
            ->paginate(20);

        // CM hanya dapat didaftarkan untuk karyawan perempuan yang aktif
        // dan memiliki master kontrak aktif.
        $employees = Employee::query()
            ->where('gender', 'P')
            ->where('is_active', true)
            ->whereHas('contract', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('full_name')
            ->get(['id_employee', 'full_name', 'address_ktp']);

        return view('payrolls.local.maternity.index', compact('cases', 'employees'));
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id_employee'],
            'leave_start_date' => ['required', 'date'],
            'period1_days' => ['required', 'integer', 'min:1', 'max:45'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:leave_start_date'],
        ]);

        $syncResult = DB::transaction(function () use ($validated) {
            // Lock the employee row so two concurrent requests cannot create
            // duplicate open CM cases for the same employee.
            $employee = Employee::query()
                ->where('id_employee', $validated['employee_id'])
                ->where('gender', 'P')
                ->where('is_active', true)
                ->whereHas('contract', function ($query) {
                    $query->where('is_active', true);
                })
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                throw ValidationException::withMessages([
                    'employee_id' => 'CM hanya dapat didaftarkan untuk karyawan perempuan yang aktif dan memiliki kontrak aktif.',
                ]);
            }

            // A rejected case is still open: its previous CM value may remain
            // held. HRD must re-submit the document on that case, not create
            // another case and leave the first hold orphaned.
            $openCaseExists = MaternityLeaveCase::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            if ($openCaseExists) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Karyawan masih memiliki kasus CM yang belum selesai atau saldo yang belum dilepas. Gunakan kasus CM yang sudah ada untuk melanjutkan verifikasi.',
                ]);
            }

            $case = MaternityLeaveCase::create([
                'employee_id' => $validated['employee_id'],
                'leave_start_date' => $validated['leave_start_date'],
                'period1_days' => (int) $validated['period1_days'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            return app(MaternityLeaveScheduleService::class)->syncAttendance($case);
        });

        $message = sprintf(
            'Tahap 1 CM berhasil didaftarkan untuk %d hari kalender (maksimal 45). Sebanyak %d hari kerja dibuat sebagai record CM; %d hari Minggu tetap dalam durasi kalender tetapi tidak dibuat sebagai record absensi. Tahap 2 baru aktif setelah tanggal persalinan aktual dan bukti persalinan diunggah.',
            $syncResult['period1_days'],
            $syncResult['updated'],
            $syncResult['sundays_excluded']
        );

        if ($syncResult['skipped_locked'] > 0) {
            $message .= sprintf(
                ' %d tanggal dilewati karena payroll periodenya sudah terkunci.',
                $syncResult['skipped_locked']
            );
        }

        if (($syncResult['sundays_legacy_without_snapshot'] ?? 0) > 0) {
            $message .= sprintf(
                ' Perhatian: ditemukan %d record CM hari Minggu lama tanpa snapshot absensi asal. Record tersebut tidak dihapus otomatis agar data absensi asli tidak hilang; periksa manual pada salinan database.',
                $syncResult['sundays_legacy_without_snapshot']
            );
        }

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', $message);
    }

    public function uploadCertificate(Request $request, MaternityLeaveCase $maternityCase)
    {
        $this->authorizeManagement();
        abort_if($maternityCase->status === 'verified', 409, 'Kasus CM yang sudah diverifikasi tidak dapat diubah dokumennya.');

        $validated = $request->validate([
            'delivery_date' => [
                'required',
                'date',
                'after_or_equal:' . $maternityCase->leave_start_date->format('Y-m-d'),
                'before_or_equal:today',
            ],
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('certificate');
        $newPath = $file->store('maternity-certificates', 'local');

        if (!$newPath) {
            return back()->with('error', 'Dokumen gagal disimpan. Silakan coba kembali.');
        }

        try {
            $result = DB::transaction(function () use ($maternityCase, $validated, $file, $newPath) {
                $lockedCase = MaternityLeaveCase::query()
                    ->whereKey($maternityCase->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    $lockedCase->status === 'verified',
                    409,
                    'Kasus CM yang sudah diverifikasi tidak dapat diubah dokumennya.'
                );

                $oldPath = $lockedCase->certificate_path;

                $lockedCase->update([
                    'delivery_date' => $validated['delivery_date'],
                    'certificate_path' => $newPath,
                    'certificate_original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'document_uploaded_at' => now(),
                    'status' => 'submitted',
                    'rejection_reason' => null,
                    'verified_at' => null,
                    'verified_by' => null,
                ]);

                $syncResult = app(MaternityLeaveScheduleService::class)
                    ->syncAttendance($lockedCase->fresh());

                return [
                    'old_path' => $oldPath,
                    'sync' => $syncResult,
                ];
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        // Delete the previous file only after the database + calendar update
        // has committed successfully.
        if (!empty($result['old_path']) && $result['old_path'] !== $newPath) {
            Storage::disk('local')->delete($result['old_path']);
        }

        $syncResult = $result['sync'];
        $successMessage = sprintf(
            'Tanggal persalinan aktual tersimpan. Tahap 2 selama 45 hari kalender telah dibuat setelah persalinan. Total record CM hari kerja: %d; %d hari Minggu tidak dibuat sebagai record CM. Menunggu verifikasi HRD.',
            $syncResult['updated'],
            $syncResult['sundays_excluded']
        );

        if (($syncResult['sundays_legacy_without_snapshot'] ?? 0) > 0) {
            $successMessage .= sprintf(
                ' Perhatian: %d record CM hari Minggu lama tidak memiliki snapshot absensi asal sehingga tidak dihapus otomatis.',
                $syncResult['sundays_legacy_without_snapshot']
            );
        }

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', $successMessage);
    }

    public function downloadCertificate(MaternityLeaveCase $maternityCase): StreamedResponse
    {
        $this->authorizeManagement();

        abort_unless(
            $maternityCase->certificate_path
            && Storage::disk('local')->exists($maternityCase->certificate_path),
            404
        );

        return Storage::disk('local')->download(
            $maternityCase->certificate_path,
            $maternityCase->certificate_original_name ?: 'bukti-persalinan'
        );
    }

    public function verify(MaternityLeaveCase $maternityCase)
    {
        $this->authorizeManagement();

        DB::transaction(function () use ($maternityCase): void {
            $lockedCase = MaternityLeaveCase::query()
                ->whereKey($maternityCase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedCase->status === 'verified', 409, 'Kasus CM sudah diverifikasi.');
            abort_unless(
                $lockedCase->status === 'submitted'
                && $lockedCase->delivery_date
                && $lockedCase->certificate_path
                && Storage::disk('local')->exists($lockedCase->certificate_path),
                422,
                'Tanggal persalinan dan dokumen bukti wajib tersedia sebelum verifikasi.'
            );

            $lockedCase->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => Auth::id(),
                'rejection_reason' => null,
            ]);
        });

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', 'Kasus CM diverifikasi. Saldo CM yang tertahan akan dilepas pada payroll terbuka berikutnya saat payroll disimpan.');
    }

    public function reject(Request $request, MaternityLeaveCase $maternityCase)
    {
        $this->authorizeManagement();
        abort_if($maternityCase->status === 'verified', 409, 'Kasus yang sudah diverifikasi tidak dapat ditolak.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($maternityCase, $validated): void {
            $lockedCase = MaternityLeaveCase::query()
                ->whereKey($maternityCase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedCase->status === 'verified', 409, 'Kasus yang sudah diverifikasi tidak dapat ditolak.');

            $lockedCase->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);
        });

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', 'Dokumen ditolak. Nilai CM tetap ditahan sampai dokumen yang benar diunggah dan diverifikasi.');
    }
}
