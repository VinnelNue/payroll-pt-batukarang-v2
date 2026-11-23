<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for employee contract effective dates.
 * start_date, end_date and exit_date are inclusive dates.
 * A NULL end_date means the history remains open until a later period closes it.
 */
final class ContractHistoryResolver
{
    public static function matchingDate(iterable $histories, CarbonInterface|string $date): Collection
    {
        $target = self::date($date);

        return collect($histories)
            ->filter(static function ($history) use ($target): bool {
                $start = self::nullableDate($history->start_date ?? null);
                $end = self::effectiveEnd($history);

                return $start !== null
                    && $start->lte($target)
                    && ($end === null || $end->gte($target));
            })
            ->values();
    }

    /** Returns a history only when exactly one row covers the requested date. */
    public static function uniqueForDate(iterable $histories, CarbonInterface|string $date): mixed
    {
        $matches = self::matchingDate($histories, $date);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Latest record that starts on/before a date; useful as a form baseline, never as current. */
    public static function latestStartedOnOrBefore(iterable $histories, CarbonInterface|string $date): mixed
    {
        $target = self::date($date);

        return collect($histories)
            ->filter(static function ($history) use ($target): bool {
                $start = self::nullableDate($history->start_date ?? null);
                return $start !== null && $start->lte($target);
            })
            ->sortByDesc(static fn ($history) => (string) ($history->start_date ?? ''))
            ->sortByDesc(static fn ($history) => (int) ($history->id_contract_history ?? 0))
            ->first();
    }

    public static function latest(iterable $histories): mixed
    {
        return collect($histories)
            ->filter(static fn ($history) => !empty($history->start_date))
            ->sortByDesc(static fn ($history) => (string) ($history->start_date ?? ''))
            ->sortByDesc(static fn ($history) => (int) ($history->id_contract_history ?? 0))
            ->first();
    }

    /**
     * A display/grouping record for the month. Prefer the record effective on
     * day one; otherwise show the earliest record beginning in the month.
     * It is not a salary-calculation decision when compensation changes mid-month.
     */
    public static function forPeriod(iterable $histories, CarbonInterface|string $period): mixed
    {
        $periodStart = self::periodStart($period);
        $periodEnd = $periodStart->copy()->endOfMonth();

        $atStart = self::matchingDate($histories, $periodStart);
        if ($atStart->count() === 1) {
            return $atStart->first();
        }
        if ($atStart->count() > 1) {
            return null;
        }

        return self::intersectingPeriod($histories, $periodStart, $periodEnd)->first();
    }

    /**
     * Resolve one salary basis for payroll's one-row-per-month schema.
     * Several sequential histories are allowed only when basic_salary and
     * allowance are identical. If they differ, the caller must stop and ask
     * for an explicit mid-month payroll rule rather than silently choosing.
     *
     * @return array{status:string,history:mixed,histories:Collection,compensation_change:bool,overlap:bool}
     */
    public static function forPayrollPeriod(iterable $histories, CarbonInterface|string $period): array
    {
        $periodStart = self::periodStart($period);
        $periodEnd = $periodStart->copy()->endOfMonth();
        $allHistories = collect($histories)->values();
        $items = self::intersectingPeriod($allHistories, $periodStart, $periodEnd);

        if (self::hasGapInPeriod($allHistories, $periodStart, $periodEnd)) {
            return [
                'status' => 'gap',
                'history' => null,
                'histories' => $items,
                'compensation_change' => false,
                'overlap' => false,
            ];
        }

        if ($items->isEmpty()) {
            return [
                'status' => 'no_history',
                'history' => null,
                'histories' => $items,
                'compensation_change' => false,
                'overlap' => false,
            ];
        }

        $overlap = self::hasOverlappingPeriods($items);
        if ($overlap) {
            return [
                'status' => 'overlap',
                'history' => null,
                'histories' => $items,
                'compensation_change' => false,
                'overlap' => true,
            ];
        }

        $compensationSignatures = $items
            ->map(static fn ($history) => implode('|', [
                self::moneySignature($history->basic_salary ?? 0),
                self::moneySignature($history->allowance ?? 0),
                (int) ($history->is_bpjstk_active ?? 0),
                (int) ($history->is_bpjs_health_active ?? 0),
                (int) ($history->use_manual_bpjs ?? 0),
                self::moneySignature($history->manual_bpjs_tk_employee ?? 0),
                self::moneySignature($history->manual_bpjs_ks_employee ?? 0),
                self::moneySignature($history->manual_bpjs_company ?? 0),
                strtoupper(trim((string) ($history->ptkp_status ?? ''))),
                strtoupper(trim((string) ($history->ter_category ?? ''))),
            ]))
            ->unique()
            ->values();

        if ($compensationSignatures->count() > 1) {
            return [
                'status' => 'compensation_change',
                'history' => null,
                'histories' => $items,
                'compensation_change' => true,
                'overlap' => false,
            ];
        }

        // If one record covers the first day, prefer it; otherwise use the
        // earliest history intersecting the month (e.g. a mid-month hire).
        $atStart = self::matchingDate($items, $periodStart);
        $selected = $atStart->count() === 1 ? $atStart->first() : $items->first();

        return [
            'status' => 'ok',
            'history' => $selected,
            'histories' => $items,
            'compensation_change' => false,
            'overlap' => false,
        ];
    }

    /** True when a break between two consecutive histories intersects the month. */
    public static function hasGapInPeriod(iterable $histories, CarbonInterface|string $period): bool
    {
        $periodStart = self::periodStart($period);
        $periodEnd = $periodStart->copy()->endOfMonth();

        return self::hasGapInPeriodDates($histories, $periodStart, $periodEnd);
    }

    private static function hasGapInPeriodDates(iterable $histories, Carbon $periodStart, Carbon $periodEnd): bool
    {
        $items = collect($histories)
            ->filter(static fn ($history) => !empty($history->start_date))
            ->sortBy(static fn ($history) => (string) $history->start_date)
            ->values();

        for ($index = 0; $index < $items->count() - 1; $index++) {
            $previousEnd = self::effectiveEnd($items[$index]);
            $nextStart = self::nullableDate($items[$index + 1]->start_date ?? null);

            if (!$previousEnd || !$nextStart) {
                continue;
            }

            $gapStart = $previousEnd->copy()->addDay();
            $gapEnd = $nextStart->copy()->subDay();

            if ($gapStart->lte($gapEnd)
                && $gapStart->lte($periodEnd)
                && $gapEnd->gte($periodStart)) {
                return true;
            }
        }

        return false;
    }

    public static function hasOverlappingPeriods(iterable $histories): bool
    {
        $items = collect($histories)
            ->filter(static fn ($history) => !empty($history->start_date))
            ->sortBy(static fn ($history) => (string) $history->start_date)
            ->values();

        for ($i = 0; $i < $items->count(); $i++) {
            $leftStart = self::nullableDate($items[$i]->start_date ?? null);
            $leftEnd = self::effectiveEnd($items[$i]);

            for ($j = $i + 1; $j < $items->count(); $j++) {
                $rightStart = self::nullableDate($items[$j]->start_date ?? null);
                $rightEnd = self::effectiveEnd($items[$j]);

                if (!$leftStart || !$rightStart) {
                    continue;
                }

                $leftReachesRight = $leftEnd === null || $leftEnd->gte($rightStart);
                $rightReachesLeft = $rightEnd === null || $rightEnd->gte($leftStart);

                if ($leftReachesRight && $rightReachesLeft) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Returns: covered, before, after, gap, overlap or none. */
    public static function statusOnDate(iterable $histories, CarbonInterface|string $date): string
    {
        $target = self::date($date);
        $items = collect($histories)
            ->filter(static fn ($history) => !empty($history->start_date))
            ->sortBy(static fn ($history) => (string) $history->start_date)
            ->values();

        if ($items->isEmpty()) {
            return 'none';
        }

        $matches = self::matchingDate($items, $target);
        if ($matches->count() > 1) {
            return 'overlap';
        }
        if ($matches->count() === 1) {
            return 'covered';
        }

        $firstStart = self::nullableDate($items->first()->start_date ?? null);
        if ($firstStart && $target->lt($firstStart)) {
            return 'before';
        }

        for ($i = 0; $i < $items->count(); $i++) {
            $current = $items[$i];
            $start = self::nullableDate($current->start_date ?? null);
            $end = self::effectiveEnd($current);
            $next = $items[$i + 1] ?? null;
            $nextStart = $next ? self::nullableDate($next->start_date ?? null) : null;

            if ($end !== null && $target->gt($end)) {
                if ($nextStart && $target->lt($nextStart)) {
                    return 'gap';
                }
                if (!$nextStart) {
                    return 'after';
                }
            }

            // An open-ended interval before a later period would overlap that
            // later record; the overlap check above catches dates it covers.
            if ($start && $target->lt($start)) {
                return 'gap';
            }
        }

        $lastEnd = self::effectiveEnd($items->last());
        if ($lastEnd !== null && $target->gt($lastEnd)) {
            return 'after';
        }

        return 'gap';
    }

    /**
     * Inclusive end is the earlier of end_date and exit_date. For formal
     * termination history with no explicit end/exit date, start_date is the
     * effective last day so a PHK/Resign/Pensiun row cannot remain open forever.
     * For ordinary contracts, NULL still means open-ended.
     */
    public static function effectiveEnd(object $history): ?Carbon
    {
        $end = self::nullableDate($history->end_date ?? null);
        $exit = self::nullableDate($history->exit_date ?? null);

        if ($end && $exit) {
            return $end->lte($exit) ? $end : $exit;
        }

        if ($end || $exit) {
            return $end ?? $exit;
        }

        if (self::isTerminationType((string) ($history->employment_type ?? ''))) {
            return self::nullableDate($history->start_date ?? null);
        }

        return null;
    }

    public static function isTerminationType(string $employmentType): bool
    {
        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($employmentType)));

        return in_array($normalized, ['PHK', 'RESIGN', 'PENSIUN', 'END_CONTRACT'], true);
    }

    private static function intersectingPeriod(iterable $histories, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return collect($histories)
            ->filter(static function ($history) use ($periodStart, $periodEnd): bool {
                $start = self::nullableDate($history->start_date ?? null);
                $end = self::effectiveEnd($history);

                return $start !== null
                    && $start->lte($periodEnd)
                    && ($end === null || $end->gte($periodStart));
            })
            ->sortBy(static fn ($history) => (string) ($history->start_date ?? ''))
            ->values();
    }

    private static function moneySignature(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private static function periodStart(CarbonInterface|string $period): Carbon
    {
        if ($period instanceof CarbonInterface) {
            return Carbon::parse($period->format('Y-m-01'))->startOfDay();
        }

        $value = trim($period);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        return Carbon::parse($value)->startOfMonth();
    }

    private static function date(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay();
    }

    private static function nullableDate(mixed $date): ?Carbon
    {
        return $date ? Carbon::parse($date)->startOfDay() : null;
    }
}
