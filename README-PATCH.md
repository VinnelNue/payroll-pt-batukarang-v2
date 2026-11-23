# Payroll Batu Karang — Contract History & Payroll Period Patch (v2)

Patch ini memperbaiki pencatatan Contract History, penentuan Current Contract, pemilihan kontrak untuk payroll per periode, import Excel, tampilan kontrak, dan export payroll.

**Patch ini tidak membutuhkan migration database.** Kolom yang digunakan sudah ada pada skema yang dikirim: `current_contract_history_id`, `start_date`, `end_date`, `exit_date`, `change_type`, `change_reason`, kompensasi, BPJS, PTKP, serta data payroll.

> Jangan gunakan ZIP patch versi sebelumnya (`payroll-contract-history-patch.zip`). Gunakan paket v2 ini.

## 1. Aturan bisnis yang diterapkan

### Contract History dan Current Contract

1. Setiap history wajib memiliki `start_date`.
2. `end_date = NULL` berarti kontrak masih terbuka. Kontrak biasa tetap berlaku sampai ada tanggal penutupan, terminasi, atau history berikutnya yang menggantikannya.
3. Jika history baru dimulai saat history sebelumnya masih terbuka atau tanggal akhirnya melewati tanggal mulai history baru, history sebelumnya ditutup pada sehari sebelum tanggal mulai history baru.
4. Gap yang memang ada pada data lama tidak otomatis diisi. Gap ditandai dan tidak dianggap sebagai kontrak aktif.
5. Tanggal `end_date` dan `exit_date` bersifat inklusif. `exit_date` diperlakukan sebagai hari terakhir yang masih tercakup.
6. `current_contract_history_id` hanya menunjuk satu history yang mencakup hari ini. History masa depan tidak menggantikan current sebelum tanggal efektif. Jika tidak ada history yang mencakup hari ini atau terdapat overlap, pointer menjadi `NULL`.
7. Tidak ada fallback ke history terakhir yang sudah kedaluwarsa.
8. `employees.is_active`, `employee_contracts.is_active`, dan current pointer adalah hal yang berbeda. Proses sinkronisasi current tidak otomatis mengaktifkan kembali master karyawan/kontrak yang sengaja dinonaktifkan.
9. History bertipe terminasi (`PHK`, `Resign`, `Pensiun`, `End_Contract`) tidak dianggap sebagai kontrak terbuka tanpa batas. Bila data lama tidak mempunyai `end_date` dan `exit_date`, `start_date` dipakai sebagai tanggal efektif terakhir untuk menghindari status terminasi terbuka selamanya.

### Status tampilan

- `Aktif`: ada satu history yang mencakup hari ini.
- `Menunggu Keputusan`: master karyawan aktif, punya history sebelumnya, tetapi tidak ada history yang mencakup hari ini dan tidak ada history masa depan.
- `Akan Berlaku`: ada history masa depan.
- `Tidak Aktif`: master karyawan nonaktif atau terminasi sudah efektif.
- `Perlu Pemeriksaan`: terdapat lebih dari satu history yang mencakup hari ini.
- `Belum Ada History`: belum pernah ada history kontrak.

16 karyawan yang sebelumnya diaudit (history terakhir berakhir 31 Oktober 2026 dan masih menunggu keputusan HRD) tetap dipertahankan sebagai karyawan aktif dengan `current_contract_history_id = NULL`. Patch tidak membuat perpanjangan kontrak secara otomatis.

### Reward, Punishment, level dan gaji

Kategori form yang tersedia:

- `level_up`: level baru harus lebih tinggi; gaji pokok tidak boleh turun.
- `level_down`: level baru harus lebih rendah; gaji pokok tidak boleh naik.
- `salary_adjustment`: gaji pokok harus berubah; jabatan, level, kategori, department, placement, dan PTKP dipertahankan.
- `reward`: level dan/atau gaji pokok harus naik; nilai lainnya tidak boleh turun.
- `punishment`: level dan/atau gaji pokok harus turun; nilai lainnya tidak boleh naik.
- `job_promotion` / `job_demotion`: wajib mencatat jabatan baru. Besaran level dan kompensasi mengikuti keputusan yang diinput, tidak ditentukan oleh formula buatan sistem.
- `position_change`: jabatan, level, kategori, gaji, tunjangan, dan PTKP dipertahankan; department/placement dapat diperbarui.
- `contract_renewal`: membuat periode/kontrak berikutnya dan menaikkan urutan PKWT jika berlaku.

**Catatan istilah:** kategori `reward` dipakai sebagai interpretasi sementara atas istilah “giving” yang disebutkan dalam diskusi. Tidak ada persentase kenaikan atau potongan yang dikarang. Nominal/level harus diisi berdasarkan keputusan resmi perusahaan dan alasan perubahan wajib untuk reward/punishment serta perubahan non-renewal.

Formula tunjangan perusahaan yang sudah ada tetap dipertahankan: `basic_salary × (level × 2%)`. Hak akses Finance, Keuangan, Head HRD, dan HRD tetap mengikuti pembatasan role yang sudah ada di controller.

## 2. Aturan payroll

