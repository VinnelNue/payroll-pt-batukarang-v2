<?php



namespace App\Imports;



use App\Models\ContractHistoryLocal;

use App\Models\ContractLocal;

use App\Models\Employee;

use Carbon\Carbon;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

use Maatwebsite\Excel\Concerns\ToCollection;

use Maatwebsite\Excel\Concerns\WithHeadingRow;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;



class EmployeeImport implements ToCollection, WithHeadingRow, SkipsEmptyRows

{
    private int $importedCount = 0;
    private int $updatedCount = 0;


    private const CONTRACT_COLUMNS = [

        1 => ['kontrak_i', 'contract_i', 'kontrak_1', 'contract_1'],

        2 => ['kontrak_ii', 'contract_ii', 'kontrak_2', 'contract_2'],

        3 => ['kontrak_iii', 'contract_iii', 'kontrak_3', 'contract_3'],

        4 => ['kontrak_iv', 'contract_iv', 'kontrak_4', 'contract_4'],

        5 => ['kontrak_v', 'contract_v', 'kontrak_5', 'contract_5'],

        6 => ['kontrak_vi', 'contract_vi', 'kontrak_6', 'contract_6'],

        7 => ['kontrak_vii', 'contract_vii', 'kontrak_7', 'contract_7'],

    ];



    private const ROMAN = [

        1 => 'i',

        2 => 'ii',

        3 => 'iii',

        4 => 'iv',

        5 => 'v',

        6 => 'vi',

        7 => 'vii',

    ];



    private function value(Collection|array $row, array $keys, mixed $default = null): mixed

    {

        foreach ($keys as $key) {

            if ($row instanceof Collection) {

                $value = $row->get($key);

            } else {

                $value = $row[$key] ?? null;

            }



            if ($value !== null && trim((string) $value) !== '') {

                return $value;

            }

        }



        return $default;

    }



    private function cleanNumber(mixed $value): ?string

    {

        if ($value === null || trim((string) $value) === '') {

            return null;

        }



        $str = trim((string) $value);



        if (is_numeric($value) && str_contains(strtoupper($str), 'E')) {

            return sprintf('%.0f', (float) $value);

        }



        return $str;

    }



    private function cleanText(mixed $value, ?string $default = null): ?string

    {

        if ($value === null) {

            return $default;

        }



        $text = trim((string) $value);



        return $text === '' ? $default : $text;

    }



    private function parseMoney(mixed $value): float

    {

        if ($value === null || trim((string) $value) === '') {

            return 0.0;

        }



        if (is_numeric($value)) {

            return (float) $value;

        }



        $value = trim((string) $value);

        $value = str_replace(['Rp', 'rp', ' ', "\xc2\xa0"], '', $value);



        if (str_contains($value, '.') && str_contains($value, ',')) {

            $value = str_replace('.', '', $value);

            $value = str_replace(',', '.', $value);

        } elseif (substr_count($value, '.') >= 1 && !str_contains($value, ',')) {

            $value = str_replace('.', '', $value);

        } elseif (substr_count($value, ',') >= 1 && !str_contains($value, '.')) {

            $value = str_replace(',', '', $value);

        }



        $value = preg_replace('/[^0-9.\\-]/', '', $value) ?? '';



        return is_numeric($value) ? (float) $value : 0.0;

    }



    private function parseDate(mixed $value, bool $required = false): ?string

    {

        if ($value instanceof Carbon) {

            return $value->format('Y-m-d');

        }



        if ($value instanceof \DateTimeInterface) {

            return Carbon::instance($value)->format('Y-m-d');

        }



        if ($value === null || trim((string) $value) === '') {

            return $required ? now()->format('Y-m-d') : null;

        }



        if (is_numeric($value)) {

            try {

                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');

            } catch (\Throwable) {

                // Continue with text parsing.

            }

        }



        $text = trim((string) $value);

        $text = preg_replace('/\\s+/u', ' ', $text) ?? $text;



        $monthMap = [

            'jan' => 'Jan', 'januari' => 'Jan',

            'feb' => 'Feb', 'februari' => 'Feb',

            'mar' => 'Mar', 'maret' => 'Mar',

            'apr' => 'Apr', 'april' => 'Apr',

            'mei' => 'May', 'may' => 'May',

            'jun' => 'Jun', 'juni' => 'Jun',

            'jul' => 'Jul', 'juli' => 'Jul',

            'agu' => 'Aug', 'ags' => 'Aug', 'agt' => 'Aug',

            'agustus' => 'Aug',

            'sep' => 'Sep', 'sept' => 'Sep', 'september' => 'Sep',

            'okt' => 'Oct', 'oktober' => 'Oct',

            'nov' => 'Nov', 'november' => 'Nov',

            'des' => 'Dec', 'desember' => 'Dec', 'dec' => 'Dec',

        ];



        foreach ($monthMap as $idMonth => $enMonth) {

            $text = preg_replace(

                '/\b' . preg_quote($idMonth, '/') . '\b/ui',

                $enMonth,

                $text

            ) ?? $text;

        }



        $text = preg_replace('/[–—−]/u', '-', $text) ?? $text;



        // CRITICAL: normalize 2-digit years explicitly.

        // This prevents values such as "01 Nov 25" becoming year 0025.

        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{2}|\d{4})$/', $text, $m)) {

