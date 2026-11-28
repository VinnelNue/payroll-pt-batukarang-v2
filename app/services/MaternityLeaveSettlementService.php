<?php

namespace App\Services;

use App\Http\Controllers\PayrollController;
use App\Models\MaternityLeaveAccrual;
use App\Models\MaternityLeaveCase;
use App\Models\MaternityLeaveSettlement;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaternityLeaveSettlementService
{
    public function __construct(
        private readonly MaternityLeaveAccrualService $accrualService
    ) {
    }

    /**
     * Called in the same transaction as HRD verification. The settlement is
     * created immediately; this does not mean Finance has transferred money.
     */
    public function createForVerifiedCase(
        MaternityLeaveCase $case,
        CarbonInterface $verifiedAt
    ): MaternityLeaveSettlement {
        $existing = MaternityLeaveSettlement::query()
            ->where('maternity_case_id', $case->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'maternity_case' => 'Settlement untuk kasus CM ini sudah pernah dibuat. Tidak boleh membuat settlement kedua.',
            ]);
        }

        $accruals = $this->accrualService->accrueThroughVerification($case, $verifiedAt);
        $gross = round((float) $accruals->sum('amount'), 2);

        $settlement = MaternityLeaveSettlement::query()->create([
            'maternity_case_id' => $case->id,
            'employee_id' => $case->employee_id,
            'status' => $gross > 0 ? 'pending' : 'no_balance',
            'gross_amount' => $gross,
            'pph21_amount' => 0,
            'net_amount' => $gross,
            'notes' => $gross > 0
                ? 'Dibuat otomatis ketika HRD memverifikasi dokumen CM.'
                : 'Tidak ada saldo CM yang perlu dibayar melalui settlement; hari yang sudah masuk payroll reguler tidak dihitung ulang.',
        ]);

        if ($accruals->isNotEmpty() && $gross > 0) {
            MaternityLeaveAccrual::query()
                ->whereIn('id', $accruals->pluck('id'))
                ->whereNull('settlement_id')
                ->update([
                    'settlement_id' => $settlement->id,
                    'status' => 'settlement_pending',
                    'updated_at' => now(),
                ]);
        }

        if ($gross <= 0) {
            // No transfer is required when there is no accrued amount.
            $case->forceFill(['released_at' => now()])->save();
        }

        return $settlement->fresh();
    }

    public function calculateTaxPreview(
        MaternityLeaveSettlement $settlement,
        CarbonInterface $paymentDate
    ): array {
        if ($settlement->status !== 'pending') {
            throw ValidationException::withMessages([
                'settlement' => 'Hanya settlement berstatus pending yang dapat dihitung pajaknya.',
            ]);
        }

        return app(PayrollController::class)->calculateMaternitySettlementTax(
            (int) $settlement->employee_id,
            (float) $settlement->gross_amount,
            Carbon::parse($paymentDate)->toDateString(),
            (int) $settlement->id
        );
    }

    public function markAccrualsPaid(MaternityLeaveSettlement $settlement): void
    {
        MaternityLeaveAccrual::query()
            ->where('settlement_id', $settlement->id)
            ->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
    }
}
