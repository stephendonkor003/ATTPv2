<?php

namespace App\Jobs;

use App\Mail\ProcurementDisbursementHandoffMail;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\User;
use App\Notifications\ProcurementDisbursementHandoffNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyProcurementOfficersOfDisbursementHandoff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $disbursementId)
    {
    }

    public function handle(): void
    {
        $disbursement = ProcurementDisbursement::with([
            'purchaseOrder.procurement',
            'purchaseOrder.vendor',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'vendor',
            'procurement',
        ])->find($this->disbursementId);

        if (! $disbursement) {
            Log::warning('Procurement handoff notification skipped; disbursement not found.', [
                'disbursement_id' => $this->disbursementId,
            ]);

            return;
        }

        if ($disbursement->procurement_notified_at || $disbursement->isProcurementProcessingComplete()) {
            return;
        }

        if (! $disbursement->paid_at || ! in_array(strtolower((string) $disbursement->status), ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true)) {
            return;
        }

        $recipients = $this->activeProcurementRecipients()->get();
        if ($recipients->isEmpty()) {
            Log::warning('Procurement handoff notification skipped; no active procurement recipients found.', [
                'disbursement_id' => $disbursement->id,
            ]);

            return;
        }

        $reviewUrl = route('procurement.disbursements.show', $disbursement);
        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new ProcurementDisbursementHandoffNotification($disbursement));
            } catch (Throwable $exception) {
                Log::warning('Procurement handoff database notification failed.', [
                    'disbursement_id' => $disbursement->id,
                    'user_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }

            try {
                Mail::to($recipient->email, $recipient->name)
                    ->send(new ProcurementDisbursementHandoffMail($disbursement, $recipient, $reviewUrl));
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('Procurement handoff email failed.', [
                    'disbursement_id' => $disbursement->id,
                    'user_id' => $recipient->id,
                    'email' => $recipient->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $disbursement->forceFill(['procurement_notified_at' => now()])->save();

        Log::info('Procurement handoff notification completed.', [
            'disbursement_id' => $disbursement->id,
            'recipients' => $recipients->count(),
            'emails_sent' => $sent,
        ]);
    }

    private function activeProcurementRecipients()
    {
        return User::query()
            ->with('role')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->where(function ($query) {
                $query->whereNull('is_disabled')
                    ->orWhere('is_disabled', false);
            })
            ->where(function ($query) {
                $query->whereNull('is_blacklisted')
                    ->orWhere('is_blacklisted', false);
            })
            ->where(function ($query) {
                $query->where('user_type', 'admin')
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery->whereIn('name', ['Super Admin', 'System Admin', 'Admin'])
                            ->orWhere('name', 'like', '%Procurement%')
                            ->orWhereHas('permissions', function ($permissionQuery) {
                                $permissionQuery->where('name', 'finance.purchase_orders.create');
                            });
                    })
                    ->orWhereHas('permissions', function ($permissionQuery) {
                        $permissionQuery->where('name', 'finance.purchase_orders.create');
                    });
            })
            ->orderBy('created_at');
    }
}
