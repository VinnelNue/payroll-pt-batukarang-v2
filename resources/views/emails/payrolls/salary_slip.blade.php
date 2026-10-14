<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">

    <h2>Slip Gaji Karyawan</h2>

    <p>Yth. {{ $payroll->employee->full_name ?? 'Karyawan' }},</p>

    <p>
        Bersama email ini kami lampirkan slip gaji Anda
        untuk periode <strong>{{ $payroll->period_month }}</strong>.
    </p>

    <p>
        Silakan buka file PDF yang terlampir untuk melihat
        rincian slip gaji Anda.
    </p>

    <p>
        Terima kasih.
    </p>

    <p>
        Hormat kami,<br>
        <strong>Tim HRD / Finance</strong>
    </p>

</body>
</html>