<?php

namespace App\Exports;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use RuntimeException;

class PayrollLocalViewSheet implements FromArray, WithEvents, WithTitle
{
    private readonly string $period;
    private readonly string $userRole;
    private readonly string $viewName;
    private readonly string $sheetTitle;
    private readonly bool $isCompact;

    private array $rows = [];
    private array $cellStyles = [];
    private array $mergeRanges = [];
    private array $rowHeights = [];
    private bool $built = false;
    private float $arrayBuildMs = 0.0;
    private ?int $sheetStartedAt = null;

    public function __construct(
        string $period,
        string $userRole,
        string $viewName,
        string $sheetTitle,
        bool $isCompact = false,
    ) {
        $this->period = trim($period);
        $this->userRole = strtolower(trim($userRole));
        $this->viewName = $viewName;
        $this->sheetTitle = $sheetTitle;
        $this->isCompact = $isCompact;
    }

    public function array(): array
    {
        if ($this->built) {
            return $this->rows;
        }

        $buildStartedAt = hrtime(true);
        $cacheKey = 'payroll-local-export-view-data:' . sha1(json_encode([
            $this->period,
            $this->userRole,
        ]));

        $prepareStartedAt = hrtime(true);
        $viewData = request()->attributes->get($cacheKey);
        $cacheHit = is_array($viewData);

        if (!$cacheHit) {
            $periodDate = Carbon::createFromFormat('Y-m-d', $this->period . '-01');
            $startDate = $periodDate->copy()->startOfMonth();
            $endDate = $periodDate->copy()->endOfMonth();

            $employees = Employee::query()
                ->with([
                    'contract.currentHistory',
                    'attendanceRecords' => function ($query) use ($startDate, $endDate): void {
                        $query->whereBetween('attendance_date', [
                            $startDate->format('Y-m-d'),
                            $endDate->format('Y-m-d'),
                        ])->orderBy('attendance_date');
                    },
                ])
                ->where('is_active', true)
                ->get();

            $employeeIds = $employees->pluck('id_employee')->filter()->values();

            $payrollsByEmployee = $employeeIds->isEmpty()
                ? collect()
                : Payroll::query()
                    ->where('period_month', $this->period)
                    ->whereIn('employee_id', $employeeIds)
                    ->get()
                    ->keyBy('employee_id');

            $holidays = Holiday::query()
                ->where('is_active', true)
                ->whereBetween('holiday_date', [
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d'),
                ])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(static fn ($holiday): string => Carbon::parse($holiday->holiday_date)->format('Y-m-d'));

            $cutoffDay = $payrollsByEmployee
                ->pluck('cutoff_day')
                ->filter(static fn ($value): bool => $value !== null)
                ->map(static fn ($value): int => (int) $value)
                ->filter(static fn (int $value): bool => $value >= 20 && $value <= 28)
                ->first();

            if ($cutoffDay === null) {
                $cutoffDay = (int) CompanySetting::get(
                    'attendance_cutoff_day:' . $this->period,
                    CompanySetting::get('attendance_cutoff_day', 26)
                );
            }

            $cutoffDay = min(28, max(20, (int) $cutoffDay));

            $viewData = [
                'employees' => $employees,
                'payrollsByEmployee' => $payrollsByEmployee,
                'holidays' => $holidays,
                'cutoffDay' => $cutoffDay,
                'period' => $this->period,
                'userRole' => $this->userRole,
            ];

            request()->attributes->set($cacheKey, $viewData);
        }

        Log::info('payroll.local.export.data_prepare', [
            'period' => $this->period,
            'sheet' => $this->sheetTitle,
            'cache_hit' => $cacheHit,
            'employee_count' => isset($viewData['employees']) ? $viewData['employees']->count() : 0,
            'attendance_count' => isset($viewData['employees'])
                ? $viewData['employees']->sum(static fn ($employee): int => $employee->attendanceRecords?->count() ?? 0)
                : 0,
            'prepare_ms' => round((hrtime(true) - $prepareStartedAt) / 1_000_000, 2),
        ]);

        $htmlStartedAt = hrtime(true);
        $html = view($this->viewName, $viewData)->render();
        $this->parseTable($html);
        unset($html, $viewData);

        $this->built = true;
        $this->arrayBuildMs = round((hrtime(true) - $htmlStartedAt) / 1_000_000, 2);

        Log::info('payroll.local.export.array_ready', [
            'period' => $this->period,
            'sheet' => $this->sheetTitle,
            'row_count' => count($this->rows),
            'column_count' => $this->rows === [] ? 0 : count($this->rows[0]),
            'cache_hit' => $cacheHit,
            'render_parse_ms' => $this->arrayBuildMs,
            'total_build_ms' => round((hrtime(true) - $buildStartedAt) / 1_000_000, 2),
        ]);

        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event): void {
                $this->sheetStartedAt = hrtime(true);
            },
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $this->applyTableMerges($sheet);
                $this->applyTableStyles($sheet);
                $this->applyRowHeights($sheet);

