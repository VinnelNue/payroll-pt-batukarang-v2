<?php

namespace App\Jobs;

use App\Mail\SalarySlipMail;
use App\Models\Employee;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLocalSalarySlipEmail implements ShouldQueue
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
        $log = DB::table('payroll_local_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->first();

        if (!$log || in_array($log->status, ['sent', 'skipped'], true)) {
            return;
        }

        DB::table('payroll_local_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'error_message' => null,
                'updated_at' => now(),
            ]);

        $employee = Employee::query()
            ->with(['contract.currentHistory'])
            ->where('id_employee', $log->employee_id)
            ->firstOrFail();

        $payroll = Payroll::query()
            ->where('employee_id', $employee->id_employee)
            ->where('period_month', $log->period_month)
            ->firstOrFail();

        $recipient = trim((string) ($log->recipient_email ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email penerima tidak valid.');
        }

        // Gunakan relasi employee yang sama dengan slip PDF Local.
        $payroll->setRelation('employee', $employee);

        $pdfBinary = Pdf::loadView(
            'payrolls.local.pdf_slip',
            compact('payroll')
        )->setPaper('a4', 'portrait')->output();

        Mail::to($recipient)->send(new SalarySlipMail($payroll, $pdfBinary));

        DB::table('payroll_local_slip_email_logs')
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
        DB::table('payroll_local_slip_email_logs')
            ->where('id', $this->emailLogId)
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 6000),
                'updated_at' => now(),
            ]);
    }
}
