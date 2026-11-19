<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MaternityLeaveCase;
use App\Models\AttendanceRecord;
use App\Models\Payroll;
use Carbon\Carbon;
use App\Services\MaternityLeaveScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'payrolls:id_payroll,employee_id,period_month,maternity_case_id,maternity_hold_days,maternity_hold_amount,maternity_release_amount,maternity_leave_pay,is_locked',
            ])
            ->orderByDesc('leave_start_date')
            ->orderByDesc('id')
            ->paginate(20);

        // Also detect payroll rows in the scheduled months even when an older
        // payroll row was not linked to maternity_case_id. This keeps the UI
        // action state aligned with the server-side delete guard.
        $cases->getCollection()->transform(function (MaternityLeaveCase $case): MaternityLeaveCase {
            $periods = $this->schedulePeriodMonths($case);
            $hasPayrollInSchedule = !empty($periods) && Payroll::query()
                ->where('employee_id', $case->employee_id)
                ->whereIn('period_month', $periods)
                ->exists();

            $case->setAttribute('has_payroll_in_schedule', $hasPayrollInSchedule);

            return $case;
        });

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



    /**
     * Update tanggal dan durasi kasus CM.
     * Perubahan jadwal otomatis disinkronkan ke attendance_records melalui snapshot.
     */
    public function update(Request $request, MaternityLeaveCase $maternityCase)
    {
        $this->authorizeManagement();

        $validated = $request->validate([
            'leave_start_date' => ['required', 'date'],
            'period1_days' => ['required', 'integer', 'min:1', 'max:45'],
            'expected_delivery_date' => ['present', 'nullable', 'date', 'after_or_equal:leave_start_date'],
            'delivery_date' => ['present', 'nullable', 'date', 'after_or_equal:leave_start_date', 'before_or_equal:today'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $newDeliveryDate = $validated['delivery_date'] ?: null;
        $oldDeliveryDate = $maternityCase->delivery_date
            ? Carbon::parse($maternityCase->delivery_date)->toDateString()
            : null;
        $deliveryDateChanged = $newDeliveryDate !== $oldDeliveryDate;
        $certificate = $request->file('certificate');

        if ($deliveryDateChanged && $newDeliveryDate && !$certificate) {
            throw ValidationException::withMessages([
                'certificate' => 'Tanggal persalinan aktual berubah. Unggah bukti persalinan yang sesuai dengan tanggal baru.',
            ]);
        }

        if ($certificate && !$newDeliveryDate) {
            throw ValidationException::withMessages([
                'delivery_date' => 'Isi tanggal persalinan aktual sebelum mengunggah bukti persalinan.',
            ]);
        }

        $originalName = $certificate?->getClientOriginalName();
        $newPath = $certificate
            ? $certificate->store('maternity-certificates', 'local')
            : null;

        if ($certificate && !$newPath) {
            return back()->with('error', 'Dokumen baru gagal disimpan. Silakan coba kembali.');
        }

        try {
            $result = DB::transaction(function () use ($maternityCase, $validated, $newDeliveryDate, $newPath, $originalName) {
                $lockedCase = MaternityLeaveCase::query()
                    ->whereKey($maternityCase->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertCaseIsNotFinal($lockedCase);

                $oldPath = $lockedCase->certificate_path;
                $currentDeliveryDate = $lockedCase->delivery_date
                    ? Carbon::parse($lockedCase->delivery_date)->toDateString()
                    : null;
                $currentStartDate = Carbon::parse($lockedCase->leave_start_date)->toDateString();
                $dateChangedUnderLock = $currentDeliveryDate !== $newDeliveryDate;
                $scheduleChangedUnderLock = $currentStartDate !== Carbon::parse($validated['leave_start_date'])->toDateString()
                    || (int) $lockedCase->period1_days !== (int) $validated['period1_days']
                    || $dateChangedUnderLock;

                if ($dateChangedUnderLock && $newDeliveryDate && !$newPath) {
                    throw ValidationException::withMessages([
                        'certificate' => 'Tanggal persalinan aktual berubah. Unggah bukti persalinan yang sesuai dengan tanggal baru.',
                    ]);
                }

                if ($scheduleChangedUnderLock) {
                    $this->assertCaseCanBeEdited($lockedCase);
                    $this->assertSchedulePeriodsUnlocked($lockedCase);
                }

                $lockedCase->leave_start_date = $validated['leave_start_date'];
                $lockedCase->period1_days = (int) $validated['period1_days'];
                $lockedCase->expected_delivery_date = $validated['expected_delivery_date'] ?: null;
                $lockedCase->delivery_date = $newDeliveryDate;

                if ($newPath) {
                    $lockedCase->certificate_path = $newPath;
                    $lockedCase->certificate_original_name = $originalName
                        ? Str::limit($originalName, 255, '')
                        : null;
                    $lockedCase->document_uploaded_at = now();
                    $lockedCase->status = 'submitted';
                    $lockedCase->rejection_reason = null;
                    $lockedCase->verified_at = null;
                    $lockedCase->verified_by = null;
                } elseif ($newDeliveryDate === null) {
                    // Menghapus tanggal aktual berarti Tahap 2 dan dokumen aktual
                    // ikut dibatalkan. Tahap 1 tetap disinkronkan.
                    $lockedCase->certificate_path = null;
                    $lockedCase->certificate_original_name = null;
                    $lockedCase->document_uploaded_at = null;
                    $lockedCase->status = 'pending';
                    $lockedCase->rejection_reason = null;
                    $lockedCase->verified_at = null;
                    $lockedCase->verified_by = null;
                }

                $lockedCase->save();

                $freshCase = $lockedCase->fresh();
                $syncResult = [
                    'updated' => 0,
                    'restored' => 0,
                ];

                if ($scheduleChangedUnderLock) {
                    $this->assertSchedulePeriodsUnlocked($freshCase);
                    $syncResult = app(MaternityLeaveScheduleService::class)
                        ->syncAttendance($freshCase);
                }

                return [
                    'old_path' => $oldPath,
                    'sync' => $syncResult,
                    'schedule_changed' => $scheduleChangedUnderLock,
                    'periods' => $this->schedulePeriodMonths($freshCase),
                ];
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if (!empty($result['old_path']) && $result['old_path'] !== $newPath) {
            Storage::disk('local')->delete($result['old_path']);
        }

        $sync = $result['sync'];
        $periodLabels = implode(', ', $result['periods']);

        $successMessage = $result['schedule_changed']
            ? sprintf(
                'Data dan jadwal CM berhasil diperbarui. Record CM disinkronkan: %d; absensi asal dipulihkan: %d. Silakan simpan ulang payroll periode %s agar perhitungan mengikuti jadwal terbaru.',
                $sync['updated'],
                $sync['restored'],
                $periodLabels ?: 'terkait'
            )
            : 'Data referensi/dokumen CM berhasil diperbarui. Jadwal absensi tidak berubah.';

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', $successMessage);
    }

    /**
     * Hapus kasus CM hanya sebelum dipakai payroll dan tanpa periode terkunci.
     * AttendanceRecord yang tertimpa CM dipulihkan dari attendance_snapshots.
     */
    public function destroy(MaternityLeaveCase $maternityCase)
    {
        $this->authorizeManagement();

        // The database transaction restores original attendance and removes
        // the case row first. The physical file is deleted only after commit,
        // so a rollback can never leave a case pointing to a missing document.
        $result = DB::transaction(function () use ($maternityCase) {
            $lockedCase = MaternityLeaveCase::query()
                ->whereKey($maternityCase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCase->status === 'verified' || $lockedCase->released_at) {
                throw ValidationException::withMessages([
                    'maternity_case' => 'Kasus CM yang sudah diverifikasi atau dilepas tidak dapat dihapus.',
                ]);
            }

            $periods = $this->schedulePeriodMonths($lockedCase);
            $hasLinkedPayroll = Payroll::query()
                ->where('maternity_case_id', $lockedCase->getKey())
                ->exists();
            $hasPayrollInSchedule = !empty($periods) && Payroll::query()
                ->where('employee_id', $lockedCase->employee_id)
                ->whereIn('period_month', $periods)
                ->exists();

            if ($hasLinkedPayroll || $hasPayrollInSchedule) {
                throw ValidationException::withMessages([
                    'maternity_case' => 'Kasus CM tidak dapat dihapus karena sudah menyentuh periode payroll. Hapus/reset payroll draft terkait terlebih dahulu, lalu coba kembali.',
                ]);
            }

            $this->assertSchedulePeriodsUnlocked($lockedCase);

            $restored = $this->restoreAttendanceSnapshots($lockedCase);
            $certificatePath = $lockedCase->certificate_path;

            // Force a real database-row deletion even if the model later adopts
            // Laravel SoftDeletes. The file bytes are stored on the local disk,
            // while the path/name metadata live in this database row.
            DB::table($lockedCase->getTable())
                ->where($lockedCase->getKeyName(), $lockedCase->getKey())
                ->delete();

            return [
                'restored' => $restored,
                'certificate_path' => $certificatePath,
                'case_id' => $lockedCase->getKey(),
            ];
        });

        $fileCleanupFailed = false;
        $certificatePath = $result['certificate_path'];

        if ($certificatePath) {
            try {
                if (Storage::disk('local')->exists($certificatePath)) {
                    $deleted = Storage::disk('local')->delete($certificatePath);
                    $fileCleanupFailed = !$deleted && Storage::disk('local')->exists($certificatePath);
                }
            } catch (\Throwable $exception) {
                $fileCleanupFailed = true;
                Log::error('maternity.certificate.delete_failed', [
                    'maternity_case_id' => $result['case_id'],
                    'path' => $certificatePath,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $message = sprintf(
            'Kasus CM berhasil dihapus dari database. %d tanggal absensi asal dipulihkan dari snapshot.',
            $result['restored']
        );

        if ($fileCleanupFailed) {
            Log::error('maternity.certificate.delete_failed', [
                'maternity_case_id' => $result['case_id'],
                'path' => $certificatePath,
            ]);

            return redirect()
                ->route('payrolls.local.maternity.index')
                ->with('warning', $message . ' Namun file bukti persalinan belum berhasil dihapus dari storage lokal. Periksa log dan hapus file tersebut secara manual.');
        }

        return redirect()
            ->route('payrolls.local.maternity.index')
            ->with('success', $message . ' File bukti persalinan pada storage lokal juga sudah dihapus.');
    }

    private function assertCaseIsNotFinal(MaternityLeaveCase $case): void
    {
        if ($case->status === 'verified' || $case->released_at) {
            throw ValidationException::withMessages([
                'maternity_case' => 'Kasus CM yang sudah diverifikasi atau dilepas tidak dapat diubah. Hubungi Finance/administrator untuk koreksi historis.',
            ]);
        }
    }

    private function assertCaseCanBeEdited(MaternityLeaveCase $case): void
    {
        $this->assertCaseIsNotFinal($case);

        $hasPayrollImpact = Payroll::query()
            ->where('maternity_case_id', $case->getKey())
            ->where(function ($query): void {
                $query->where('maternity_hold_days', '>', 0)
                    ->orWhere('maternity_hold_amount', '>', 0)
                    ->orWhere('maternity_release_amount', '>', 0)
                    ->orWhere('maternity_leave_pay', '>', 0);
            })
            ->exists();

        if ($hasPayrollImpact) {
            throw ValidationException::withMessages([
                'maternity_case' => 'Tanggal CM tidak dapat diubah karena sudah memengaruhi perhitungan payroll. Perbaiki/reset payroll draft terkait terlebih dahulu.',
            ]);
        }
    }

    /** @return array<int, string> */
    private function schedulePeriodMonths(MaternityLeaveCase $case): array
    {
        $months = [];

        foreach ($case->schedulePeriods() as $period) {
            $cursor = Carbon::parse($period['start'])->startOfMonth();
            $lastMonth = Carbon::parse($period['end'])->startOfMonth();

            while ($cursor->lessThanOrEqualTo($lastMonth)) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        }

        foreach (array_keys((array) ($case->attendance_snapshots ?? [])) as $dateString) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateString)) {
                $months[] = substr((string) $dateString, 0, 7);
            }
        }

        $months = array_values(array_unique($months));
        sort($months);

        return $months;
    }

    private function assertSchedulePeriodsUnlocked(MaternityLeaveCase $case): void
    {
        $months = $this->schedulePeriodMonths($case);

        if (empty($months)) {
            return;
        }

        $lockedMonths = Payroll::query()
            ->where('employee_id', $case->employee_id)
            ->whereIn('period_month', $months)
            ->where('is_locked', true)
            ->pluck('period_month')
            ->map(static fn ($period): string => substr((string) $period, 0, 7))
            ->unique()
            ->values()
            ->all();

        if (!empty($lockedMonths)) {
            throw ValidationException::withMessages([
                'maternity_case' => 'Kasus CM tidak dapat diubah/dihapus karena menyentuh payroll yang sudah Close: ' . implode(', ', $lockedMonths) . '.',
            ]);
        }
    }

    private function restoreAttendanceSnapshots(MaternityLeaveCase $case): int
    {
        $snapshots = (array) ($case->attendance_snapshots ?? []);
        $dateBounds = [];

        foreach ($case->schedulePeriods() as $period) {
            $dateBounds[] = Carbon::parse($period['start'])->toDateString();
            $dateBounds[] = Carbon::parse($period['end'])->toDateString();
        }

        foreach (array_keys($snapshots) as $snapshotDate) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $snapshotDate)) {
                $dateBounds[] = (string) $snapshotDate;
            }
        }

        // A legacy CM row without a snapshot cannot be safely restored to its
        // original status, so stop rather than silently destroy attendance.
        // Include historical snapshot dates too, because the CM schedule may
        // have been edited before this deletion request.
        $legacyRows = collect();
        if (!empty($dateBounds)) {
            sort($dateBounds);
            $legacyRows = AttendanceRecord::query()
                ->where('employee_id', $case->employee_id)
                ->where('source', 'maternity')
                ->whereBetween('attendance_date', [reset($dateBounds), end($dateBounds)])
                ->get(['attendance_date']);
        }

        foreach ($legacyRows as $legacyRow) {
            $dateString = Carbon::parse($legacyRow->attendance_date)->toDateString();
            if (!array_key_exists($dateString, $snapshots)) {
                throw ValidationException::withMessages([
                    'maternity_case' => 'Ada record CM lama tanpa snapshot absensi asal pada ' . $dateString . '. Kasus tidak dihapus otomatis agar data absensi tidak hilang.',
                ]);
            }
        }

        $restored = 0;

        foreach ($snapshots as $dateString => $original) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateString)) {
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('employee_id', $case->employee_id)
                ->whereDate('attendance_date', $dateString)
                ->first();

            $wasMaternityRecord = $record
                && strtolower((string) $record->source) === 'maternity';

            if ($wasMaternityRecord) {
                $record->delete();
            }

            if (!empty($original['exists'])) {
                // Never overwrite a later manual/fingerprint correction.
                $current = AttendanceRecord::query()
                    ->where('employee_id', $case->employee_id)
                    ->whereDate('attendance_date', $dateString)
                    ->first();

                if (!$current) {
                    AttendanceRecord::create([
                        'employee_id' => $case->employee_id,
                        'attendance_date' => $dateString,
                        'status' => $original['status'] ?? null,
                        'source' => $original['source'] ?? null,
                    ]);
                    $restored++;
                }
            } elseif ($wasMaternityRecord) {
                $restored++;
            }
        }

        return $restored;
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
