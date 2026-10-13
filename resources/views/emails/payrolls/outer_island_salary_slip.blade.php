<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji {{ $period }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:28px;">
        <h2 style="margin:0 0 18px;font-size:20px;color:#111827;">Slip Gaji Luar Pulau</h2>

        <p style="margin:0 0 12px;">Yth. {{ $employeeName }},</p>

        <p style="margin:0 0 12px;line-height:1.6;">
            Terlampir slip gaji Anda untuk periode <strong>{{ $period }}</strong> dalam format PDF.
        </p>

        <p style="margin:0 0 20px;line-height:1.6;">
            Email ini berisi informasi Gaji pribadi. Mohon jangan meneruskan lampiran kepada pihak yang tidak berwenang.
        </p>

        <p style="margin:0;line-height:1.6;">Salam,<br><strong>PT. Batu Karang</strong></p>
    </div>
    <p style="max-width:620px;margin:14px auto 0;color:#6b7280;font-size:12px;line-height:1.5;">
        Email ini dikirim secara otomatis oleh sistem payroll. Mohon tidak membalas email ini jika alamat pengirim tidak menerima balasan.
    </p>
</body>
</html>
