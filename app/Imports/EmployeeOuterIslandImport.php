<?php

namespace App\Imports;

use App\Models\ContractHistoryOuterIsland;
use App\Models\ContractOuterIsland;
use App\Models\EmployeeOuterIsland;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import Outer Island berdasarkan struktur HRIS pada Database Area Luar Pulau 1.ods.
 *
 * HRIS adalah satu-satunya sumber yang dipakai otomatis untuk membentuk history:
 * - Freelance 1-6: AA:AF, masing-masing satu rentang tanggal dalam satu sel.
 * - Kontrak I-VII: AG:AT, pasangan kolom awal/akhir dua kolom per periode.
 * - Awal Kontrak berlaku: AV dipakai untuk awal PKWTT jika status G adalah Tetap.
 *
 * Sheet pendukung tidak diimpor otomatis karena format beberapa sheet tidak konsisten
 * dan bisa membuat history salah/duplikat. History pada HRIS lebih otoritatif.
 */
class EmployeeOuterIslandImport implements WithMultipleSheets
{
    private array $conflictingKtps = [];

    public array $report = [
        'employees_created' => 0,
        'employees_updated' => 0,
        'employees_relinked_by_internal_nik' => 0,
        'masters_created' => 0,
        'histories_created' => 0,
        'histories_updated_from_hris' => 0,
        'histories_duplicates_removed' => 0,
        'stale_period_histories_removed' => 0,
        'histories_skipped_duplicate' => 0,
        'rows_skipped' => 0,
        'errors' => [],
    ];

    public function sheets(): array
    {
        return [
            'HRIS' => new OuterIslandHrisSheet($this),
        ];
    }

    /**
     * Import CSV hasil ekspor sheet HRIS. HRIS CSV ini memiliki satu baris kosong/
     * dekoratif sebelum header, sehingga jangan memakai importer CSV lama yang
     * menganggap kolom pertama sebagai NIK KTP. Header dicari berdasarkan nama kolom.
     *
     * @throws \RuntimeException bila CSV bukan hasil ekspor HRIS yang dikenali.
     */
    public function importCsvFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('File CSV HRIS tidak bisa dibaca.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new \RuntimeException('File CSV HRIS kosong.');
        }
        // Banyak CSV yang diekspor dari Excel Windows masih memakai Windows-1252
        // (misalnya karakter en dash byte 0x96). Normalisasi ke UTF-8 agar nama/tanggal
        // non-ASCII aman diproses oleh mbstring dan database.
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        // Cari header di awal file. Deteksi delimiter dari struktur header, bukan
        // baris pertama karena baris pertama ekspor ODS dapat berisi tanda titik koma kosong.
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $delimiter = null;
        $headerLineIndex = null;
        $headerRequired = [
            'NAMA',
            'NIK KTP',
            'STATUS PEKERJAAN',
            'FREELANCE 1',
            'FREELANCE 6',
            'KONTRAK I START DATE',
            'KONTRAK VII END DATE',
        ];

        $normalizeHeader = static function (array $fields): array {
            return array_map(static function ($field): string {
                $field = preg_replace('/\s+/u', ' ', trim((string) $field)) ?? trim((string) $field);
                return mb_strtoupper(ltrim($field, "\xEF\xBB\xBF"));
            }, $fields);
        };

        foreach (array_slice($lines, 0, 20, true) as $lineIndex => $line) {
            if (trim($line) === '') {
                continue;
            }
            foreach ([ ';', ',' ] as $candidateDelimiter) {
                $fields = str_getcsv($line, $candidateDelimiter, '"', '\\');
                $normalized = $normalizeHeader($fields);
                $matches = count(array_intersect($headerRequired, $normalized));
                if ($matches >= 6) {
                    $delimiter = $candidateDelimiter;
                    $headerLineIndex = $lineIndex;
                    break 2;
                }
            }
        }

        if ($delimiter === null || $headerLineIndex === null) {
            throw new \RuntimeException(
                'Header CSV tidak dikenali sebagai HRIS. Gunakan hasil ekspor dengan kolom Nama, NIK KTP, Status Pekerjaan, Freelance 1–6, serta Kontrak I–VII. Tidak ada data yang diimpor.'
            );
        }

