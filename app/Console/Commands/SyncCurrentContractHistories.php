<?php

namespace App\Console\Commands;

use App\Models\ContractLocal;
use App\Support\ContractHistoryResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCurrentContractHistories extends Command
{
    protected $signature = 'contracts:sync-current-history {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Synchronize current contract pointers from contract history effective dates.';

    public function handle(): int
    {
        $today = Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $relinked = 0;
        $cleared = 0;
        $overlaps = 0;
        $deactivated = 0;

        ContractLocal::query()
            ->with(['employee', 'histories'])
            ->chunkById(200, function ($contracts) use (
                $today,
                $dryRun,
                &$relinked,
                &$cleared,
                &$overlaps,
                &$deactivated
            ): void {
                foreach ($contracts as $contract) {
                    $histories = $contract->histories;
                    $matches = ContractHistoryResolver::matchingDate($histories, $today);

                    if ($matches->count() > 1) {
                        $overlaps++;
                        if (!$dryRun) {
                            $contract->histories()->update(['is_active' => false]);
                            $contract->update(['current_contract_history_id' => null]);
                        }
                        continue;
                    }

                    if ($matches->count() === 1) {
                        $current = $matches->first();
                        $needsRelink = (int) $contract->current_contract_history_id
                            !== (int) $current->id_contract_history;
                        $wasActive = (bool) $contract->is_active;
                        $currentEffectiveEnd = ContractHistoryResolver::effectiveEnd($current);
                        $terminationEffective = ContractHistoryResolver::isTerminationType(
                            (string) $current->employment_type
                        ) && $currentEffectiveEnd !== null
                            && $currentEffectiveEnd->lt($today);

                        if (!$dryRun) {
                            $contract->histories()->update(['is_active' => false]);
                            if (!$terminationEffective) {
                                $current->update(['is_active' => true]);
                            }

                            $contractPayload = [
                                'current_contract_history_id' => $current->id_contract_history,
                            ];
                            if ($terminationEffective) {
                                $contractPayload['is_active'] = false;
                            }
                            $contract->update($contractPayload);

                            // A valid current history does not by itself reactivate
                            // a manually inactive employee/master. Only a verified
                            // termination may deactivate these master flags.
                            if ($terminationEffective) {
                                $contract->employee?->update(['is_active' => false]);
                            }
                        }

                        if ($needsRelink) {
                            $relinked++;
                        }

                        if ($terminationEffective && $wasActive) {
                            $deactivated++;
                        }

                        continue;
                    }

                    // No history covers today: a future schedule or a gap is not current.
                    $latestStarted = $histories
                        ->filter(function ($history) use ($today) {
                            return $history->start_date
                                && Carbon::parse($history->start_date)->startOfDay()->lt($today);
                        })
                        ->sortByDesc('start_date')
                        ->first();

                    $latestTerminationEnd = $latestStarted
                        ? ContractHistoryResolver::effectiveEnd($latestStarted)
                        : null;
                    $terminationEffective = $latestStarted
                        && ContractHistoryResolver::isTerminationType((string) $latestStarted->employment_type)
                        && $latestTerminationEnd !== null
                        && $latestTerminationEnd->lt($today);

                    $hadPointer = $contract->current_contract_history_id !== null;
                    $wasActive = (bool) $contract->is_active;

                    if (!$dryRun) {
                        $contract->histories()->update(['is_active' => false]);
                        $contract->update(['current_contract_history_id' => null]);

                        if ($terminationEffective) {
                            $contract->update(['is_active' => false]);
                            $contract->employee?->update(['is_active' => false]);
                        }
                    }

                    if ($hadPointer) {
                        $cleared++;
                    }
                    if ($terminationEffective && $wasActive) {
                        $deactivated++;
                    }
                }
            }, 'id_contract', 'id_contract');

        $mode = $dryRun ? 'DRY RUN' : 'SAVED';
        $this->info("{$mode}: {$relinked} pointer(s) relinked, {$cleared} cleared, {$overlaps} overlap(s), {$deactivated} termination(s) deactivated.");

        if ($overlaps > 0) {
            $this->warn('Overlap detected: those contracts have no current pointer until their dates are corrected.');
        }

        return self::SUCCESS;
    }
}
