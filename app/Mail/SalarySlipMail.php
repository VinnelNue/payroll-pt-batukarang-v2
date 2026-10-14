<?php

namespace App\Mail;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalarySlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payroll $payroll,
        public string $pdfBinary
    ) {}

    public function build(): static
    {
        $employeeName = $this->payroll->employee->full_name
            ?? 'Karyawan';

        $period = $this->payroll->period_month;

        return $this->subject(
            'Slip Gaji ' . $period . ' - ' . $employeeName
        )
            ->view('emails.payrolls.salary_slip')
            ->with([
                'payroll' => $this->payroll,
            ])
            ->attachData(
                $this->pdfBinary,
                'Slip_Gaji_' . $period . '.pdf',
                [
                    'mime' => 'application/pdf',
                ]
            );
    }
}