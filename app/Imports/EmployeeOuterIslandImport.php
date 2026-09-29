<?php

namespace App\Imports;

use App\Models\EmployeeOuterIsland;
use App\Models\ContractOuterIsland;
use App\Models\ContractHistoryOuterIsland;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeOuterIslandImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /**
     * Header Excel berada pada baris ke-3.
     */
    public function headingRow(): int
    {
        return 3;
    }

    /**
     * ============================================================
     * CLEAN NUMBER
     * ============================================================
     *
     * Untuk NIK / KK / PIN / rekening / NPWP.
     *
     * Menangani:
     * 1234567890123456
     * 1.234567890123456E15
     * string biasa
     */
    private function cleanNumber($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);

        if ($str === '') {
            return null;
        }

        /*
         * Scientific notation dari Excel.
         */
        if (
            is_numeric($value) &&
            str_contains(strtoupper($str), 'E')
        ) {
            return sprintf(
                '%.0f',
                (float) $value
            );
        }

        /*
         * Jika Excel membaca angka sebagai float
         * dan tidak memakai desimal yang bermakna.
         */
        if (
            is_numeric($value) &&
            !str_contains($str, '.')
        ) {
            return (string) (int) $value;
        }

        return $str;
    }

    /**
     * ============================================================
     * PARSE DATE
     * ============================================================
     */
    private function parseDate(
        $value,
        bool $defaultToday = false
    ): ?string {
        if ($value === null || $value === '') {
            return $defaultToday
                ? now()->format('Y-m-d')
                : null;
        }

        try {
            /*
             * Excel serial date.
             */
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date
                    ::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            }

            $timestamp = strtotime((string) $value);

            if ($timestamp === false) {
                return $defaultToday
                    ? now()->format('Y-m-d')
                    : null;
            }

            return date(
                'Y-m-d',
                $timestamp
            );

        } catch (\Throwable $e) {
            return $defaultToday
                ? now()->format('Y-m-d')
                : null;
        }
    }

    /**
     * ============================================================
     * PARSE BOOLEAN
     * ============================================================
     */
    private function parseBoolean(
        $value,
        bool $default = true
    ): bool {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(
            trim((string) $value)
        );

        if (
            in_array(
                $value,
                [
                    '1',
                    'true',
                    'yes',
                    'ya',
                    'aktif',
                    'active',
                    'on',
                ],
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $value,
                [
                    '0',
                    'false',
                    'no',
                    'tidak',
                    'nonaktif',
                    'inactive',
                    'off',
                ],
                true
            )
        ) {
            return false;
        }

        return $default;
    }

    /**
     * ============================================================
     * PARSE MONEY
     * ============================================================
     *
     * Support:
     *
     * 5000000
     * 5.000.000
     * Rp 5.000.000
     * 5,000,000
     * 5.000.000,00
     * 5000000.00
     */
    private function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        /*
         * Kalau memang numeric dari Excel,
         * jangan dimodifikasi.
         */
        if (
            is_int($value) ||
            is_float($value)
        ) {
            return round(
                (float) $value,
                2
            );
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return 0;
        }

        /*
         * Hilangkan prefix Rupiah.
         */
        $value = preg_replace(
            '/\s*(Rp|IDR)\s*/i',
            '',
            $value
        );

        $value = trim($value);

        /*
         * FORMAT INDONESIA
         *
         * 5.000.000
         * 5.000.000,50
         */
        if (
            str_contains($value, '.') &&
            str_contains($value, ',')
        ) {
            $value = str_replace(
                '.',
                '',
                $value
            );

            $value = str_replace(
                ',',
                '.',
                $value
            );
        }

        /*
         * FORMAT INDONESIA TANPA DESIMAL
         *
         * 5.000.000
         */
        elseif (
            substr_count($value, '.') >= 1 &&
            !str_contains($value, ',')
        ) {
            /*
             * Kalau bagian setelah titik hanya 1-2 digit,
             * anggap decimal.
             *
             * 5000000.50
             */
            if (
                preg_match(
                    '/^\d+\.\d{1,2}$/',
                    $value
                )
            ) {
                // biarkan sebagai decimal
            } else {
                $value = str_replace(
                    '.',
                    '',
                    $value
                );
            }
        }

        /*
         * FORMAT US
         *
         * 5,000,000
         */
        elseif (
            substr_count($value, ',') >= 1 &&
            !str_contains($value, '.')
        ) {
            /*
             * 5000000,50
             */
            if (
                preg_match(
                    '/^\d+,\d{1,2}$/',
                    $value
                )
            ) {
                $value = str_replace(
                    ',',
                    '.',
                    $value
                );
            } else {
                $value = str_replace(
                    ',',
                    '',
                    $value
                );
            }
        }

        /*
         * Buang karakter lain.
         */
        $value = preg_replace(
            '/[^0-9.\-]/',
            '',
            $value
        );

        if (
            $value === '' ||
            !is_numeric($value)
        ) {
            return 0;
        }

        return round(
            (float) $value,
            2
        );
    }

    /**
     * ============================================================
     * CALCULATE ALLOWANCE
     * ============================================================
     *
     * Rumus:
     *
     * basic_salary × level × 2%
     */
    private function calculateAllowance(
        float $basicSalary,
        ?int $level
    ): float {
        if (
            $basicSalary <= 0 ||
            !$level ||
            $level <= 0
        ) {
            return 0;
        }

        return round(
            $basicSalary *
            ($level * 0.02),
            2
        );
    }

    /**
     * ============================================================
     * TERMINATION
     * ============================================================
     */
    private function isTerminatedEmploymentType(
        string $employmentType
    ): bool {
        return in_array(
            $employmentType,
            [
                'PHK',
                'Resign',
                'Pensiun',
                'End_Contract',
            ],
            true
        );
    }

    /**
     * ============================================================
     * COLLECTION
     * ============================================================
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            /*
             * ========================================================
             * 1. NIK
             * ========================================================
             */

            $nikKtp = $this->cleanNumber(
                $row['nik']
                ?? $row['nik_ktp_outer']
                ?? $row['nik_ktp']
                ?? null
            );

            if (empty($nikKtp)) {
                continue;
            }

            /*
             * Jangan duplicate.
             */
            if (
                EmployeeOuterIsland::where(
                    'nik_ktp_outer',
                    $nikKtp
                )->exists()
            ) {
                continue;
            }

            /*
             * ========================================================
             * 2. IDENTITAS
             * ========================================================
             */

            $birthDate = $this->parseDate(
                $row['birth_date_outer']
                ?? $row['birth_date']
                ?? $row['tanggal_lahir']
                ?? null,
                true
            );

            $genderRaw = strtoupper(
                trim(
                    $row['gender_outer']
                    ?? $row['gender']
                    ?? $row['jenis_kelamin']
                    ?? 'L'
                )
            );

            $gender =
                str_starts_with(
                    $genderRaw,
                    'P'
                ) ||
                str_starts_with(
                    $genderRaw,
                    'F'
                )
                    ? 'P'
                    : 'L';

            /*
             * ========================================================
             * 3. PTKP
             * ========================================================
             */

            $statusKaryawanRaw = strtoupper(
                trim(
                    $row['status_karyawan']
                    ?? $row['ptkp_status']
                    ?? $row['marital_status']
                    ?? 'TK/0'
                )
            );

            if ($statusKaryawanRaw === '') {
                $statusKaryawanRaw = 'TK/0';
            }

            $maritalStatus =
                str_starts_with(
                    $statusKaryawanRaw,
                    'K'
                )
                    ? 'married'
                    : 'single';

            /*
             * ========================================================
             * 4. CONTRACT DATE
             * ========================================================
             */

            $startDate = $this->parseDate(
                $row['start_date']
                ?? $row['tanggal_mulai']
                ?? null,
                true
            );

            $endDate = $this->parseDate(
                $row['end_date']
                ?? $row['tanggal_berakhir']
                ?? null,
                false
            );

            /*
             * ========================================================
             * 5. LEVEL / CATEGORY
             * ========================================================
             */

            $levelRaw =
                $row['level']
                ?? null;

            $levelClean =
                $levelRaw !== null &&
                $levelRaw !== '' &&
                is_numeric($levelRaw)
                    ? (int) $levelRaw
                    : null;

            $categoryRaw =
                $row['kategori']
                ?? $row['category']
                ?? null;

            $categoryClean =
                $categoryRaw === null ||
                trim((string) $categoryRaw) === '' ||
                trim((string) $categoryRaw) === '-'
                    ? null
                    : trim((string) $categoryRaw);

            /*
             * ========================================================
             * 6. PEKERJAAN
             * ========================================================
             */

            $jobTitle = trim(
                (string) (
                    $row['jabatan']
                    ?? $row['job_title']
                    ?? 'Staff'
                )
            );

            $department = trim(
                (string) (
                    $row['divisi']
                    ?? $row['department']
                    ?? '-'
                )
            );

            $placement = trim(
                (string) (
                    $row['area']
                    ?? $row['placement_area']
                    ?? '-'
                )
            );

            $employmentType = trim(
                (string) (
                    $row['employment_type']
                    ?? $row['status_hubungan_kerja']
                    ?? 'PKWT'
                )
            );

            /*
             * Normalisasi employment type.
             */
            $employmentMap = [
                'pkwt' => 'PKWT',
                'pkwtt' => 'PKWTT',
                'probation' => 'Probation',
                'internship' => 'Internship',
                'phk' => 'PHK',
                'resign' => 'Resign',
                'pensiun' => 'Pensiun',
                'end_contract' => 'End_Contract',
                'end contract' => 'End_Contract',
            ];

            $employmentKey = strtolower(
                $employmentType
            );

            $employmentType =
                $employmentMap[$employmentKey]
                ?? 'PKWT';

            /*
             * ========================================================
             * 7. FINANCIAL
             * ========================================================
             */

            $basicSalary = $this->parseMoney(
                $row['basic_salary']
                ?? $row['gaji_pokok']
                ?? 0
            );

            /*
             * Allowance.
             *
             * Prioritas:
             * 1. Hitung berdasarkan sistem jika level tersedia.
             * 2. Kalau level kosong, gunakan allowance Excel.
             */
            $excelAllowance = $this->parseMoney(
                $row['allowance']
                ?? $row['tunjangan_tetap']
                ?? $row['tunjangan']
                ?? 0
            );

            $allowance =
                $levelClean !== null
                    ? $this->calculateAllowance(
                        $basicSalary,
                        $levelClean
                    )
                    : $excelAllowance;

            /*
             * ========================================================
             * 8. BPJS
             * ========================================================
             */

            $bpjsTk = $this->parseBoolean(
                $row['is_bpjstk_active']
                ?? $row['bpjs_tk']
                ?? $row['bpjs_ketenagakerjaan']
                ?? true,
                true
            );

            $bpjsHealth = $this->parseBoolean(
                $row['is_bpjs_health_active']
                ?? $row['bpjs_ks']
                ?? $row['bpjs_kesehatan']
                ?? true,
                true
            );

            /*
             * ========================================================
             * 9. TERMINATION
             * ========================================================
             */

            $isTerminated =
                $this->isTerminatedEmploymentType(
                    $employmentType
                );

            $exitDate = null;
            $exitReason = null;

            if ($isTerminated) {

                $exitDate =
                    $this->parseDate(
                        $row['exit_date']
                        ?? $row['tanggal_keluar']
                        ?? null,
                        true
                    );

                $exitReason =
                    trim(
                        (string) (
                            $row['exit_reason']
                            ?? $row['alasan_keluar']
                            ?? $employmentType
                        )
                    );
            }

            /*
             * ========================================================
             * 10. TRANSACTION
             * ========================================================
             */

            DB::transaction(
                function () use (
                    $row,
                    $nikKtp,
                    $birthDate,
                    $gender,
                    $maritalStatus,
                    $startDate,
                    $endDate,
                    $statusKaryawanRaw,
                    $levelClean,
                    $categoryClean,
                    $jobTitle,
                    $department,
                    $placement,
                    $employmentType,
                    $basicSalary,
                    $allowance,
                    $bpjsTk,
                    $bpjsHealth,
                    $isTerminated,
                    $exitDate,
                    $exitReason
                ) {

                    /*
                     * ==================================================
                     * EMPLOYEE MASTER
                     * ==================================================
                     */

                    $employee =
                        EmployeeOuterIsland::create([

                            'uuid' =>
                                (string) Str::uuid(),

                            'no_kk_outer' =>
                                $this->cleanNumber(
                                    $row['no_kk_outer']
                                    ?? $row['no_kk']
                                    ?? null
                                ),

                            'nik_ktp_outer' =>
                                $nikKtp,

                            'full_name_outer' =>
                                $row['nama']
                                ?? $row['full_name_outer']
                                ?? $row['full_name']
                                ?? $row['nama_lengkap']
                                ?? '',

                            'gender_outer' =>
                                $gender,

                            'birth_place_outer' =>
                                $row['birth_place_outer']
                                ?? $row['tempat_lahir']
                                ?? '-',

                            'birth_date_outer' =>
                                $birthDate,

                            'religion_outer' =>
                                $row['religion_outer']
                                ?? $row['agama']
                                ?? null,

                            'marital_status_outer' =>
                                $maritalStatus,

                            'phone_number_outer' =>
                                $this->cleanNumber(
                                    $row['phone_number_outer']
                                    ?? $row['no_hp']
                                    ?? null
                                ),

                            'email_outer' =>
                                $row['email_outer']
                                ?? $row['email']
                                ?? null,

                            'address_ktp_outer' =>
                                $row['alamat']
                                ?? $row['address_ktp_outer']
                                ?? $row['alamat_ktp']
                                ?? '-',

                            'address_domicile_outer' =>
                                $row['address_domicile_outer']
                                ?? $row['alamat_domisili']
                                ?? null,

                            'npwp_number_outer' =>
                                $this->cleanNumber(
                                    $row['npwp']
                                    ?? $row['npwp_number_outer']
                                    ?? null
                                ),

                            'bank_name_outer' =>
                                $row['bank_name_outer']
                                ?? $row['nama_bank']
                                ?? null,

                            'bank_account_number_outer' =>
                                $this->cleanNumber(
                                    $row['bank_account_number_outer']
                                    ?? $row['no_rekening']
                                    ?? null
                                ),

                            'bank_account_holder_outer' =>
                                $row['bank_account_holder_outer']
                                ?? $row['pemilik_rekening']
                                ?? null,

                            'ktp_path_outer' =>
                                null,

                            /*
                             * Employee tetap aktif kecuali
                             * memang termination.
                             */
                            'is_active' =>
                                !$isTerminated,
                        ]);

                    /*
                     * ==================================================
                     * CONTRACT MASTER
                     * ==================================================
                     */

                    $contract =
                        ContractOuterIsland::create([

                            'uuid' =>
                                (string) Str::uuid(),

                            'employee_outer_island_id' =>
                                $employee
                                    ->id_employee_outer_island,

                            'current_contract_history_id' =>
                                null,

                            'is_active' =>
                                !$isTerminated,
                        ]);

                    /*
                     * ==================================================
                     * CONTRACT HISTORY #1
                     * ==================================================
                     */

                    $history =
                        ContractHistoryOuterIsland::create([

                            'uuid' =>
                                (string) Str::uuid(),

                            'contract_outer_island_id' =>
                                $contract
                                    ->id_contract_outer_island,

                            /*
                             * Attendance machine.
                             */
                            'nik_fingerprint' =>
                                $this->cleanNumber(
                                    $row['nik_fingerprint']
                                    ?? $row['nik_mesin']
                                    ?? null
                                ),

                            'fingerprint_pin' =>
                                $this->cleanNumber(
                                    $row['fingerprint_pin']
                                    ?? $row['pin']
                                    ?? null
                                ),

                            /*
                             * Job.
                             */
                            'job_title' =>
                                $jobTitle,

                            'department' =>
                                $department,

                            'placement_area' =>
                                $placement,

                            /*
                             * Level.
                             */
                            'category' =>
                                $categoryClean,

                            'level' =>
                                $levelClean,

                            /*
                             * Financial.
                             */
                            'basic_salary' =>
                                $basicSalary,

                            'allowance' =>
                                $allowance,

                            /*
                             * BPJS.
                             */
                            'is_bpjstk_active' =>
                                $bpjsTk,

                            'is_bpjs_health_active' =>
                                $bpjsHealth,

                            'use_manual_bpjs' =>
                                false,

                            'manual_bpjs_tk_employee' =>
                                0,

                            'manual_bpjs_ks_employee' =>
                                0,

                            'manual_bpjs_company' =>
                                0,

                            /*
                             * PTKP.
                             */
                            'ptkp_status' =>
                                $statusKaryawanRaw,

                            /*
                             * Employment.
                             */
                            'employment_type' =>
                                $employmentType,

                            'pkwt_sequence' =>
                                strtoupper(
                                    $employmentType
                                ) === 'PKWT'
                                    ? 1
                                    : null,

                            /*
                             * Period.
                             */
                            'start_date' =>
                                $startDate,

                            'end_date' =>
                                $endDate,

                            /*
                             * Termination.
                             */
                            'exit_date' =>
                                $exitDate,

                            'exit_reason' =>
                                $exitReason,

                            /*
                             * Current history.
                             */
                            'is_active' =>
                                !$isTerminated,
                        ]);

                    /*
                     * ==================================================
                     * SET CURRENT HISTORY
                     * ==================================================
                     */

                    $contract->update([

                        'current_contract_history_id' =>
                            $history
                                ->id_contract_history_outer_island,

                        'is_active' =>
                            !$isTerminated,
                    ]);
                }
            );
        }
    }
}