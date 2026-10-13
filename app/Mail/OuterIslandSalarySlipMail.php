<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OuterIslandSalarySlipMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $period,
        public string $pdfBinary,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Slip Gaji Luar Pulau - ' . $this->period
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payrolls.outer_island_salary_slip',
            with: [
                'employeeName' => $this->employeeName,
                'period' => $this->period,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdfBinary,
                $this->pdfFilename
            )->withMime('application/pdf'),
        ];
    }
}