            $day = (int) $m[1];

            $monthName = ucfirst(strtolower($m[2]));

            $year = (int) $m[3];



            if ($year < 100) {

                $year += $year <= 68 ? 2000 : 1900;

            }



            try {

                return Carbon::createFromFormat(

                    '!j M Y',

                    sprintf('%d %s %d', $day, $monthName, $year)

                )->format('Y-m-d');

            } catch (\Throwable) {

                // Continue below.

            }

        }



        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd M Y', 'j M Y'] as $format) {

            try {

                $date = Carbon::createFromFormat($format, $text);

                if ($date !== false) {

                    return $date->format('Y-m-d');

                }

            } catch (\Throwable) {

                // Try next format.

            }

        }



        // Fallback for 2-digit year numeric dates: 01-11-25 / 01/11/25.

        if (preg_match('/^(\\d{1,2})[-\\/\s]*(\\d{1,2})[-\\/\s]*(\\d{2})$/', $text, $m)) {

            $year = (int) $m[3];

            $year += $year <= 68 ? 2000 : 1900;



            try {

                return Carbon::create(

                    $year,

                    (int) $m[2],

                    (int) $m[1]

                )->format('Y-m-d');

            } catch (\Throwable) {

                // Continue.

            }

        }



        try {

            $date = Carbon::parse($text);

            return $date->format('Y-m-d');

        } catch (\Throwable) {

            return $required ? now()->format('Y-m-d') : null;

        }

    }



    private function parseContractRange(mixed $value): ?array
    {
        $text = $this->cleanText($value);

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/[–—−]/u', '-', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        // Format utama: 01/01/2026 - 31/01/2026
        // Format lain yang umum di Excel: 01/01/2026 s/d 31/01/2026
        // atau 01/01/2026 sd 31/01/2026.
        $startText = null;
        $endText = null;

        if (preg_match('/^(.+?)\s+-\s+(.+)$/u', $text, $m)) {
            $startText = trim($m[1]);
            $endText = trim($m[2]);
        } elseif (preg_match('/^(.+?)\s+s\s*\/\s*d\s+(.+)$/iu', $text, $m)) {
            $startText = trim($m[1]);
            $endText = trim($m[2]);
        } elseif (preg_match('/^(.+?)\s+sd\s+(.+)$/iu', $text, $m)) {
            $startText = trim($m[1]);
            $endText = trim($m[2]);
        }

        if ($startText === null) {
            return null;
        }

        $start = $this->parseDate($startText, false);
        $end = $this->parseDate($endText, false);

        if (!$start) {
            return null;
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    private function normalizeGender(mixed $value): string

    {

        $raw = strtoupper(trim((string) ($value ?? '')));



        if ($raw === '') {

            return 'L';

        }



        if (

            str_starts_with($raw, 'P') ||

            str_starts_with($raw, 'F') ||

            str_contains($raw, 'PEREMPUAN') ||

            str_contains($raw, 'FEMALE')

        ) {

            return 'P';

        }



        return 'L';

    }



    /*

     * Normalize PTKP dari Excel tanpa mengubah format bisnis Excel.

     * Contoh: TK0, TK01, TK02, K01, K02, K03, K04.

     *

     * Format lama seperti TK/0, TK/2, K/1 juga diterima agar data

     * yang sudah ada tidak rusak, lalu disimpan kembali dalam format

     * yang konsisten dengan Excel.

     */

    private function normalizePtkpStrict(mixed $value): string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));

        // Bersihkan spasi Excel, termasuk non-breaking space.
        $raw = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $raw);
        $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
        $raw = trim($raw);

        // Kosong / 0 = TK0.
        if ($raw === '' || $raw === '0') {
            return 'TK0';
        }

        /*
        |--------------------------------------------------------------------------
        | FORMAT PTKP YANG DITERIMA
        |--------------------------------------------------------------------------
        |
        | TK0, TK1, TK2, TK3, TK4
        | TK01, TK02, TK03, TK04
        | TK/0, TK/1, TK/2, TK/3, TK/4
        |
        | K0, K1, K2, K3
        | K01, K02, K03, K04
        | K/0, K/1, K/2, K/3
        |
        | Penyimpanan database:
        |
        | TK0  -> TK0
        | TK1  -> TK01
        | TK2  -> TK02
        | TK3  -> TK03
        | TK4  -> TK04
        |
        | K0   -> K01   (K/0)
        | K1   -> K02   (K/1)
        | K2   -> K03   (K/2)
        | K3   -> K04   (K/3)
        | K01  -> K01
        | K02  -> K02
        | K03  -> K03
        | K04  -> K04
        |--------------------------------------------------------------------------
        */

        if (!preg_match(
            '/^(TK|K)\s*(?:[\/\-_]\s*)?(0[0-4]|[0-4])$/u',
            $raw,
            $match
        )) {
            throw new \InvalidArgumentException(
                "PTKP tidak valid: {$value}. Gunakan TK0-TK04 atau K0-K3 / K01-K04."
            );
        }

        $prefix = $match[1];
        $numberRaw = $match[2];

        // Normalisasi angka ke integer untuk membedakan K0, K01, dst.
        $number = (int) $numberRaw;

        if ($prefix === 'TK') {
            if ($number < 0 || $number > 4) {
                throw new \InvalidArgumentException(
                    "PTKP tidak valid: {$value}. Gunakan TK0, TK01, TK02, TK03, atau TK04."
                );
            }

            return $number === 0
                ? 'TK0'
                : 'TK' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        // Jika Excel sudah mengirim K01-K04, pertahankan nilainya.
        if (strlen($numberRaw) === 2) {
            if ($number < 1 || $number > 4) {
                throw new \InvalidArgumentException(
                    "PTKP tidak valid: {$value}. Gunakan K01, K02, K03, atau K04."
                );
            }

            return 'K' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        // Jika Excel mengirim K0/K1/K2/K3, geser satu tingkat:
        // K0 -> K01, K1 -> K02, K2 -> K03, K3 -> K04.
        if ($number < 0 || $number > 3) {
            throw new \InvalidArgumentException(
                "PTKP tidak valid: {$value}. Untuk format K, gunakan K0-K3 atau K01-K04."
            );
        }

        return 'K' . str_pad((string) ($number + 1), 2, '0', STR_PAD_LEFT);
    }

    private function normalizePtkp(mixed $value): string

    {
        try {
            return $this->normalizePtkpStrict($value);
        } catch (\InvalidArgumentException) {
            return 'TK0';
        }
    }



    /**
     * Ambil PTKP dari kolom status_pernikahan pada Excel.
     *
     * PENTING:
     * - Kolom status_pernikahan pada workbook ini berisi KODE PTKP.
     * - Nilai tersebut hanya dipakai untuk contract_histories.ptkp_status.
     * - Nilai tersebut TIDAK pernah dimasukkan ke employees.marital_status.
     */
    private function extractPtkp(Collection|array $row): string
    {
        // Sumber PTKP import adalah kolom Excel "status_pernikahan".
        // Nilainya TIDAK disimpan ke employees.marital_status.
        // Nilai tersebut hanya dipakai sebagai ptkp_status pada contract history.
        $candidate = $this->value($row, [
            // Header Excel yang digunakan: Status_Pernikahan
            // WithHeadingRow akan membacanya sebagai status_pernikahan.
            'status_pernikahan',
        ]);

        if ($candidate === null) {
            throw new \InvalidArgumentException(
                'PTKP wajib diisi pada kolom status_pernikahan. Gunakan TK0-TK04 atau K0-K3 / K01-K04.'
            );
        }

        // Normalizer menangani TK02/K02 maupun format lama seperti TK/2 atau K/2.
        // Jangan mengambil sumber PTKP dari kolom lain agar Status_Pernikahan
        // menjadi satu-satunya sumber PTKP pada import ini.
        return $this->normalizePtkpStrict($candidate);
    }

    /*

     * TER perusahaan: hanya A, B, atau C.

     * Tidak dihitung otomatis dari PTKP/usai; mengikuti nilai sumber Excel.

     */

    private function normalizeTerCategory(mixed $value): ?string

    {

        if ($value === null || trim((string) $value) === '') {

            return null;

        }



        $raw = strtoupper(trim((string) $value));

        $raw = preg_replace('/\\s+/', '', $raw) ?? $raw;



        if (preg_match('/(?:^|[^A-Z])(TER)?([ABC])$/', $raw, $match)) {

            return $match[2];

        }



        if (in_array($raw, ['A', 'B', 'C'], true)) {

            return $raw;

        }



        return null;

    }



    private function extractTerCategory(

        Collection|array $row,

        int $sequence

    ): ?string {

        $candidates = [];



        if ($sequence > 0) {

            $candidates[] = $this->pickContractSpecific($row, 'ter_category', $sequence);

            $candidates[] = $this->pickContractSpecific($row, 'ter', $sequence);

            $candidates[] = $this->pickContractSpecific($row, 'kategori_ter', $sequence);

        }



        $candidates[] = $this->value($row, [

            'ter_category',

            'ter',

            'kategori_ter',

            'ter_pajak',

            'kategori_ter_pajak',

        ]);



        foreach ($candidates as $candidate) {

            $normalized = $this->normalizeTerCategory($candidate);



            if ($normalized !== null) {

                return $normalized;

            }

        }



        return null;

    }



    private function normalizeEmploymentType(mixed $value): string

    {

        $raw = strtoupper(trim((string) ($value ?? '')));



        if ($raw === '') {

            return 'PKWT';

        }



        return match (true) {

            str_contains($raw, 'TETAP'),

            str_contains($raw, 'PKWTT') => 'PKWTT',

            str_contains($raw, 'PROBATION') => 'Probation',

            str_contains($raw, 'INTERNSHIP'),

            str_contains($raw, 'MAGANG') => 'Internship',

            str_contains($raw, 'PHK') => 'PHK',

            str_contains($raw, 'RESIGN') => 'Resign',

            str_contains($raw, 'PENSIUN') => 'Pensiun',

            str_contains($raw, 'END_CONTRACT'),

            str_contains($raw, 'END CONTRACT') => 'End_Contract',

            str_contains($raw, 'KONTRAK'),

            str_contains($raw, 'PKWT') => 'PKWT',

            default => 'PKWT',

        };

    }



    /*

     * Map Status Karyawan Excel ke format internal database.

     *

     * Tetap      -> PKWTT / null

     * Kontrak I  -> PKWT / 1

     * ...

     * Kontrak VII-> PKWT / 7

     *

     * Nilai lain tetap didukung sebagai compatibility legacy.

     */

    private function parseExcelEmploymentStatus(mixed $value): array

    {

        $raw = strtoupper(trim((string) ($value ?? '')));



        if ($raw === '' || $raw === '0') {

            return [

                'employment_type' => null,

                'pkwt_sequence' => null,

                'label' => null,

            ];

        }



        if (str_contains($raw, 'TETAP') || str_contains($raw, 'PKWTT')) {

            return [

                'employment_type' => 'PKWTT',

                'pkwt_sequence' => null,

                'label' => 'Tetap',

            ];

        }



        $romanToSequence = [

            'VII' => 7,

            'VI' => 6,

            'V' => 5,

            'IV' => 4,

            'III' => 3,

            'II' => 2,

            'I' => 1,

        ];



        foreach ($romanToSequence as $roman => $sequence) {

            if (preg_match('/\bKONTRAK\s*' . $roman . '\b/', $raw)) {

                return [

                    'employment_type' => 'PKWT',

                    'pkwt_sequence' => $sequence,

                    'label' => 'Kontrak ' . $roman,

                ];

            }

        }



        return [

            'employment_type' => $this->normalizeEmploymentType($value),

            'pkwt_sequence' => null,

            'label' => $this->cleanText($value),

        ];

    }



    private function pickContractSpecific(

        Collection|array $row,

        string $base,

        int $sequence,

        mixed $default = null

    ): mixed {

        $roman = self::ROMAN[$sequence] ?? (string) $sequence;



        $keys = [

            $base . '_' . $roman,

            $base . '_' . $sequence,

            $base . $roman,

            $base . $sequence,

            $base,

        ];



        return $this->value($row, $keys, $default);

    }



    private function getContractRows(Collection|array $row): array

    {

        $contracts = [];



        foreach (self::CONTRACT_COLUMNS as $sequence => $keys) {

            $rawCell = $this->value($row, $keys);

            $range = $this->parseContractRange($rawCell);



            if (!$range) {

                continue;

            }



            $contracts[] = [

                'sequence' => $sequence,

                'raw' => $rawCell,

                ...$range,

            ];

        }



        // Extra-robust fallback: inspect normalized headings dynamically.

        if ($contracts === [] && $row instanceof Collection) {

            foreach ($row->keys() as $key) {

                $normalizedKey = strtolower(trim((string) $key));

                $sequence = null;



                if (preg_match('/(?:kontrak|contract)_?(i{1,3}|iv|v|vi|vii)$/i', $normalizedKey, $m)) {

                    $roman = strtolower($m[1]);

                    $sequence = array_search($roman, self::ROMAN, true);

                    if ($sequence === false) {

                        $sequence = null;

                    }

                } elseif (preg_match('/(?:kontrak|contract)_?(\\d)$/i', $normalizedKey, $m)) {

                    $sequence = (int) $m[1];

                }



                if (!$sequence || $sequence < 1 || $sequence > 7) {

                    continue;

                }



                $range = $this->parseContractRange($row->get($key));

                if (!$range) {

                    continue;

                }



                $contracts[] = [

                    'sequence' => $sequence,

                    'raw' => $row->get($key),

                    ...$range,

                ];

            }

        }



        // Old/simple format fallback.

        if ($contracts === []) {

            $startDate = $this->parseDate(

                $this->value($row, ['start_date', 'tanggal_mulai']),

                false

            );



            if ($startDate) {

                $contracts[] = [

                    'sequence' => 1,

                    'raw' => null,

                    'start_date' => $startDate,

                    'end_date' => $this->parseDate(

                        $this->value($row, ['end_date', 'tanggal_berakhir']),

                        false

                    ),

                ];

            }

        }



        usort(

            $contracts,

            static fn (array $a, array $b) => [$a['start_date'], $a['sequence']] <=> [$b['start_date'], $b['sequence']]

        );



        return $contracts;

    }



    private function selectCurrentHistory(array $histories): ?ContractHistoryLocal

    {

        if ($histories === []) {

            return null;

        }



        $today = Carbon::today();



        $current = collect($histories)

            ->filter(function (ContractHistoryLocal $history) use ($today) {

                $start = $history->start_date ? Carbon::parse($history->start_date) : null;

                $end = $history->end_date ? Carbon::parse($history->end_date) : null;



                return $start

                    && $start->lte($today)

                    && (!$end || $today->lt($end));

            })

            ->sortByDesc(fn (ContractHistoryLocal $history) => $history->start_date?->format('Y-m-d'))

            ->first();



        if ($current) {

            return $current;

        }



        $latestStarted = collect($histories)

            ->filter(function (ContractHistoryLocal $history) use ($today) {

                return $history->start_date

                    && Carbon::parse($history->start_date)->lte($today);

            })

            ->sortByDesc(fn (ContractHistoryLocal $history) => $history->start_date?->format('Y-m-d'))

            ->first();



        if ($latestStarted) {

            return $latestStarted;

        }



        return collect($histories)

            ->sortBy(fn (ContractHistoryLocal $history) => $history->start_date?->format('Y-m-d'))

            ->first();

    }



    public function collection(Collection $rows): void

    {
        if ($rows->count() > 5000) {
            throw new \RuntimeException('Import dibatasi maksimal 5.000 baris per file.');
        }

        foreach ($rows as $rowIndex => $row) {

            $nikKtp = $this->cleanNumber(

                $this->value($row, ['nik_ktp', 'nik_ktp_karyawan', 'no_nik_ktp', 'nik_karyawan_ktp'])

            );



            /*
             * Jika NIK KTP kosong atau tidak valid, lewati baris tersebut.
             * Baris lain tetap diproses dan tidak boleh ikut gagal hanya
             * karena ada satu baris Excel yang kosong.
             */
            if (!$nikKtp || !preg_match('/^\d{10,20}$/', $nikKtp)) {
                continue;
            }



            DB::transaction(function () use ($row, $nikKtp): void {
                // PTKP hanya dibaca dari kolom Excel Status_Pernikahan.
                // Nilainya hanya disimpan ke contract history sebagai ptkp_status.
                // employees.marital_status TIDAK disentuh oleh importer ini.
                $ptkpStatus = $this->extractPtkp($row);


                $employeePayload = [

                    'nik_ktp' => $nikKtp,

                    'full_name' => $this->cleanText(

                        $this->value($row, ['full_name', 'nama_lengkap', 'nama']),

                        'Tanpa Nama'

                    ),

                    'gender' => $this->normalizeGender(

                        $this->value($row, ['gender', 'jenis_kelamin'])

                    ),

                    'religion' => $this->cleanText(

                        $this->value($row, ['religion', 'agama'])

                    ),

                    'birth_place' => $this->cleanText(

                        $this->value($row, ['birth_place', 'tempat_lahir']),

                        '-'

                    ),

                    'birth_date' => $this->parseDate(

                        $this->value($row, ['birth_date', 'tanggal_lahir']),

                        true

                    ),


                    'phone_number' => $this->cleanNumber(

                        $this->value($row, ['phone_number', 'no_hp', 'no_hp_whatsapp', 'whatsapp'])

                    ),

                    'email' => $this->cleanText(

                        $this->value($row, ['email', 'alamat_email'])

                    ),

                    'address_ktp' => $this->cleanText(

                        $this->value($row, ['address_ktp', 'alamat_ktp', 'alamat']),

                        '-'

                    ),

                    'address_domicile' => $this->cleanText(

                        $this->value($row, ['address_domicile', 'alamat_domisili'])

                    ),

                    'province_code' => $this->cleanNumber(

                        $this->value($row, ['province_code', 'kode_provinsi'])

                    ),

                    'city_code' => $this->cleanNumber(

                        $this->value($row, ['city_code', 'kode_kota'])

                    ),

                    'district_code' => $this->cleanNumber(

                        $this->value($row, ['district_code', 'kode_kecamatan'])

                    ),

                    'village_code' => $this->cleanNumber(

                        $this->value($row, ['village_code', 'kode_kelurahan'])

                    ),

                    'npwp_number' => $this->cleanNumber(

                        $this->value($row, ['npwp_number', 'npwp'])

                    ),

                    'bank_name' => $this->cleanText(

                        $this->value($row, ['bank_name', 'nama_bank'])

                    ),

                    'bank_account_number' => $this->cleanNumber(

                        $this->value($row, [

                            'bank_account_number',

                            'no_rekening',

                            'rekening_permata',

                            'rekening',

                        ])

                    ),

                    'bank_account_holder' => $this->cleanText(

                        $this->value($row, ['bank_account_holder', 'pemilik_rekening'])

                    ),

                ];



                if (

                    !$employeePayload['bank_name'] &&

                    $employeePayload['bank_account_number'] &&

                    $this->value($row, ['rekening_permata']) !== null

                ) {

                    $employeePayload['bank_name'] = 'PERMATA';

                }



                $employee = Employee::where('nik_ktp', $nikKtp)->first();



                if (!$employee) {

                    $employee = Employee::create([

                        'uuid' => (string) Str::uuid(),

                        'no_kk' => $this->cleanNumber(

                            $this->value($row, ['no_kk', 'nomor_kk'])

                        ),

                        ...$employeePayload,

                        'is_active' => true,

                    ]);

                } else {

                    $updateEmployee = array_filter(

                        $employeePayload,

                        static fn ($value) => $value !== null && $value !== ''

                    );



                    if ($updateEmployee !== []) {

                        $employee->update($updateEmployee);

                    }

                }



                $contract = ContractLocal::firstOrCreate(

                    ['employee_id' => $employee->id_employee],

                    [

                        'uuid' => (string) Str::uuid(),

                        'is_active' => true,

                    ]

                );



                $statusKaryawanValue = $this->value(

                    $row,

                    ['status_karyawan', 'status_hubungan_kerja']

                );



                $excelEmployment = $this->parseExcelEmploymentStatus(

                    $statusKaryawanValue

                );



                $contractRows = $this->getContractRows($row);



                // Karyawan "Tetap" pada workbook dapat tidak memiliki

                // Kontrak I-VII. Tetap buat satu history PKWTT agar

                // employment_type tidak hilang dari sistem.

                if (

                    $contractRows === []

                    &&

                    $excelEmployment['employment_type'] === 'PKWTT'

                ) {

                    $permanentStartDate = $this->parseDate(

                        $this->value($row, [

                            'masa_kerja_dihitung_mulai',

                            'masa_kerja',

                            'tanggal_mulai_kerja',

                            'start_date',

                        ]),

                        false

                    );



                    if ($permanentStartDate) {

                        $contractRows = [[

                            'sequence' => 0,

                            'raw' => null,

                            'start_date' => $permanentStartDate,

                            'end_date' => null,

                        ]];

                    }

                }



                /*
                 * Jika kolom Kontrak I-VII kosong/tidak terbaca, jangan membatalkan
                 * pembuatan data karyawan. Tetap buat 1 history dasar menggunakan
                 * tanggal U (Masa Kerja dihitung mulai). Ini memastikan employee
                 * tetap memiliki contract/history dan dapat tampil di index.
                 */
                if ($contractRows === []) {
                    $fallbackStartDate = $this->parseDate(
                        $this->value($row, [
                            'masa_kerja_dihitung_mulai',
                            'masa_kerja',
                            'tanggal_mulai_kerja',
                            'start_date',
                        ]),
                        false
                    );

                    if (!$fallbackStartDate) {
                        $fallbackStartDate = now()->format('Y-m-d');
                    }

                    $fallbackSequence = null;

                    if ($excelEmployment['employment_type'] === 'PKWT') {
                        $fallbackSequence = $excelEmployment['pkwt_sequence'] ?? 1;
                    }

                    $contractRows = [[
                        'sequence' => $fallbackSequence ?? 0,
                        'raw' => null,
                        'start_date' => $fallbackStartDate,
                        'end_date' => null,
                    ]];
                }



                $historyModels = [];



                $currentFingerprint = $this->cleanNumber(

                    $this->value($row, ['nik', 'nik_fingerprint', 'nik_mesin', 'nik_fingerprint_id', 'nik_pin', 'nikpin'])

                );



                $currentPin = $this->cleanNumber(

                    $this->value($row, ['pin', 'fingerprint_pin', 'pin_fingerprint', 'no_pin'])

                );



                foreach ($contractRows as $contractRow) {

                    $sequence = (int) $contractRow['sequence'];

                    $isPermanentHistory = $sequence === 0;



                    $jobTitle = $this->cleanText(

                        $isPermanentHistory

                            ? $this->value($row, ['jabatan'])

                            : $this->pickContractSpecific($row, 'job_title', $sequence)

                                ?? $this->value($row, ['jabatan']),

                        'Staff'

                    );



                    $department = $this->cleanText(

                        $isPermanentHistory

                            ? $this->value($row, ['divisi'])

                            : $this->pickContractSpecific($row, 'department', $sequence)

                                ?? $this->value($row, ['divisi']),

                        '-'

                    );



                    $placementArea = $this->cleanText(

                        $isPermanentHistory

                            ? $this->value($row, ['area_penempatan'])

                            : $this->pickContractSpecific($row, 'placement_area', $sequence)

                                ?? $this->value($row, ['area_penempatan']),

                        '-'

                    );



                    $category = $this->cleanText(

                        $isPermanentHistory

                            ? $this->value($row, ['kategori'])

                            : $this->pickContractSpecific($row, 'category', $sequence)

                                ?? $this->value($row, ['kategori'])

                    );



                    $levelValue = $isPermanentHistory

                        ? $this->value($row, ['level'])

                        : $this->pickContractSpecific($row, 'level', $sequence);

                    $level = is_numeric($levelValue) ? (int) $levelValue : null;



                    $basicSalary = $this->parseMoney(

                        $isPermanentHistory

                            ? $this->value($row, ['gaji_pokok'])

                            : $this->pickContractSpecific(

                                $row,

                                'basic_salary',

                                $sequence,

                                $this->value($row, ['gaji_pokok'])

                            )

                    );



                    $allowance = $this->parseMoney(

                        $isPermanentHistory

                            ? $this->value($row, ['tunjangan_tetap', 'tunjangan'])

                            : $this->pickContractSpecific(

                                $row,

                                'allowance',

                                $sequence,

                                $this->value($row, ['tunjangan_tetap', 'tunjangan'])

                            )

                    );



                    $contractPtkp = $ptkpStatus;



                    $terCategory = $this->extractTerCategory(

                        $row,

                        $sequence

                    );



                    // Status Karyawan Excel:

                    // - Tetap tanpa kolom Kontrak I-VII => PKWTT.

                    // - Setiap Kontrak I-VII => PKWT dengan sequence sesuai kolom.

                    if ($isPermanentHistory) {

                        $employmentType = 'PKWTT';

                        $historySequence = null;

                    } else {

                        $employmentType = 'PKWT';

                        $historySequence = $sequence;

                    }



                    $historyData = [

                        'nik_fingerprint' => $this->pickContractSpecific(

                            $row,

                            'nik_fingerprint',

                            $sequence,

                            $currentFingerprint

                        ),

                        'fingerprint_pin' => $this->pickContractSpecific(

                            $row,

                            'fingerprint_pin',

                            $sequence,

                            $currentPin

                        ),

                        'job_title' => $jobTitle,

                        'department' => $department,

                        'placement_area' => $placementArea,

                        'category' => $category,

                        'level' => $level,

                        'basic_salary' => round($basicSalary, 2),

                        'allowance' => round($allowance, 2),

                        'is_bpjstk_active' => $this->booleanValue(

                            $this->pickContractSpecific(

                                $row,

                                'is_bpjstk_active',

                                $sequence,

                                $this->value($row, ['bpjs_tk', 'bpjs_tk_active'], true)

                            ),

                            true

                        ),

                        'is_bpjs_health_active' => $this->booleanValue(

                            $this->pickContractSpecific(

                                $row,

                                'is_bpjs_health_active',

                                $sequence,

                                $this->value($row, ['bpjs_kes', 'bpjs_health_active'], true)

                            ),

                            true

                        ),

                        'ptkp_status' => $contractPtkp,


                        'use_manual_bpjs' => false,

                        'manual_bpjs_tk_employee' => 0,

                        'manual_bpjs_ks_employee' => 0,

                        'manual_bpjs_company' => 0,

                        'employment_type' => $employmentType,

                        'pkwt_sequence' => $employmentType === 'PKWT' ? $historySequence : null,

                        'start_date' => $contractRow['start_date'],

                        'end_date' => $contractRow['end_date'],

                        'exit_date' => null,

                        'exit_reason' => null,

                    ];



                    $history = $contract->histories()

                        ->where('pkwt_sequence', $historyData['pkwt_sequence'])

                        ->whereDate('start_date', $historyData['start_date'])

                        ->first();



                    if (!$history && $employmentType !== 'PKWT') {

                        $history = $contract->histories()

                            ->whereNull('pkwt_sequence')

                            ->whereDate('start_date', $historyData['start_date'])

                            ->first();

                    }



                    if (!$history) {

                        $history = $contract->histories()->create([

                            'uuid' => (string) Str::uuid(),

                            ...$historyData,

                            'is_active' => false,

                        ]);

                    } else {

                        $history->update($historyData);

                    }



                    $historyModels[] = $history->fresh();

                }



                $currentHistory = $this->selectCurrentHistory($historyModels);



                if (!$currentHistory) {

                    return;

                }



                $contract->histories()

                    ->whereKey(array_map(

                        static fn (ContractHistoryLocal $history) => $history->id_contract_history,

                        $historyModels

                    ))

                    ->update(['is_active' => false]);



                $currentHistory->update(['is_active' => true]);



                $statusKaryawan = strtoupper(trim((string) $this->value(

                    $row,

                    ['status_karyawan', 'status_hubungan_kerja']

                )));



                $employeeActive = !(

                    str_contains($statusKaryawan, 'PHK') ||

                    str_contains($statusKaryawan, 'RESIGN') ||

                    str_contains($statusKaryawan, 'PENSIUN')

                );



                $contract->update([

                    'current_contract_history_id' => $currentHistory->id_contract_history,

                    'is_active' => $employeeActive,

                ]);



                $employee->update([

                    'is_active' => $employeeActive,

                ]);

                if ($employee->wasRecentlyCreated) {
                    $this->importedCount++;
                } else {
                    $this->updatedCount++;
                }

            });

        }

        if (($this->importedCount + $this->updatedCount) === 0) {
            throw new \RuntimeException(
                'Tidak ada data karyawan yang berhasil diproses. Pastikan header Excel menggunakan NIK KTP pada kolom J.'
            );
        }

    }



    private function extractPtkpFromContract(

        Collection|array $row,

        int $sequence,

        string $fallback

    ): string {

        if ($sequence > 0) {

            $candidate = $this->pickContractSpecific($row, 'ptkp_status', $sequence);



            if ($candidate === null) {

                $candidate = $this->pickContractSpecific($row, 'ptkp', $sequence);

            }



            if ($candidate !== null) {

                $collapsed = preg_replace(

                    '/\\s+/',

                    '',

                    strtoupper(trim((string) $candidate))

                ) ?? '';

                $collapsed = str_replace(['/', '-', '_'], '', $collapsed);



                if (preg_match('/^(TK|K)(0|[1-4])$/', $collapsed)) {

                    return $this->normalizePtkp($collapsed);

                }

            }

        }



        return $this->normalizePtkp($fallback);

    }



    private function booleanValue(mixed $value, bool $default): bool

    {

        if ($value === null || trim((string) $value) === '') {

            return $default;

        }



        if (is_bool($value)) {

            return $value;

        }



        $raw = strtoupper(trim((string) $value));



        return match ($raw) {

            '1', 'TRUE', 'YES', 'YA', 'Y', 'AKTIF', 'ACTIVE' => true,

            '0', 'FALSE', 'NO', 'TIDAK', 'N', 'NONAKTIF', 'INACTIVE' => false,

            default => $default,

        };

    }

}
