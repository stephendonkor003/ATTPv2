<?php

namespace App\Services;

use App\Models\Procurement;
use App\Models\ThinkTankProcurementItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProcurementOpportunityExpiryService
{
    public function __construct(
        private readonly ThinkTankProcurementWorkflowService $workflow,
        private readonly ProcurementPublicationNotificationService $publicationNotifications,
    ) {}

    /**
     * Close a bounded batch of expired opportunities. The candidate lookup is
     * deliberately followed by a row lock and a second eligibility check so
     * concurrent scheduler or HTTP requests cannot produce duplicate events.
     *
     * @return array{closed: int, think_tank_events: int, applicant_notifications: int, applicant_close_notifications_supported: bool}
     */
    public function closeExpired(int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $today = now()->startOfDay();
        $ids = $this->expiredCandidateIds($today, $limit);
        $result = [
            'closed' => 0,
            'think_tank_events' => 0,
            'applicant_notifications' => 0,
            'applicant_close_notifications_supported' => $this->publicationNotifications->supports('closed'),
        ];

        foreach ($ids as $id) {
            $outcome = $this->closeById((string) $id, $today);
            if (! $outcome['closed']) {
                continue;
            }

            $result['closed']++;
            $result['think_tank_events'] += $outcome['think_tank_event'] ? 1 : 0;

            // The existing applicant lifecycle mail currently has reviewed
            // wording only for recall and republication. Never route a closure
            // through its republication fallback. If a dedicated close event is
            // added later, this capability check makes the scheduler adopt it.
            if ($result['applicant_close_notifications_supported'] && $outcome['procurement']) {
                $result['applicant_notifications'] += $this->publicationNotifications
                    ->queue($outcome['procurement'], 'closed');
            }
        }

        return $result;
    }

    /**
     * Close one opportunity through the same transactional lifecycle used by
     * the scheduler. This keeps legacy lazy expiry checks from bypassing Think
     * Tank workflow events.
     */
    public function closeOne(string $procurementId): bool
    {
        $today = now()->startOfDay();
        $outcome = $this->closeById($procurementId, $today);

        if ($outcome['closed']
            && $outcome['procurement']
            && $this->publicationNotifications->supports('closed')) {
            $this->publicationNotifications->queue($outcome['procurement'], 'closed');
        }

        return $outcome['closed'];
    }

    public function isExpired(Procurement $procurement, CarbonInterface $today): bool
    {
        return ! $procurement->trashed()
            && $procurement->status === 'published'
            && $procurement->application_end_date !== null
            && $procurement->application_end_date->startOfDay()->lt($today->copy()->startOfDay());
    }

    /** @return Collection<int, string> */
    private function expiredCandidateIds(CarbonInterface $today, int $limit): Collection
    {
        return Procurement::query()
            ->where('status', 'published')
            ->whereNotNull('application_end_date')
            ->where('application_end_date', '<', $today->toDateString())
            ->orderBy('application_end_date')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }

    /** @return array{closed: bool, think_tank_event: bool, procurement: ?Procurement} */
    private function closeById(string $procurementId, CarbonInterface $today): array
    {
        return DB::transaction(function () use ($procurementId, $today): array {
            $procurement = Procurement::query()
                ->whereKey($procurementId)
                ->lockForUpdate()
                ->first();

            if (! $procurement || ! $this->isExpired($procurement, $today)) {
                return [
                    'closed' => false,
                    'think_tank_event' => false,
                    'procurement' => null,
                ];
            }

            $procurement->forceFill(['status' => 'closed'])->save();
            $eventCreated = false;

            if ($procurement->procurement_owner_type === 'think_tank'
                && filled($procurement->think_tank_member_id)
                && filled($procurement->think_tank_procurement_plan_id)) {
                $item = ThinkTankProcurementItem::query()
                    ->where('procurement_id', $procurement->getKey())
                    ->where('plan_id', $procurement->think_tank_procurement_plan_id)
                    ->whereHas('plan', fn ($plans) => $plans
                        ->whereKey($procurement->think_tank_procurement_plan_id)
                        ->where('think_tank_member_id', $procurement->think_tank_member_id))
                    ->with(['plan.member', 'documents'])
                    ->lockForUpdate()
                    ->first();

                if ($item?->plan) {
                    $this->workflow->event(
                        $item->plan,
                        $item,
                        null,
                        'item_publication_closed',
                        'published',
                        'closed',
                        'The application window expired on '.$procurement->application_end_date->toDateString().'.',
                        [
                            'procurement_id' => (string) $procurement->getKey(),
                            'application_end_date' => $procurement->application_end_date->toDateString(),
                            'application_count' => $procurement->submissions()->count(),
                            'publication_version' => max(1, (int) $procurement->publication_version),
                            'closed_automatically' => true,
                        ],
                    );
                    $eventCreated = true;
                }
            }

            return [
                'closed' => true,
                'think_tank_event' => $eventCreated,
                'procurement' => $procurement,
            ];
        }, 3);
    }
}
