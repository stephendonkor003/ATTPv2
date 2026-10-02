<?php

namespace App\Services;

use App\Mail\VendorProcurementLifecycleMail;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

final class ProcurementPublicationNotificationService
{
    private const SUPPORTED_EVENTS = [
        'recalled',
        'republished',
    ];

    public function supports(string $event): bool
    {
        return in_array($event, self::SUPPORTED_EVENTS, true);
    }

    /**
     * Queue at most one lifecycle message per applicant account. Call this
     * only after the publication transaction has committed.
     */
    public function queue(Procurement $procurement, string $event, bool $onlyActive = false): int
    {
        if (! $this->supports($event)) {
            throw new InvalidArgumentException("Unsupported procurement publication lifecycle event [{$event}].");
        }

        $procurement->loadMissing('thinkTankMember:id,name,logo_path');
        $submissions = $procurement->submissions()
            ->when($onlyActive, fn ($query) => $query->where('status', '<>', FormSubmission::STATUS_WITHDRAWN))
            ->whereNotNull('submitted_by')
            ->latest()
            ->get()
            ->unique(fn (FormSubmission $submission): string => (string) $submission->submitted_by)
            ->values();
        if ($submissions->isEmpty()) {
            return 0;
        }

        $vendors = User::query()
            ->whereIn('id', $submissions->pluck('submitted_by')->filter()->all())
            ->where('user_type', 'vendor')
            ->get()
            ->keyBy(fn (User $vendor): string => (string) $vendor->id);
        $queued = 0;

        foreach ($submissions as $submission) {
            $vendor = $vendors->get((string) $submission->submitted_by);
            if (! $vendor || ! filter_var($vendor->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($vendor->email)->queue(new VendorProcurementLifecycleMail(
                    $procurement,
                    $vendor,
                    $submission,
                    $event,
                    $procurement->recall_reason,
                ));
                $queued++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $queued;
    }
}