        // Baca dari buffer yang sudah dinormalisasi UTF-8, bukan membuka ulang file asli.
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Buffer CSV HRIS tidak dapat dibuat.');
        }
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        $lineIndex = -1;
        $foundHeader = false;
        try {
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $lineIndex++;
                if (isset($row[0])) {
                    $row[0] = ltrim((string) $row[0], "\xEF\xBB\xBF");
                }

                if (!$foundHeader) {
                    if ($lineIndex !== $headerLineIndex) {
                        continue;
                    }
                    $normalized = $normalizeHeader($row);
                    $matches = count(array_intersect($headerRequired, $normalized));
                    if ($matches < 6) {
                        throw new \RuntimeException('Header HRIS gagal dibaca. Periksa delimiter dan format CSV.');
                    }
                    // Samakan struktur baris dengan handler Excel: index 0 dekoratif,
                    // index 1 header, data mulai index 2.
                    $rows[] = [];
                    $rows[] = $row;
                    $foundHeader = true;
                    continue;
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        if (!$foundHeader || count($rows) <= 2) {
            throw new \RuntimeException('Header HRIS ditemukan, tetapi tidak ada baris data setelah header.');
        }

        $collection = new Collection($rows);
        // Validasi duplikat identitas dilakukan atas seluruh CSV sebelum menulis data.
        $this->preflightHris($collection);
        foreach ($collection as $index => $row) {
            if ($index < 2) {
                continue;
            }
            $this->importHrisRow($row instanceof Collection ? $row->toArray() : (array) $row, $index + 1);
        }
    }

    /** Validate duplicate identities across the full source before importing any HRIS row. */
    public function preflightHris(Collection $rows): void
    {
        $byKtp = [];
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue; // Row 1 kosong/merge; row 2 adalah header.
            }

            $r = $row instanceof Collection ? $row->toArray() : (array) $row;
            $ktp = $this->text($r[11] ?? null); // L: NIK KTP
            $name = $this->normalizeName($r[4] ?? null); // E: Nama
            $internalNik = $this->text($r[2] ?? null); // C: NIK internal

            if (!$ktp || !$name || !preg_match('/^\d{16}$/', $ktp)) {
                continue;
            }

            $byKtp[$ktp][] = [
                'row' => $index + 1,
                'name' => trim((string) ($r[4] ?? '')),
                'normalized_name' => $name,
                'internal_nik' => strtoupper((string) ($internalNik ?? '')),
            ];
        }

        foreach ($byKtp as $ktp => $records) {
            $identities = [];
            foreach ($records as $record) {
                $identities[$record['normalized_name'].'|'.$record['internal_nik']] = true;
            }

            if (count($identities) > 1) {
                $details = implode('; ', array_map(
                    fn (array $record) => "baris Excel {$record['row']}: {$record['name']} (NIK internal {$record['internal_nik']})",
                    $records
                ));
                $message = "NIK KTP {$ktp} dipakai oleh beberapa identitas: {$details}. Semua baris dengan NIK KTP ini dilewati supaya history tidak tercampur; perbaiki NIK KTP di HRIS lalu import ulang.";
                $this->conflictingKtps[$ktp] = $message;
                $this->error($message);
            }
        }
    }

    public function importHrisRow(array $r, int $rowNumber): void
    {
        $nikKtp = $this->text($r[11] ?? null); // L
        $name = $this->text($r[4] ?? null); // E
        $internalNik = $this->text($r[2] ?? null); // C
        $pin = $this->text($r[1] ?? null); // B

        // Abaikan baris kosong atau header; jangan membentuk employee parsial.
        if (!$nikKtp && !$name && !$internalNik) {
            return;
        }

        if ($nikKtp && isset($this->conflictingKtps[$nikKtp])) {
            $this->report['rows_skipped']++;
            return;
        }

        if (!$nikKtp || !$name || strtoupper($name) === 'NAMA' || !preg_match('/^\d{16}$/', $nikKtp)) {
            $this->skip("HRIS baris {$rowNumber}: NIK KTP atau nama kosong/tidak valid.");
            return;
        }

        $dob = $this->datePublic($r[15] ?? null); // P: Tanggal Lahir
        $gender = $this->gender($r[18] ?? null); // S: Jenis Kelamin
        $birthPlace = $this->text($r[14] ?? null); // O: Tempat Lahir
        $maritalStatus = $this->marital($r[19] ?? null); // T: Status Kawin
        $ptkp = $this->ptkpFromChildren($r[20] ?? null, $r[19] ?? null); // U: Jumlah Anak

        DB::transaction(function () use (
            $r,
            $rowNumber,
            $nikKtp,
            $name,
            $internalNik,
            $pin,
            $dob,
            $gender,
            $birthPlace,
            $maritalStatus,
            $ptkp
        ): void {
            $employeeByKtp = EmployeeOuterIsland::query()
                ->where('nik_ktp_outer', $nikKtp)
                ->first();
            $employeeByInternalNik = $this->findEmployeeByInternalNik($internalNik);
            $employee = $employeeByKtp;

            if ($employeeByKtp && $this->normalizeName($employeeByKtp->full_name_outer) !== $this->normalizeName($name)) {
                // KTP cocok tetapi nama tidak: jangan menimpa identitas berdasarkan KTP saja.
                // Boleh pulih dari data import lama hanya bila NIK internal menunjuk tepat satu
                // master employee lain; selain itu hentikan baris untuk pemeriksaan manual.
                if ($employeeByInternalNik
                    && (int) $employeeByInternalNik->id_employee_outer_island !== (int) $employeeByKtp->id_employee_outer_island) {
                    $employee = $employeeByInternalNik;
                    $this->report['employees_relinked_by_internal_nik']++;
                    $this->error("HRIS baris {$rowNumber}: identitas '{$name}' dipulihkan berdasarkan NIK internal {$internalNik}; KTP pada database sebelumnya terkait nama berbeda.");
                } else {
                    $this->skip("HRIS baris {$rowNumber}: NIK KTP {$nikKtp} sudah terdaftar atas nama '{$employeeByKtp->full_name_outer}', sedangkan sumber berisi '{$name}'. NIK internal tidak dapat membuktikan identitas lain secara unik; baris dilewati.");
                    return;
                }
            } elseif (!$employeeByKtp && $employeeByInternalNik) {
                // Bila KTP sumber sudah diperbaiki, gunakan history NIK internal yang unik
                // untuk memperbarui employee lama alih-alih membuat employee duplikat.
                $employee = $employeeByInternalNik;
                $this->report['employees_relinked_by_internal_nik']++;
                $this->error("HRIS baris {$rowNumber}: employee '{$name}' ditemukan kembali melalui NIK internal {$internalNik}; NIK KTP akan disinkronkan dari HRIS.");
            }

            if (!$employee && (!$dob || !$gender)) {
                $this->skip("HRIS baris {$rowNumber} ({$name}): karyawan baru tidak dibuat karena tanggal lahir atau jenis kelamin kosong/tidak valid.");
                return;
            }

            // Pemetaan mengikuti header workbook terbaru, bukan indeks workbook lama.
            $employeePayload = [
                'nik_ktp_outer' => $nikKtp,
                'no_kk_outer' => $this->text($r[12] ?? null), // M
                'full_name_outer' => $name, // E
                'gender_outer' => $gender, // S
                'birth_place_outer' => $birthPlace ?: '-', // O
                'birth_date_outer' => $dob, // P
                'religion_outer' => $this->text($r[13] ?? null), // N
                'marital_status_outer' => $maritalStatus, // T
                'address_ktp_outer' => $this->text($r[17] ?? null) ?: '-', // R
                'phone_number_outer' => $this->text($r[22] ?? null), // W
                'npwp_number_outer' => $this->text($r[24] ?? null), // Y
                'email_outer' => $this->email($r[25] ?? null), // Z
                'is_active' => true,
            ];

            if ($employee) {
                // Excel kosong tidak menimpa data identitas yang sudah tersimpan.
                $employee->fill(array_filter(
                    $employeePayload,
                    fn ($value) => $value !== null && $value !== ''
                ));
                $employee->save();
                $this->report['employees_updated']++;
            } else {
                if (!$dob || !$gender) {
                    $this->skip("HRIS baris {$rowNumber} ({$name}): tanggal lahir/jenis kelamin wajib untuk membuat employee baru.");
                    return;
                }

                $employee = EmployeeOuterIsland::create(array_merge(
                    ['uuid' => (string) Str::uuid()],
                    array_filter($employeePayload, fn ($value) => $value !== null && $value !== '')
                ));
                $this->report['employees_created']++;
            }

            // UUID wajib pada tabel master kontrak.
            $master = ContractOuterIsland::firstOrCreate(
                ['employee_outer_island_id' => $employee->id_employee_outer_island],
                [
                    'uuid' => (string) Str::uuid(),
                    'current_contract_history_id' => null,
                    'is_active' => false,
                ]
            );
            if ($master->wasRecentlyCreated) {
                $this->report['masters_created']++;
            }

            $jobTitle = $this->text($r[5] ?? null) ?: 'Karyawan'; // F
            $area = $this->text($r[3] ?? null); // D
            $statusRaw = $this->text($r[6] ?? null); // G
            $statusType = $this->normalizeEmploymentType($statusRaw);
            $statusSequence = $statusType === 'PKWT' ? $this->pkwtSequenceFromStatus($statusRaw) : null;

            // Simpan akhir periode terakhir hanya untuk fallback status Tetap bila AV kosong.
            $latestPeriodEnd = null;

            // Freelance 1-6 pada AA-AF. Tiap sel harus berupa SATU rentang lengkap.
            $validFreelanceSequences = [];
            $emptyFreelanceSequences = [];
            for ($sequence = 1; $sequence <= 6; $sequence++) {
                $raw = $r[25 + $sequence] ?? null; // AA=index 26 ... AF=index 31
                $ranges = $this->dateRangesPublic($raw);
                if (count($ranges) > 1) {
                    $this->error("HRIS baris {$rowNumber}: Freelance {$sequence} berisi lebih dari satu rentang; sel dilewati.");
                    continue;
                }

                if ($ranges === []) {
                    if ($this->isClearlyEmptyPeriodCell($raw)) {
                        $emptyFreelanceSequences[] = $sequence;
                    }
                    continue;
                }

                $validFreelanceSequences[$sequence] = true;
                foreach ($ranges as [$start, $end]) {
                    if ($end && ($latestPeriodEnd === null || $end > $latestPeriodEnd)) {
                        $latestPeriodEnd = $end;
                    }
                    $this->upsertAuthoritativeHistory($employee, $master, [
                        'employment_type' => 'Freelance',
                        'pkwt_sequence' => null,
                        'freelance_sequence' => $sequence,
                        'start_date' => $start,
                        'end_date' => $end,
                        'job_title' => $jobTitle,
                        'placement_area' => $area,
                        'nik_fingerprint' => $internalNik,
                        'fingerprint_pin' => $pin,
                        'ptkp_status' => $ptkp,
                    ]);
                }
            }

            // Kontrak I-VII pada AG:AT: tiap periode menempati dua kolom berurutan.
            $validPkwtSequences = [];
            $emptyPkwtSequences = [];
            
            for ($sequence = 1; $sequence <= 7; $sequence++) {
                $startIndex = 32 + (($sequence - 1) * 2); // AG, AI, AK, AM, AO, AQ, AS
                $startRaw = $r[$startIndex] ?? null;
                $endRaw = $r[$startIndex + 1] ?? null;
                $start = $this->datePublic($startRaw);
                $end = $this->datePublic($endRaw);

                // Bila satu sel kebetulan menyimpan rentang utuh, parse rentang itu sebagai fallback.
                if ((!$start || !$end) && is_string($startRaw)) {
                    $range = $this->singleDateRange($startRaw);
                    if ($range) {
                        $start = $start ?: $range[0];
                        $end = $end ?: $range[1];
                    }
                }
                if ((!$start || !$end) && is_string($endRaw)) {
                    $range = $this->singleDateRange($endRaw);
                    if ($range) {
                        $start = $start ?: $range[0];
                        $end = $end ?: $range[1];
                    }
                }

                if (!$start && !$end) {
                    if ($this->isClearlyEmptyPeriodCell($startRaw) && $this->isClearlyEmptyPeriodCell($endRaw)) {
                        $emptyPkwtSequences[] = $sequence;
                    }
                    continue;
                }
                if (!$start || !$end || $end < $start) {
                    $this->error("HRIS baris {$rowNumber}: Kontrak {$sequence} tidak dibuat karena tanggal awal/akhir tidak lengkap atau tidak valid.");
                    continue;
                }

                $validPkwtSequences[$sequence] = true;
                if ($end && ($latestPeriodEnd === null || $end > $latestPeriodEnd)) {
                    $latestPeriodEnd = $end;
                }
                $this->upsertAuthoritativeHistory($employee, $master, [
                    'employment_type' => 'PKWT',
                    'pkwt_sequence' => $sequence,
                    'freelance_sequence' => null,
                    'start_date' => $start,
                    'end_date' => $end,
                    'job_title' => $jobTitle,
                    'placement_area' => $area,
                    'nik_fingerprint' => $internalNik,
                    'fingerprint_pin' => $pin,
                    'ptkp_status' => $ptkp,
                ]);
            }

            // HRIS adalah sumber otoritatif untuk slot bernomor. Jika sumber benar-benar
            // kosong, hapus slot lama yang mungkin tersimpan keliru oleh importer sebelumnya.
            // Sel berisi teks non-tanggal yang tidak dikenal tidak menghapus history; dicatat saja.
            $this->removeStaleNumberedPeriods(
                $master,
                $emptyFreelanceSequences,
                $emptyPkwtSequences
            );

            // Status G menjadi penanda jika pasangan periode belum berhasil dibaca.
            // Kolom H (Habis Kontrak) tidak diimpor dan tidak dipakai membuat tanggal kontrak.
            if ($statusType === 'PKWT' && $statusSequence && empty($validPkwtSequences[$statusSequence])) {
                $this->error("HRIS baris {$rowNumber}: status '{$statusRaw}' menunjuk Kontrak {$statusSequence}, tetapi pasangan tanggal Kontrak {$statusSequence} kosong/tidak valid. History tidak dibuat dari kolom Habis Kontrak karena kolom itu harus dihitung dari history yang valid.");
            }

            // Tetap -> PKWTT. Utamakan tanggal resmi AV. Jika AV kosong, inferensikan
            // tanggal mulai hanya ketika history sebelumnya memiliki end_date valid yang
            // sudah lewat; gunakan hari berikutnya dan catat warning, jangan menebak dari H.
            if ($statusType === 'PKWTT') {
                $startPermanent = $this->datePublic($r[47] ?? null); // AV
                if (!$startPermanent && $latestPeriodEnd && $latestPeriodEnd < Carbon::today()->toDateString()) {
                    $startPermanent = Carbon::parse($latestPeriodEnd)->addDay()->toDateString();
                    $this->error("HRIS baris {$rowNumber}: status Tetap dan AV kosong; tanggal PKWTT diinferensikan {$startPermanent} dari sehari setelah akhir periode terakhir {$latestPeriodEnd}. Mohon verifikasi bila tanggal SK Tetap berbeda.");
                }
                if ($startPermanent) {
                    $this->upsertAuthoritativeHistory($employee, $master, [
                        'employment_type' => 'PKWTT',
                        'pkwt_sequence' => null,
                        'freelance_sequence' => null,
                        'start_date' => $startPermanent,
                        'end_date' => null,
                        'job_title' => $jobTitle,
                        'placement_area' => $area,
                        'nik_fingerprint' => $internalNik,
                        'fingerprint_pin' => $pin,
                        'ptkp_status' => $ptkp,
                    ]);
                } else {
                    $this->error("HRIS baris {$rowNumber}: status Tetap tetapi AV (Awal Kontrak berlaku) kosong/tidak valid; PKWTT tidak dibuat karena tanggal mulai tidak boleh ditebak.");
                }
            }

            $this->syncCurrent($master, $statusType, $statusSequence);
        });
    }

    /**
     * Find the employee owner of an internal fingerprint NIK only when exactly one master
     * matches. This is a recovery path for old imports that merged two people by KTP.
     */
    private function findEmployeeByInternalNik(?string $internalNik): ?EmployeeOuterIsland
    {
        if (!$internalNik) {
            return null;
        }

        $matches = EmployeeOuterIsland::query()
            ->whereHas('contractMaster.histories', function ($query) use ($internalNik) {
                $query->where('nik_fingerprint', $internalNik);
            })
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Empty or non-date labels explicitly mean that this numbered source slot has no period. */
    private function isClearlyEmptyPeriodCell(mixed $value): bool
    {
        if ($value === null) return true;
        $text = mb_strtolower(trim((string) $value));
        if ($text === '' || in_array($text, ['-', '—', '–', 'n/a', 'null'], true)) return true;
        return in_array($text, ['freelance', 'sales lepas', 'sopir lepas'], true);
    }

    /**
     * Remove numbered history slots whose source cells are explicitly empty.
     * This cleans bad leftover periods from earlier imports without erasing a slot
     * whose source text was non-empty but could not be parsed safely.
     */
    private function removeStaleNumberedPeriods(
        ContractOuterIsland $master,
        array $emptyFreelanceSequences,
        array $emptyPkwtSequences
    ): void {
        foreach (array_unique($emptyFreelanceSequences) as $sequence) {
            $deleted = ContractHistoryOuterIsland::query()
                ->where('contract_outer_island_id', $master->id_contract_outer_island)
                ->where('employment_type', 'Freelance')
                ->where('freelance_sequence', (int) $sequence)
                ->delete();
            $this->report['stale_period_histories_removed'] += $deleted;
        }

        foreach (array_unique($emptyPkwtSequences) as $sequence) {
            $deleted = ContractHistoryOuterIsland::query()
                ->where('contract_outer_island_id', $master->id_contract_outer_island)
                ->where('employment_type', 'PKWT')
                ->where('pkwt_sequence', (int) $sequence)
                ->delete();
            $this->report['stale_period_histories_removed'] += $deleted;
        }

        // Older importer versions stored Freelance rows without a sequence. HRIS AA:AF
        // now defines the six authoritative slots, so those unnumbered legacy rows cannot
        // be labelled correctly and are removed rather than shown as a misleading period.
        $deletedLegacy = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $master->id_contract_outer_island)
            ->where('employment_type', 'Freelance')
            ->whereNull('freelance_sequence')
            ->delete();
        $this->report['stale_period_histories_removed'] += $deletedLegacy;
    }

    /** Upsert HRIS history by employee + period sequence; re-import corrects old bad dates. */
    private function upsertAuthoritativeHistory(
        EmployeeOuterIsland $employee,
        ContractOuterIsland $master,
        array $data
    ): void {
        $start = $data['start_date'] ?? null;
        $type = $data['employment_type'] ?? null;
        if (!$start || !$type) {
            return;
        }

        $sequenceField = match ($type) {
            'PKWT' => 'pkwt_sequence',
            'Freelance' => 'freelance_sequence',
            default => null,
        };

        $query = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $master->id_contract_outer_island)
            ->where('employment_type', $type);

        if ($sequenceField !== null && isset($data[$sequenceField])) {
            $query->where($sequenceField, (int) $data[$sequenceField]);
        } elseif ($type === 'PKWTT') {
            // PKWTT adalah satu riwayat permanen terbuka per master.
            $query->whereNull('end_date');
        } else {
            return;
        }

        $existingRows = $query->orderBy('id_contract_history_outer_island')->get();
        $payload = [
            'nik_fingerprint' => $data['nik_fingerprint'] ?? null,
            'fingerprint_pin' => $data['fingerprint_pin'] ?? null,
            'job_title' => $data['job_title'] ?: 'Karyawan',
            'placement_area' => $data['placement_area'] ?? null,
            'ptkp_status' => $data['ptkp_status'] ?: 'TK/0',
            'employment_type' => $type,
            'pkwt_sequence' => $type === 'PKWT' ? (int) $data['pkwt_sequence'] : null,
            'freelance_sequence' => $type === 'Freelance' ? (int) $data['freelance_sequence'] : null,
            'start_date' => $start,
            'end_date' => $data['end_date'] ?? null,
            'updated_at' => now(),
        ];

        if ($existingRows->isNotEmpty()) {
            $keep = $existingRows->first();
            DB::table('employee_contract_histories_outer_island')
                ->where('id_contract_history_outer_island', $keep->id_contract_history_outer_island)
                ->update($payload);

            $duplicateIds = $existingRows
                ->skip(1)
                ->pluck('id_contract_history_outer_island')
                ->all();
            if ($duplicateIds !== []) {
                // Duplikat slot yang sama dibuat oleh import lama yang keliru; sumber HRIS berwenang.
                DB::table('employee_contract_histories_outer_island')
                    ->whereIn('id_contract_history_outer_island', $duplicateIds)
                    ->delete();
                $this->report['histories_duplicates_removed'] += count($duplicateIds);
            }

            $this->report['histories_updated_from_hris']++;
            return;
        }

        // Tabel employee_contract_histories_outer_island mewajibkan uuid.
        DB::table('employee_contract_histories_outer_island')->insert([
            'uuid' => (string) Str::uuid(),
            'contract_outer_island_id' => $master->id_contract_outer_island,
            'nik_fingerprint' => $data['nik_fingerprint'] ?? null,
            'fingerprint_pin' => $data['fingerprint_pin'] ?? null,
            'job_title' => $data['job_title'] ?: 'Karyawan',
            'department' => null,
            'placement_area' => $data['placement_area'] ?? null,
            // Kategori dan level memang diminta untuk di-skip saat import.
            'category' => null,
            'level' => null,
            'basic_salary' => 0,
            'allowance' => 0,
            'is_bpjstk_active' => 1,
            'is_bpjs_health_active' => 1,
            'ptkp_status' => $data['ptkp_status'] ?: 'TK/0',
            'use_manual_bpjs' => 0,
            'manual_bpjs_tk_employee' => 0,
            'manual_bpjs_ks_employee' => 0,
            'manual_bpjs_company' => 0,
            'employment_type' => $type,
            'pkwt_sequence' => $payload['pkwt_sequence'],
            'freelance_sequence' => $payload['freelance_sequence'],
            'start_date' => $start,
            'end_date' => $data['end_date'] ?? null,
            'exit_date' => null,
            'exit_reason' => null,
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->report['histories_created']++;
    }

    /** Synchronize active history based on the dates and the status marker in HRIS G. */
    private function syncCurrent(
        ContractOuterIsland $master,
        ?string $statusType = null,
        ?int $statusSequence = null
    ): void {
        $today = Carbon::today()->toDateString();
        $candidates = ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $master->id_contract_outer_island)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->whereNotIn('employment_type', ['PHK', 'Resign', 'Pensiun', 'End_Contract'])
            ->orderByDesc('start_date')
            ->orderByDesc('id_contract_history_outer_island')
            ->get();

        $current = null;
        if ($candidates->isNotEmpty() && $statusType !== null) {
            $current = $candidates->first(function ($history) use ($statusType, $statusSequence) {
                if ($statusType === 'PKWT') {
                    return $history->employment_type === 'PKWT'
                        && $statusSequence !== null
                        && (int) $history->pkwt_sequence === $statusSequence;
                }
                if ($statusType === 'PKWTT') {
                    return $history->employment_type === 'PKWTT';
                }
                if ($statusType === 'Freelance') {
                    return $history->employment_type === 'Freelance';
                }
                return false;
            });
        }
        $current ??= $candidates->first();

        ContractHistoryOuterIsland::query()
            ->where('contract_outer_island_id', $master->id_contract_outer_island)
            ->update(['is_active' => false]);

        if ($current) {
            ContractHistoryOuterIsland::query()
                ->where('id_contract_history_outer_island', $current->id_contract_history_outer_island)
                ->update(['is_active' => true]);
        }

        $master->forceFill([
            'current_contract_history_id' => $current?->id_contract_history_outer_island,
            'is_active' => $current !== null,
        ])->save();

        $employeeId = $master->employee_outer_island_id;
        EmployeeOuterIsland::query()
            ->where('id_employee_outer_island', $employeeId)
            ->update(['is_active' => $current !== null]);
    }

    public function dateRangesPublic(mixed $value): array
    {
        if ($value === null || $value === '' || $value instanceof \DateTimeInterface) {
            return [];
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            // Freelance column must contain start AND end in one cell, not one date serial.
            return [];
        }

        $text = trim((string) $value);
        if ($text === '' || in_array(mb_strtolower($text), ['-', 'n/a', 'null'], true)) {
            return [];
        }
        $text = str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        if (!preg_match('/^(.+?)\s*(?:-|s\/d|s\.d\.?|sd|sampai)\s*(.+)$/iu', $text, $match)) {
            // Labels such as "Freelance" or "Sales Lepas" are not dates.
            return [];
        }

        $startParts = $this->periodDateParts(trim($match[1]));
        $endParts = $this->periodDateParts(trim($match[2]));
        if (!$startParts || !$endParts) {
            $this->error("Rentang tanggal tidak dikenali dan dilewati: {$text}");
            return [];
        }

        if ($startParts['month'] === null) $startParts['month'] = $endParts['month'];
        if ($endParts['month'] === null) $endParts['month'] = $startParts['month'];
        if (!$startParts['month'] || !$endParts['month']) {
            $this->error("Bulan pada rentang tanggal tidak dapat ditentukan: {$text}");
            return [];
        }

        if ($startParts['year'] === null) $startParts['year'] = $endParts['year'];
        if ($endParts['year'] === null) $endParts['year'] = $startParts['year'];
        if (!$startParts['year'] || !$endParts['year']) {
            $this->error("Tahun pada rentang tanggal tidak dapat ditentukan: {$text}");
            return [];
        }

        if (!checkdate((int) $startParts['month'], (int) $startParts['day'], (int) $startParts['year'])
            || !checkdate((int) $endParts['month'], (int) $endParts['day'], (int) $endParts['year'])) {
            $this->error("Tanggal kalender tidak valid pada rentang: {$text}");
            return [];
        }

        $start = sprintf('%04d-%02d-%02d', $startParts['year'], $startParts['month'], $startParts['day']);
        $end = sprintf('%04d-%02d-%02d', $endParts['year'], $endParts['month'], $endParts['day']);

        // Untuk rentang lintas tahun yang hanya mencantumkan tahun di satu ujung.
        if ($end < $start && !$startParts['_explicit_year'] && $endParts['_explicit_year']
            && $startParts['month'] > $endParts['month']) {
            $startParts['year']--;
            if (!checkdate((int) $startParts['month'], (int) $startParts['day'], (int) $startParts['year'])) return [];
            $start = sprintf('%04d-%02d-%02d', $startParts['year'], $startParts['month'], $startParts['day']);
        } elseif ($end < $start && $startParts['_explicit_year'] && !$endParts['_explicit_year']
            && $endParts['month'] < $startParts['month']) {
            $endParts['year']++;
            if (!checkdate((int) $endParts['month'], (int) $endParts['day'], (int) $endParts['year'])) return [];
            $end = sprintf('%04d-%02d-%02d', $endParts['year'], $endParts['month'], $endParts['day']);
        }

        if ($end < $start) {
            $this->error("Tanggal berakhir lebih awal dari tanggal mulai: {$text}");
            return [];
        }

        return [[$start, $end]];
    }

    private function singleDateRange(string $value): ?array
    {
        $ranges = $this->dateRangesPublic($value);
        return count($ranges) === 1 ? $ranges[0] : null;
    }

    private function periodDateParts(string $value): ?array
    {
        $value = trim($value, " \t\n\r\0\x0B.,;");
        if (preg_match('/^(\d{1,2})$/', $value, $m)) {
            return ['day' => (int) $m[1], 'month' => null, 'year' => null, '_explicit_year' => false];
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            return ['day' => (int) $m[3], 'month' => (int) $m[2], 'year' => (int) $m[1], '_explicit_year' => true];
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})$/', $value, $m)) {
            $year = (int) $m[3];
            if ($year < 100) $year = $year <= 69 ? 2000 + $year : 1900 + $year;
            return ['day' => (int) $m[1], 'month' => (int) $m[2], 'year' => $year, '_explicit_year' => true];
        }
        if (!preg_match('/^(\d{1,2})\s+([[:alpha:]\.]+)\s*(\d{2,4})?$/iu', $value, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $monthText = mb_strtolower(rtrim($m[2], '.'));
        $month = $this->monthNumber($monthText);
        if (!$month) return null;
        $explicitYear = isset($m[3]) && $m[3] !== '';
        $year = $explicitYear ? (int) $m[3] : null;
        if ($year !== null && $year < 100) $year = $year <= 69 ? 2000 + $year : 1900 + $year;
        return ['day' => $day, 'month' => $month, 'year' => $year, '_explicit_year' => $explicitYear];
    }

    private function monthNumber(string $month): ?int
    {
        $month = mb_strtolower(preg_replace('/[^\pL]/u', '', $month));
        return match (true) {
            str_starts_with($month, 'jan') => 1,
            str_starts_with($month, 'feb') => 2,
            str_starts_with($month, 'mar') => 3,
            str_starts_with($month, 'apr') => 4,
            str_starts_with($month, 'mei'), str_starts_with($month, 'may') => 5,
            str_starts_with($month, 'jun') => 6,
            str_starts_with($month, 'jul') => 7,
            str_starts_with($month, 'agu'), str_starts_with($month, 'ags'), str_starts_with($month, 'agt'), str_starts_with($month, 'aug') => 8,
            str_starts_with($month, 'sep') => 9,
            str_starts_with($month, 'okt'), str_starts_with($month, 'oct') => 10,
            str_starts_with($month, 'nov') => 11,
            str_starts_with($month, 'des'), str_starts_with($month, 'dec') => 12,
            default => null,
        };
    }

    public function datePublic(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->toDateString();
            if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            $s = trim((string) $value);
            if ($s === '' || in_array(mb_strtolower($s), ['-', 'n/a', 'null'], true)) return null;
            $s = str_replace(["\u{2013}", "\u{2014}", "\u{2212}"], '-', $s);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                return Carbon::createFromFormat('!Y-m-d', $s)->toDateString();
            }
            $parts = $this->periodDateParts($s);
            if ($parts && $parts['month'] !== null && $parts['year'] !== null
                && checkdate($parts['month'], $parts['day'], $parts['year'])) {
                return sprintf('%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day']);
            }
            foreach (['d M Y', 'd F Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'j M Y', 'j F Y', 'd M y', 'd F y', 'j M y', 'j F y'] as $format) {
                try {
                    $date = Carbon::createFromFormat('!'.$format, $s);
                    if ($date) return $date->toDateString();
                } catch (\Throwable $e) {
                    // Try the next explicitly supported date format.
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function normalizeEmploymentType(?string $value): ?string
    {
        $s = mb_strtolower(trim((string) $value));
        if ($s === '') return null;
        if (str_contains($s, 'tetap') || str_contains($s, 'pkwtt')) return 'PKWTT';
        if (str_contains($s, 'freelance') || str_contains($s, 'freelancer') || str_contains($s, 'sales lepas')) return 'Freelance';
        if (str_contains($s, 'pkwt') || str_contains($s, 'kontrak')) return 'PKWT';
        return null;
    }

    private function pkwtSequenceFromStatus(?string $value): ?int
    {
        $s = mb_strtoupper(trim((string) $value));
        if ($s === '') return null;
        $roman = [
            'VII' => 7,
            'VI' => 6,
            'V' => 5,
            'IV' => 4,
            'III' => 3,
            'II' => 2,
            'I' => 1,
        ];
        foreach ($roman as $label => $number) {
            if (preg_match('/\b(?:KONTRAK|PKWT)\s*'.$label.'\b/u', $s)) return $number;
        }
        if (preg_match('/\b(?:KONTRAK|PKWT)\s*(\d+)\b/u', $s, $m)) {
            $number = (int) $m[1];
            return $number >= 1 && $number <= 7 ? $number : null;
        }
        return null;
    }

    private function gender(mixed $value): ?string
    {
        $s = mb_strtoupper(trim((string) $value));
        return match ($s) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA' => 'L',
            'P', 'PEREMPUAN', 'WANITA' => 'P',
            default => null,
        };
    }

    private function marital(mixed $value): string
    {
        $s = mb_strtolower(trim((string) $value));
        if (str_contains($s, 'cerai')) return 'divorced';
        if (str_contains($s, 'belum kawin') || str_contains($s, 'tidak kawin') || str_contains($s, 'lajang') || $s === 'single') return 'single';
        if (str_contains($s, 'kawin') || str_contains($s, 'menikah') || $s === 'married') return 'married';
        return 'single';
    }

    /**
     * Jumlah Anak in HRIS uses legacy PTKP codes: K01=K/0, K02=K/1,
     * K03=K/2, K04=K/3. TK00 is normalized to TK/0.
     */
    private function ptkpFromChildren(mixed $value, mixed $maritalValue = null): string
    {
        $s = mb_strtoupper(trim((string) $value));
        if ($s === '' || $s === '-') {
            return $this->marital($maritalValue) === 'married' ? 'K01' : 'TK/0';
        }

        if (preg_match('/^K0([1-4])$/', $s, $m)) return 'K0'.$m[1];
        if (preg_match('/^TK00$/', $s)) return 'TK/0';
        if (preg_match('/^TK0([1-4])$/', $s, $m)) return 'TK0'.$m[1];
        if (preg_match('/^(K|TK)\s*\/\s*([0-4])$/', $s, $m)) {
            $n = min(3, (int) $m[2]);
            if ($m[1] === 'K') return 'K0'.($n + 1);
            return $n === 0 ? 'TK/0' : 'TK0'.$n;
        }
        if (preg_match('/^([0-4])$/', $s, $m)) {
            $n = min(3, (int) $m[1]);
            if ($this->marital($maritalValue) === 'married') return 'K0'.($n + 1);
            return $n === 0 ? 'TK/0' : 'TK0'.$n;
        }

        $this->error("Kode Jumlah Anak/PTKP tidak dikenali: {$s}; nilai default mengikuti status kawin.");
        return $this->marital($maritalValue) === 'married' ? 'K01' : 'TK/0';
    }

    private function email(mixed $value): ?string
    {
        $value = $this->text($value);
        return $value && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        return $text === '' || $text === '-' ? null : $text;
    }

    private function normalizeName(mixed $value): string
    {
        $name = mb_strtolower(trim((string) ($value ?? '')));
        return preg_replace('/\s+/u', ' ', $name) ?? $name;
    }

    private function skip(string $message): void
    {
        $this->report['rows_skipped']++;
        $this->error($message);
    }

    private function error(string $message): void
    {
        $this->report['errors'][] = $message;
        Log::warning('[EmployeeOuterIslandImport] '.$message);
    }
}

class OuterIslandHrisSheet implements ToCollection
{
    public function __construct(private EmployeeOuterIslandImport $parent) {}

    public function collection(Collection $rows): void
    {
        // Preflight dilakukan sebelum satu baris pun ditulis ke database.
        $this->parent->preflightHris($rows);

        foreach ($rows as $index => $row) {
            if ($index < 2) continue; // Baris 1 kosong, baris 2 header.
            $this->parent->importHrisRow($row->toArray(), $index + 1);
        }
    }
}
