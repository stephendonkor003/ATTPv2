<?php

namespace App\Services;

use App\Jobs\NotifyProcurementOfficersOfDisbursementHandoff;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcurementDisbursementHandoffNotificationService
{
    public function notify(ProcurementDisbursement $disbursement): void
    {
        if (! $disbursement->id || ! $this->shouldNotify($disbursement)) {
            return;
        }

        try {
            NotifyProcurementOfficersOfDisbursementHandoff::dispatch($disbursement->id);
            return;
        } catch (Throwable $exception) {
            Log::warning('Procurement handoff notification queue dispatch failed; falling back to immediate send.', [
                'disbursement_id' => $disbursement->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            NotifyProcurementOfficersOfDisbursementHandoff::dispatchSync($disbursement->id);
        } catch (Throwable $exception) {
            Log::warning('Procurement handoff notification fallback failed.', [
                'disbursement_id' => $disbursement->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldNotify(ProcurementDisbursement $disbursement): bool
    {
        if ($disbursement->procurement_notified_at) {
            return false;
        }

        if ($disbursement->isProcurementProcessingComplete()) {
            return false;
        }

        return $disbursement->paid_at
            && in_array(strtolower((string) $disbursement->status), ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true);
    }
}
