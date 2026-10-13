<?php

namespace App\Jobs;

use App\Mail\OuterIslandSalarySlipMail;
use App\Models\EmployeeOuterIsland;
use App\Models\PayrollOuterIsland;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOuterIslandSalarySlipEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(public int $emailLogId)
    {
    }

    public function handle(): void
    {
        $log = DB::table('payroll_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->first();

        if (!$log || in_array($log->status, ['sent', 'skipped'], true)) {
            return;
        }

        DB::table('payroll_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'error_message' => null,
                'updated_at' => now(),
            ]);

        $employee = EmployeeOuterIsland::query()
            ->with(['contractMaster.currentHistory'])
            ->where('id_employee_outer_island', $log->employee_outer_island_id)
            ->firstOrFail();

        $payroll = PayrollOuterIsland::query()
            ->where('employee_outer_island_id', $employee->id_employee_outer_island)
            ->where('period_month', $log->period_month)
            ->firstOrFail();

        $recipient = trim((string) ($log->recipient_email ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email penerima tidak valid.');
        }

        $contract = $employee->contractMaster?->currentHistory;

        // Samakan relasi yang digunakan oleh PDF dengan tombol Cetak Slip PDF.
        $payroll->setRelation('employee', $employee);

        $pdfBinary = Pdf::loadView(
            'payrolls.outer_island.pdf_slip',
            [
                'employee' => $employee,
                'payroll' => $payroll,
                'contract' => $contract,
                'period' => (string) $log->period_month,
            ]
        )
            ->setPaper('A4', 'portrait')
            ->output();

        $safeName = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '_',
            (string) ($employee->full_name_outer ?: 'Karyawan')
        ) ?: 'Karyawan';

        Mail::to($recipient)->send(
            new OuterIslandSalarySlipMail(
                (string) ($employee->full_name_outer ?: 'Karyawan'),
                (string) $log->period_month,
                $pdfBinary,
                "Slip_Gaji_Outer_Island_{$safeName}_{$log->period_month}.pdf"
            )
        );

        DB::table('payroll_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);
    }

    public function failed(Throwable $exception): void
    {
        DB::table('payroll_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 6000),
                'updated_at' => now(),
            ]);
    }
}
