<?php

namespace App\Support;

use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\User;
use App\Models\VendorAdminAlertRead;
use App\Models\VendorMessage;
use App\Models\VendorReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VendorAdminAlerts
{
    public const TYPE_PURCHASE_REQUESTS = 'purchase_orders';
    public const TYPE_REPORTS = 'reports';
    public const TYPE_PAYMENT_RECEIPTS = 'payment_receipts';
    public const TYPE_MESSAGES = 'messages';

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_PURCHASE_REQUESTS,
            self::TYPE_REPORTS,
            self::TYPE_PAYMENT_RECEIPTS,
            self::TYPE_MESSAGES,
        ];
    }

    public static function forUser(User $user, int $limit = 6): array
    {
        $readIds = Schema::hasTable('vendor_admin_alert_reads')
            ? VendorAdminAlertRead::where('admin_id', $user->id)
                ->get()
                ->groupBy('alert_type')
                ->map(fn ($rows) => $rows->pluck('source_id')->map(fn ($id) => (string) $id)->all())
                ->all()
            : [];

        return [
            self::TYPE_PURCHASE_REQUESTS => self::bucket(
                self::TYPE_PURCHASE_REQUESTS,
                'Purchase orders',
                'feather-file-text',
                ProcurementPurchaseOrder::with('vendor')
                    ->whereNotNull('vendor_id')
                    ->orderByDesc('issued_at')
                    ->orderByDesc('created_at'),
                fn (ProcurementPurchaseOrder $purchaseOrder) => [
                    'title' => $purchaseOrder->po_title ?: $purchaseOrder->reference_no,
                    'vendor' => $purchaseOrder->vendor?->name ?? 'Vendor',
                    'amount' => trim(($purchaseOrder->resolved_currency ?: 'USD') . ' ' . number_format((float) $purchaseOrder->amount, 2)),
                    'date' => ($purchaseOrder->issued_at ?: $purchaseOrder->created_at)?->format('d M Y H:i') ?? 'N/A',
                    'url' => Route::has('procurement.purchase-orders.show')
                        ? route('procurement.purchase-orders.show', $purchaseOrder)
                        : '#',
                ],
                $readIds[self::TYPE_PURCHASE_REQUESTS] ?? [],
                $limit
            ),
            self::TYPE_REPORTS => self::bucket(
                self::TYPE_REPORTS,
                'Vendor reports',
                'feather-clipboard',
                VendorReport::with('user')->latest(),
                fn (VendorReport $report) => [
                    'title' => $report->title ?: $report->reference_no,
                    'vendor' => $report->user?->name ?? 'Vendor',
                    'amount' => null,
                    'date' => $report->created_at?->format('d M Y H:i') ?? 'N/A',
                    'url' => Route::has('vendors.requests.reports.show')
                        ? route('vendors.requests.reports.show', $report)
                        : '#',
                ],
                $readIds[self::TYPE_REPORTS] ?? [],
                $limit
            ),
            self::TYPE_PAYMENT_RECEIPTS => self::bucket(
                self::TYPE_PAYMENT_RECEIPTS,
                'Payment receipts',
                'feather-credit-card',
                ProcurementDisbursement::with('vendor')
                    ->whereNotNull('vendor_id')
                    ->where(function ($query) {
                        $query->where('recipient_confirmation_status', 'confirmed')
                            ->orWhereNotNull('recipient_confirmed_at');
                    })
                    ->orderByDesc('recipient_confirmed_at')
                    ->orderByDesc('created_at'),
                fn (ProcurementDisbursement $receipt) => [
                    'title' => $receipt->reference_no ?: 'Payment receipt',
                    'vendor' => $receipt->vendor?->name ?? 'Vendor',
                    'amount' => trim(($receipt->currency ?: $receipt->resolved_currency ?: 'USD') . ' ' . number_format((float) $receipt->amount, 2)),
                    'date' => ($receipt->recipient_confirmed_at ?: $receipt->paid_at ?: $receipt->created_at)?->format('d M Y H:i') ?? 'N/A',
                    'url' => Route::has('procurement.disbursements.show')
                        ? route('procurement.disbursements.show', $receipt)
                        : '#',
                ],
                $readIds[self::TYPE_PAYMENT_RECEIPTS] ?? [],
                $limit
            ),
            self::TYPE_MESSAGES => self::bucket(
                self::TYPE_MESSAGES,
                'Clarifications',
                'feather-message-square',
                VendorMessage::with('user')->latest(),
                fn (VendorMessage $message) => [
                    'title' => $message->subject ?: 'Clarification message',
                    'vendor' => $message->user?->name ?? 'Vendor',
                    'amount' => null,
                    'date' => $message->created_at?->format('d M Y H:i') ?? 'N/A',
                    'url' => Route::has('vendors.requests.messages.show')
                        ? route('vendors.requests.messages.show', $message)
                        : '#',
                ],
                $readIds[self::TYPE_MESSAGES] ?? [],
                $limit
            ),
        ];
    }

    public static function markRead(User $user, string $type, string $sourceId): VendorAdminAlertRead
    {
        if (! in_array($type, self::allowedTypes(), true)) {
            abort(404);
        }

        return VendorAdminAlertRead::updateOrCreate(
            [
                'admin_id' => $user->id,
                'alert_type' => $type,
                'source_id' => $sourceId,
            ],
            [
                'read_at' => now(),
            ]
        );
    }

    private static function bucket(
        string $type,
        string $label,
        string $icon,
        Builder $query,
        callable $present,
        array $readIds,
        int $limit
    ): array {
        $unreadCount = (clone $query)
            ->when(! empty($readIds), fn ($builder) => $builder->whereNotIn($builder->getModel()->getTable() . '.id', $readIds))
            ->count();

        $items = (clone $query)
            ->limit($limit)
            ->get()
            ->map(function ($model) use ($type, $present, $readIds) {
                $payload = $present($model);
                $isRead = in_array((string) $model->id, $readIds, true);

                return [
                    'id' => (string) $model->id,
                    'type' => $type,
                    'title' => $payload['title'],
                    'vendor' => $payload['vendor'],
                    'amount' => $payload['amount'],
                    'date' => $payload['date'],
                    'url' => $payload['url'],
                    'is_read' => $isRead,
                    'mark_url' => Route::has('vendors.requests.alerts.read')
                        ? route('vendors.requests.alerts.read', ['type' => $type, 'source' => $model->id])
                        : '#',
                ];
            })
            ->values();

        return [
            'type' => $type,
            'label' => $label,
            'icon' => $icon,
            'unread_count' => $unreadCount,
            'items' => $items,
        ];
    }
}