                $sheet->setShowGridlines(false);

                $isFinanceRole = in_array(
                    $this->userRole,
                    ['manager_keuangan', 'super_admin'],
                    true
                );
                $isHeadHrd = in_array(
                    $this->userRole,
                    ['head_hrd', 'kepala_hrd'],
                    true
                );

                $identityCount = $isFinanceRole ? 5 : 3;
                $firstAttendanceColumn = $identityCount + 1;
                $daysInMonth = Carbon::createFromFormat('Y-m-d', $this->period . '-01')->daysInMonth;
                $showBpjsColumns = $isFinanceRole || $isHeadHrd;
                $showFinancialColumns = $isFinanceRole;

                $sheet->freezePane(
                    Coordinate::stringFromColumnIndex($firstAttendanceColumn) . '6'
                );

                $sheet->getColumnDimension('A')->setWidth(4.5);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(40);

                if ($isFinanceRole) {
                    $sheet->getColumnDimension('D')->setWidth(8);
                    $sheet->getColumnDimension('E')->setWidth(6);
                }

                for ($i = 0; $i < $daysInMonth; $i++) {
                    $index = $firstAttendanceColumn + $i;
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($index)
                    )->setWidth(3.1);
                }

                $summaryWidths = $this->isCompact
                    ? [8, 10, 10, 8, 7, 9]
                    : [8, 10, 10, 8, 7, 7, 9, 9, 10, 9];

                $columnIndex = $firstAttendanceColumn + $daysInMonth;

                foreach ($summaryWidths as $width) {
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth($width);
                    $columnIndex++;
                }

                if ($showBpjsColumns) {
                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth(15);
                    $columnIndex++;

                    $sheet->getColumnDimension(
                        Coordinate::stringFromColumnIndex($columnIndex)
                    )->setWidth(15);
                    $columnIndex++;
                }

                if ($showFinancialColumns) {
                    foreach ([13, 13, 15, 13, 15, 14, 12, 14, 16] as $width) {
                        $sheet->getColumnDimension(
                            Coordinate::stringFromColumnIndex($columnIndex)
                        )->setWidth($width);
                        $columnIndex++;
                    }
                }

                if (!$this->isCompact) {
                    $internalIdColumn = Coordinate::stringFromColumnIndex($columnIndex);
                    $sheet->getColumnDimension($internalIdColumn)->setWidth(2);
                    $sheet->getColumnDimension($internalIdColumn)->setVisible(false);
                }

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setPaperSize(PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToPage(true);

                $sheet->getPageMargins()
                    ->setTop(0.25)
                    ->setRight(0.2)
                    ->setBottom(0.25)
                    ->setLeft(0.2);

                Log::info('payroll.local.export.sheet_complete', [
                    'period' => $this->period,
                    'sheet' => $this->sheetTitle,
                    'highest_row' => $sheet->getHighestRow(),
                    'highest_column' => $sheet->getHighestColumn(),
                    'render_parse_ms' => $this->arrayBuildMs,
                    'sheet_ms' => $this->sheetStartedAt !== null
                        ? round((hrtime(true) - $this->sheetStartedAt) / 1_000_000, 2)
                        : null,
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ]);
            },
        ];
    }

    private function parseTable(string $html): void
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new RuntimeException('PHP extension DOM diperlukan untuk memproses worksheet payroll.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET | LIBXML_COMPACT
            );

            if (!$loaded) {
                throw new RuntimeException('HTML worksheet payroll tidak dapat dibaca.');
            }

            $tables = $document->getElementsByTagName('table');
            if ($tables->length === 0) {
                throw new RuntimeException('Tabel export payroll tidak ditemukan.');
            }

            $table = $tables->item(0);
            $tableRows = $table->getElementsByTagName('tr');
            $grid = [];
            $occupiedUntil = [];
            $maxColumn = 0;
            $rowNumber = 0;

            foreach ($tableRows as $rowElement) {
                $rowNumber++;
                $columnNumber = 1;

                foreach ($rowElement->childNodes as $cell) {
                    if (!$cell instanceof \DOMElement) {
                        continue;
                    }

                    $tagName = strtolower($cell->tagName);
                    if (!in_array($tagName, ['td', 'th'], true)) {
                        continue;
                    }

                    $colspan = max(1, min(256, (int) $cell->getAttribute('colspan')));
                    $rowspan = max(1, min(1000, (int) $cell->getAttribute('rowspan')));

                    while ($this->rangeIsOccupied(
                        $occupiedUntil,
                        $columnNumber,
                        $colspan,
                        $rowNumber
                    )) {
                        $columnNumber++;
                    }

                    $text = $this->readCellText($cell);
                    $grid[$rowNumber][$columnNumber] = $this->normalizeCellValue($text);

                    $style = $this->cssToExcelStyle($cell->getAttribute('style'));
                    if (str_contains($text, "\n")) {
                        $style['alignment']['wrapText'] = true;
                    }
                    if ($style !== []) {
                        $this->cellStyles[$rowNumber][$columnNumber] = $style;
                    }

                    if ($colspan > 1 || $rowspan > 1) {
                        $lastColumn = $columnNumber + $colspan - 1;
                        $lastRow = $rowNumber + $rowspan - 1;

                        $this->mergeRanges[] = sprintf(
                            '%s%d:%s%d',
                            Coordinate::stringFromColumnIndex($columnNumber),
                            $rowNumber,
                            Coordinate::stringFromColumnIndex($lastColumn),
                            $lastRow
                        );

                        if ($rowspan > 1) {
                            for ($column = $columnNumber; $column <= $lastColumn; $column++) {
                                $occupiedUntil[$column] = $lastRow;
                            }
                        }
                    }

                    $maxColumn = max($maxColumn, $columnNumber + $colspan - 1);
                    $columnNumber += $colspan;
                }

                $rowStyle = $this->parseCssDeclarations($rowElement->getAttribute('style'));
                if (isset($rowStyle['height']) && preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/i', trim($rowStyle['height']), $match)) {
                    $this->rowHeights[$rowNumber] = (float) $match[1] * 0.75;
                }
            }

            if ($rowNumber === 0 || $maxColumn === 0) {
                throw new RuntimeException('Tabel export payroll tidak memiliki baris atau kolom.');
            }

            for ($row = 1; $row <= $rowNumber; $row++) {
                $values = $grid[$row] ?? [];
                $normalized = [];
                for ($column = 1; $column <= $maxColumn; $column++) {
                    $normalized[] = $values[$column] ?? null;
                }
                $this->rows[] = $normalized;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private function rangeIsOccupied(array $occupiedUntil, int $startColumn, int $colspan, int $rowNumber): bool
    {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            if (($occupiedUntil[$column] ?? 0) >= $rowNumber) {
                return true;
            }
        }

        return false;
    }

    private function readCellText(\DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'br') {
                $text .= "\n";
                continue;
            }

            if ($child->hasChildNodes()) {
                $text .= $this->readCellText($child);
                continue;
            }

            $text .= $child->nodeValue ?? '';
        }

        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[\t\r\f\v ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeCellValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return $value;
    }

    private function parseCssDeclarations(string $css): array
    {
        $declarations = [];

        foreach (explode(';', $css) as $declaration) {
            $separator = strpos($declaration, ':');
            if ($separator === false) {
                continue;
            }

            $property = strtolower(trim(substr($declaration, 0, $separator)));
            $value = trim(substr($declaration, $separator + 1));

            if ($property !== '' && $value !== '') {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    private function cssToExcelStyle(string $css): array
    {
        $declarations = $this->parseCssDeclarations($css);
        if ($declarations === []) {
            return [];
        }

        $style = [];
        $font = [];
        $alignment = [];
        $borders = [];

        if (isset($declarations['font-weight'])) {
            $weight = strtolower($declarations['font-weight']);
            $font['bold'] = $weight === 'bold' || (is_numeric($weight) && (int) $weight >= 600);
        }

        if (isset($declarations['font-size']) && preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/i', $declarations['font-size'], $match)) {
            $font['size'] = max(1, (float) $match[1] * 0.75);
        }

        if (isset($declarations['font-family'])) {
            $family = trim($declarations['font-family'], " \t\n\r\0\x0B\"'");
            if ($family !== '') {
                $font['name'] = str_contains(strtolower($family), 'georgia') ? 'Georgia' : 'Arial';
            }
        }

        if (isset($declarations['color'])) {
            $color = $this->normalizeCssColor($declarations['color']);
            if ($color !== null) {
                $font['color'] = ['rgb' => $color];
            }
        }

        $background = $declarations['background-color'] ?? $declarations['background'] ?? null;
        if ($background !== null) {
            $color = $this->normalizeCssColor($background);
            if ($color !== null) {
                $style['fill'] = [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $color],
                ];
            }
        }

        if (isset($declarations['text-align'])) {
            $horizontal = match (strtolower($declarations['text-align'])) {
                'center' => Alignment::HORIZONTAL_CENTER,
                'right' => Alignment::HORIZONTAL_RIGHT,
                'left' => Alignment::HORIZONTAL_LEFT,
                'justify' => Alignment::HORIZONTAL_JUSTIFY,
                default => null,
            };

            if ($horizontal !== null) {
                $alignment['horizontal'] = $horizontal;
            }
        }

        if (isset($declarations['vertical-align'])) {
            $vertical = match (strtolower($declarations['vertical-align'])) {
                'middle' => Alignment::VERTICAL_CENTER,
                'top' => Alignment::VERTICAL_TOP,
                'bottom' => Alignment::VERTICAL_BOTTOM,
                default => null,
            };

            if ($vertical !== null) {
                $alignment['vertical'] = $vertical;
            }
        }

        if (isset($declarations['white-space']) && strtolower($declarations['white-space']) === 'nowrap') {
            $alignment['wrapText'] = false;
        }

        if (isset($declarations['border'])) {
            $border = $this->parseCssBorder($declarations['border']);
            if ($border !== null) {
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $borders[$side] = $border;
                }
            }
        }

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $property = 'border-' . $side;
            if (!isset($declarations[$property])) {
                continue;
            }

            $border = $this->parseCssBorder($declarations[$property]);
            if ($border !== null) {
                $borders[$side] = $border;
            }
        }

        if ($font !== []) {
            $style['font'] = $font;
        }
        if ($alignment !== []) {
            $style['alignment'] = $alignment;
        }
        if ($borders !== []) {
            $style['borders'] = $borders;
        }

        return $style;
    }

    private function parseCssBorder(string $value): ?array
    {
        $value = strtolower(trim($value));

        if ($value === '0' || $value === 'none') {
            return ['borderStyle' => Border::BORDER_NONE];
        }

        if (!preg_match('/^(?:(\d+(?:\.\d+)?)px\s+)?(solid|dashed|dotted|double)\s+(#[0-9a-f]{3}|#[0-9a-f]{6})$/i', $value, $match)) {
            return null;
        }

        $width = isset($match[1]) ? (float) $match[1] : 1.0;
        $line = strtolower($match[2]);
        $style = match ($line) {
            'dashed' => Border::BORDER_DASHED,
            'dotted' => Border::BORDER_DOTTED,
            'double' => Border::BORDER_DOUBLE,
            default => $width >= 2 ? Border::BORDER_MEDIUM : Border::BORDER_THIN,
        };

        $color = $this->normalizeCssColor($match[3]);

        return [
            'borderStyle' => $style,
            'color' => ['rgb' => $color ?? 'FF000000'],
        ];
    }

    private function normalizeCssColor(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $match)) {
            $value = sprintf(
                '#%s%s%s%s%s%s',
                $match[1][0], $match[1][0],
                $match[1][1], $match[1][1],
                $match[1][2], $match[1][2]
            );
        }

        if (!preg_match('/^#([0-9a-f]{6})$/i', $value, $match)) {
            return null;
        }

        return 'FF' . strtoupper($match[1]);
    }

    private function applyTableMerges($sheet): void
    {
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
        }
    }

    private function applyTableStyles($sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow > 0 && $highestColumn !== '') {
            $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                'font' => [
                    'name' => 'Arial',
                    'size' => 6.75,
                    'color' => ['rgb' => 'FF000000'],
                ],
            ]);
        }

        $runs = [];
        $stylesBySignature = [];

        foreach ($this->cellStyles as $rowNumber => $columns) {
            ksort($columns);
            $current = null;
            $startColumn = null;
            $endColumn = null;

            $flush = function () use (&$runs, &$stylesBySignature, &$current, &$startColumn, &$endColumn, $rowNumber): void {
                if ($current === null || $startColumn === null || $endColumn === null) {
                    return;
                }

                $signature = hash('sha256', serialize($current));
                $key = $signature . ':' . $startColumn . ':' . $endColumn;
                $runs[$key][] = (int) $rowNumber;
                $stylesBySignature[$signature] = $current;
            };

            foreach ($columns as $columnNumber => $style) {
                $columnNumber = (int) $columnNumber;
                $signature = hash('sha256', serialize($style));

                if (
                    $current !== null
                    && $signature === hash('sha256', serialize($current))
                    && $columnNumber === $endColumn + 1
                ) {
                    $endColumn = $columnNumber;
                    continue;
                }

                $flush();
                $current = $style;
                $startColumn = $columnNumber;
                $endColumn = $columnNumber;
            }

            $flush();
        }

        foreach ($runs as $key => $rowNumbers) {
            [$signature, $startColumn, $endColumn] = explode(':', $key);
            sort($rowNumbers, SORT_NUMERIC);

            $groups = [];
            $groupStart = null;
            $previousRow = null;

            foreach ($rowNumbers as $rowNumber) {
                if ($groupStart === null) {
                    $groupStart = $rowNumber;
                } elseif ($rowNumber !== $previousRow + 1) {
                    $groups[] = [$groupStart, $previousRow];
                    $groupStart = $rowNumber;
                }
                $previousRow = $rowNumber;
            }

            if ($groupStart !== null) {
                $groups[] = [$groupStart, $previousRow];
            }

            $firstColumn = Coordinate::stringFromColumnIndex((int) $startColumn);
            $lastColumn = Coordinate::stringFromColumnIndex((int) $endColumn);
            $style = $stylesBySignature[$signature];

            foreach ($groups as [$firstRow, $lastRow]) {
                $range = $firstColumn . $firstRow;
                if ($firstColumn !== $lastColumn || $firstRow !== $lastRow) {
                    $range .= ':' . $lastColumn . $lastRow;
                }
                $sheet->getStyle($range)->applyFromArray($style);
            }
        }

        foreach ($this->mergeRanges as $range) {
            if (!preg_match('/^([A-Z]+)(\d+):/i', $range, $match)) {
                continue;
            }

            $columnNumber = Coordinate::columnIndexFromString(strtoupper($match[1]));
            $rowNumber = (int) $match[2];
            $style = $this->cellStyles[$rowNumber][$columnNumber] ?? null;

            if ($style !== null) {
                $sheet->getStyle($range)->applyFromArray($style);
            }
        }
    }

    private function applyRowHeights($sheet): void
    {
        foreach ($this->rowHeights as $rowNumber => $height) {
            $sheet->getRowDimension((int) $rowNumber)->setRowHeight($height);
        }
    }
}
