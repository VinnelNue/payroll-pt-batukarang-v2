<?php

namespace App\Mail;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payroll;
    public $pdfBinary;

    public function __construct(Payroll $payroll, $pdfBinary)
    {
        $this->payroll = $payroll;
        $this->pdfBinary = $pdfBinary;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Slip Gaji PT. BATU KARANG - Periode ' . $this->payroll->period_month,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <p>Halo <strong>{$this->payroll->employee->full_name}</strong>,</p>
                <p>Berikut kami lampirkan dokumen <strong>Slip Gaji</strong> Anda untuk periode <strong>{$this->payroll->period_month}</strong>.</p>
                <p>Terima kasih.<br><strong>HRD & Payroll Dept - PT. Batu Karang Malang</strong></p>
            ",
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'Slip_Gaji_' . $this->payroll->employee->full_name . '_' . $this->payroll->period_month . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}