1. Payroll memilih Contract History berdasarkan bulan yang sedang diproses, bukan `current_contract_history_id` hari ini.
2. Halaman payroll, daftar payroll tersimpan, PDF slip, dan export Excel menggunakan history yang relevan dengan periode yang dipilih.
3. Jika satu bulan mempunyai perubahan kompensasi di tengah bulan, proses payroll baru untuk karyawan tersebut dilewati karena belum ada aturan prorata yang disepakati. Sistem tidak memilih salah satu gaji secara diam-diam.
4. Jika history kosong, gap, atau overlap, karyawan tersebut tidak dihitung pada periode itu dan alasannya dicantumkan pada pesan hasil proses. Karyawan lain yang mempunyai basis kontrak valid tetap dapat diproses. Jika tidak ada satu pun karyawan yang mempunyai basis kontrak valid, proses simpan dihentikan.
5. Validasi absensi harian memastikan status absensi tidak dihitung di luar semua periode Contract History. Status absensi yang tersimpan tidak dihapus oleh validasi ini. Baris hari libur otomatis `HB` di luar masa kontrak tidak dimasukkan ke snapshot payroll.
6. Payroll yang sudah terkunci tidak dihitung ulang. Perubahan history yang beririsan dengan payroll terkunci ditolak sampai prosedur unlock resmi dijalankan.
7. Tidak ada perubahan rumus gaji pokok, overtime, gantungan, BPJS, PPh 21, maternity leave, atau pengolahan fingerprint dalam patch ini.

## 3. File yang diganti / ditambahkan

| Lokasi di project | Aksi |
|---|---|
| `app/Console/Commands/SyncCurrentContractHistories.php` | Tambahkan command |
| `app/Exports/PayrollLocalViewSheet.php` | Ganti |
| `app/Http/Controllers/ContractLocalController.php` | Ganti |
| `app/Http/Controllers/PayrollController.php` | Ganti |
| `app/Imports/EmployeeImport.php` | Ganti |
| `app/Support/AttendanceEmployeeScope.php` | Ganti |
| `app/Support/ContractHistoryResolver.php` | Tambahkan resolver |
| `resources/views/contracts/local/contract.blade.php` | Ganti |
| `resources/views/contracts/local/history.blade.php` | Ganti |
| `resources/views/contracts/local/index.blade.php` | Ganti |
| `resources/views/payrolls/local/create.blade.php` | Ganti |

File model dan migration tidak diganti.

## 4. Cara pemasangan di Windows / Laragon

### A. Backup dulu

1. Export database `payroll_batu_karang_v2` dari phpMyAdmin ke file SQL.
2. Dari folder project, backup folder `app` dan `resources`:

```powershell
$backup = "..\payroll-backup-contract-history-$(Get-Date -Format yyyyMMdd-HHmm)"
New-Item -ItemType Directory -Path $backup -Force | Out-Null
Copy-Item -Path .\app, .\resources -Destination $backup -Recurse -Force
```

### B. Salin patch

Ekstrak ZIP. Set `$patch` ke folder hasil ekstrak yang berisi folder `app` dan `resources`, lalu jalankan dari root project Laravel:

```powershell
$patch = "C:\PATH\KE\HASIL\EKSTRAK\payroll-contract-history-v2"
Copy-Item -Path "$patch\*" -Destination (Get-Location).Path -Recurse -Force
```

Pastikan folder patch yang ditunjuk `$patch` berisi `app`, `resources`, dan `README-PATCH.md`.

### C. Bersihkan cache dan cek command

```powershell
php artisan optimize:clear
php artisan list | Select-String "contracts:sync-current-history"
```

Jika command belum terdeteksi:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan list | Select-String "contracts:sync-current-history"
```

### D. Dry-run dahulu — jangan langsung sinkronisasi

```powershell
php artisan contracts:sync-current-history --dry-run
```

Periksa hasilnya sebelum menjalankan command tanpa `--dry-run`. Kirim output dry-run untuk diperiksa terlebih dahulu. Jangan menjalankan sinkronisasi real berulang kali tanpa memeriksa hasil.

## 5. Uji penerimaan setelah pemasangan

1. Periksa Andhik Dharmabakti: Juli 2026 dan Agustus 2026 harus menggunakan Contract III; September 2026 harus menggunakan Contract IV.
2. Buat/siapkan history kontrak dengan `start_date` dan `end_date = NULL`. Selama belum ada history pengganti, history itu harus menjadi current setelah tanggal mulainya berlaku.
3. Tambahkan history berikutnya dengan tanggal mulai masa depan. History lama harus ditutup pada sehari sebelumnya; current pointer tidak boleh berpindah sebelum tanggal mulai baru.
4. Pastikan 16 karyawan yang masih menunggu keputusan tetap mempunyai master aktif dan current pointer NULL sampai keputusan HRD dicatat.
5. Pastikan payroll historis membaca history bulan yang dipilih meskipun current hari ini berbeda.
6. Pastikan history overlap/gap atau perubahan kompensasi di tengah bulan tidak dihitung dengan basis yang ambigu.
7. Uji Reward/Punishment: alasan wajib, reward harus naik dan punishment harus turun, tanpa persentase otomatis.
8. Uji import Excel dengan history masa depan dan pastikan current pointer tetap menunjuk kontrak yang berlaku hari ini.
9. Pastikan import atau koreksi tidak mengubah periode payroll yang sudah terkunci.
10. Pastikan status master karyawan yang sengaja nonaktif tidak otomatis aktif kembali hanya karena ada history yang mencakup hari ini.

Untuk 16 karyawan yang masih menunggu keputusan HRD, payroll bulan November tidak akan dibuat dari kontrak yang sudah berakhir. Bila HRD memutuskan perpanjangan, catat history baru dengan tanggal efektif yang telah disetujui terlebih dahulu.

## 6. Batas pengujian paket

Seluruh file PHP dan Blade telah diperiksa dengan `php -l` pada lingkungan pembuatan patch. Pemeriksaan tersebut hanya memvalidasi sintaks PHP. Paket belum dijalankan di Laragon milik pengguna, belum dikompilasi oleh Blade runtime Laravel, dan belum menjalani tes integrasi terhadap database lokal.

Setelah disalin, lakukan `php artisan optimize:clear`, jalankan dry-run, dan uji skenario di atas sebelum dipakai untuk payroll produksi.
