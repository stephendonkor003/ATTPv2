<?php

namespace App\Jobs;

use App\Mail\VendorPurchaseRequestSubmittedAdminMail;
use App\Models\User;
use App\Models\VendorPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAdminsOfVendorPurchaseRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $purchaseRequestId)
    {
    }

    public function handle(): void
    {
        $purchaseRequest = VendorPurchaseRequest::with([
            'user',
            'subActivity.activity.project.program',
            'items',
            'documents',
        ])->find($this->purchaseRequestId);

        if (! $purchaseRequest) {
            Log::warning('Vendor purchase request admin notification skipped; request not found.', [
                'purchase_request_id' => $this->purchaseRequestId,
            ]);

            return;
        }

        $reviewUrl = route('vendors.requests.purchase-requests.show', $purchaseRequest);
        $sent = 0;

        $this->activeAdminQuery()->chunk(50, function ($admins) use ($purchaseRequest, $reviewUrl, &$sent) {
            foreach ($admins as $admin) {
                try {
                    Mail::to($admin->email, $admin->name)
                        ->send(new VendorPurchaseRequestSubmittedAdminMail($purchaseRequest, $admin, $reviewUrl));
                    $sent++;
                } catch (Throwable $exception) {
                    Log::warning('Vendor purchase request admin email failed.', [
                        'purchase_request_id' => $purchaseRequest->id,
                        'admin_id' => $admin->id,
                        'admin_email' => $admin->email,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Vendor purchase request admin notification completed.', [
            'purchase_request_id' => $purchaseRequest->id,
            'emails_sent' => $sent,
        ]);
    }

    private function activeAdminQuery()
    {
        return User::query()
            ->with('role')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->where(function ($query) {
                $query->where('user_type', 'admin')
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery->whereIn('name', ['Super Admin', 'System Admin', 'Admin']);
                    });
            })
            ->where(function ($query) {
                $query->whereNull('is_disabled')
                    ->orWhere('is_disabled', false);
            })
            ->where(function ($query) {
                $query->whereNull('is_blacklisted')
                    ->orWhere('is_blacklisted', false);
            })
            ->orderBy('created_at');
    }
}
