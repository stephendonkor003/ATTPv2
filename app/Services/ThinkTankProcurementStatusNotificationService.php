<?php

namespace App\Services;

use App\Jobs\SendThinkTankProcurementStatusNotification;
use App\Models\ThinkTankProcurementEvent;
use App\Models\ThinkTankProcurementStatusNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ThinkTankProcurementStatusNotificationService
{
    private const SECRETARIAT_ADMIN_ROLES = [
        'System Admin',
        'Super Admin',
        'Super Administrator',
        'Admin',
        'Administrator',
    ];

    private const PROCUREMENT_PERMISSIONS = [
        'think_tank.procurement.review',
        'think_tank.procurement.step',
        'procurement.manage_all',
    ];

    /**
     * Persist one delivery per normalized recipient while the workflow event is
     * still in its transaction, then enqueue only after that transaction commits.
     */
    public function stage(ThinkTankProcurementEvent $event): void
    {
        $event->loadMissing([
            'plan.member.portalUser.thinkTankMembership',
            'plan.member.portalUsers.thinkTankMembership',
            'item.documents',
            'actor.role.permissions',
            'actor.permissions',
        ]);

        if (! $event->plan) {
            return;
        }

        [$heading, $message] = $this->content($event);
        $ids = $this->recipients($event)
            ->map(function (array $recipient) use ($event, $heading, $message): string {
                $notification = ThinkTankProcurementStatusNotification::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'recipient_email' => $recipient['email'],
                    ],
                    [
                        'recipient_user_id' => $recipient['user_id'],
                        'recipient_name' => $recipient['name'],
                        'recipient_scope' => $recipient['scope'],
                        'audiences' => $recipient['audiences'],
                        'heading' => $heading,
                        'message' => $message,
                        'status' => ThinkTankProcurementStatusNotification::STATUS_PENDING,
                    ],
                );

                return (string) $notification->id;
            })
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            logger()->warning('Procurement status changed without an active email recipient.', [
                'event_id' => $event->id,
                'plan_id' => $event->plan_id,
                'item_id' => $event->item_id,
                'action' => $event->action,
            ]);

            return;
        }

        DB::afterCommit(function () use ($ids): void {
            $ids->each(function (string $id): void {
                try {
                    SendThinkTankProcurementStatusNotification::dispatch($id)
                        ->onQueue('mail')
                        ->afterCommit();
                } catch (Throwable $exception) {
                    // A sync queue executes the job inside this callback. The
                    // status transition is already committed, so a provider
                    // outage must not turn that successful write into a 500.
                    Log::warning('Procurement status notification could not be dispatched.', [
                        'notification_id' => $id,
                        'exception' => $exception::class,
                    ]);
                }
            });
        });
    }

    /** @return Collection<int, array{user_id: ?string, email: string, name: ?string, scope: string, audiences: array<int, string>}> */
    private function recipients(ThinkTankProcurementEvent $event): Collection
    {
        $recipients = collect();

        $this->activeUsers()
            ->where(fn (Builder $query): Builder => $query->whereNull('user_type')->orWhere('user_type', '<>', 'think_tank'))
            ->whereHas('role', fn (Builder $role): Builder => $role->whereIn('name', self::SECRETARIAT_ADMIN_ROLES))
            ->get()
            ->each(fn (User $user) => $this->addRecipient($recipients, $user, 'secretariat', 'secretariat_administrator'));

        $notifyProcurementTeam = in_array($event->action, ['plan_submitted', 'item_submitted_to_secretariat'], true)
            || ! $event->actor
            || $event->actor->isThinkTankUser();

        if ($notifyProcurementTeam) {
            $this->activeUsers()
                ->where(fn (Builder $query): Builder => $query->whereNull('user_type')->orWhere('user_type', '<>', 'think_tank'))
                ->where(function (Builder $query): void {
                    $query->whereHas('role', fn (Builder $role): Builder => $role->where('name', 'Procurement Officer'))
                        ->orWhereHas('role.permissions', fn (Builder $permission): Builder => $permission->whereIn('name', self::PROCUREMENT_PERMISSIONS))
                        ->orWhereHas('permissions', fn (Builder $permission): Builder => $permission->whereIn('name', self::PROCUREMENT_PERMISSIONS));
                })
                ->get()
                ->each(fn (User $user) => $this->addRecipient($recipients, $user, 'secretariat', 'secretariat_procurement_officer'));
        } elseif ($event->actor && ! $event->actor->isThinkTankUser() && $this->isActive($event->actor)) {
            $this->addRecipient($recipients, $event->actor, 'secretariat', 'acting_secretariat_officer');
        }

        $member = $event->plan->member;
        if ($member) {
            $primaryId = $member->portal_user_id;
            $this->activeUsers()
                ->where(function (Builder $query) use ($primaryId): void {
                    $query->where('user_type', 'think_tank');
                    if ($primaryId) {
                        $query->orWhere('users.id', $primaryId);
                    }
                })
                ->where(function (Builder $query) use ($member, $primaryId): void {
                    $query->where('think_tank_member_id', $member->id);
                    if ($primaryId) {
                        $query->orWhere('users.id', $primaryId);
                    }
                })
                ->where(function (Builder $query) use ($primaryId): void {
                    $query->whereIn('think_tank_access_level', [
                        User::THINK_TANK_ACCESS_ADMIN,
                        User::THINK_TANK_ACCESS_PROCUREMENT,
                    ]);
                    if ($primaryId) {
                        $query->orWhere('users.id', $primaryId);
                    }
                })
                ->get()
                ->each(function (User $user) use ($recipients): void {
                    $audience = $user->resolvedThinkTankAccessLevel() === User::THINK_TANK_ACCESS_PROCUREMENT
                        ? 'think_tank_procurement_officer'
                        : 'think_tank_administrator';
                    $this->addRecipient($recipients, $user, 'think_tank', $audience);
                });
        }

        return $recipients->values();
    }

    /**
     * Revalidate the durable recipient immediately before delivery. A queued
     * status email must never outlive account revocation, reassignment, role
     * removal or an email-address change.
     */
    public function recipientIsStillEligible(ThinkTankProcurementStatusNotification $notification): bool
    {
        $notification->loadMissing([
            'recipient.role.permissions',
            'recipient.permissions',
            'event.plan.member:id,portal_user_id',
            'event.actor:id',
        ]);

        $recipient = $notification->recipient;
        $event = $notification->event;
        $member = $event?->plan?->member;
        if (! $recipient || ! $event || ! $member || ! $this->isActive($recipient)) {
            return false;
        }

        $currentEmail = Str::lower(trim((string) $recipient->email));
        if (filter_var($currentEmail, FILTER_VALIDATE_EMAIL) === false
            || ! hash_equals($currentEmail, Str::lower(trim((string) $notification->recipient_email)))) {
            return false;
        }

        if ($notification->recipient_scope === 'think_tank') {
            $isPrimaryAdministrator = filled($member->portal_user_id)
                && (string) $member->portal_user_id === (string) $recipient->id;
            $isAssignedProcurementRecipient = $recipient->user_type === 'think_tank'
                && (string) $recipient->think_tank_member_id === (string) $member->id
                && in_array($recipient->resolvedThinkTankAccessLevel(), [
                    User::THINK_TANK_ACCESS_ADMIN,
                    User::THINK_TANK_ACCESS_PROCUREMENT,
                ], true);

            return $isPrimaryAdministrator || $isAssignedProcurementRecipient;
        }

        if ($notification->recipient_scope !== 'secretariat' || $recipient->isThinkTankUser()) {
            return false;
        }

        $audiences = collect((array) $notification->audiences);

        return ($audiences->contains('secretariat_administrator') && $this->isSecretariatAdministrator($recipient))
            || ($audiences->contains('secretariat_procurement_officer') && $this->isSecretariatProcurementOfficer($recipient))
            || ($audiences->contains('acting_secretariat_officer')
                && (string) $event->actor_id === (string) $recipient->id
                && ($this->isSecretariatAdministrator($recipient) || $this->isSecretariatProcurementOfficer($recipient)));
    }

    private function activeUsers(): Builder
    {
        return User::query()
            ->with(['role.permissions', 'permissions', 'thinkTankMembership'])
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->where(function (Builder $query): void {
                $query->whereNull('is_disabled')
                    ->orWhere('is_disabled', false)
                    ->orWhere(function (Builder $temporaryBlock): void {
                        $temporaryBlock->where('is_disabled', true)
                            ->whereNotNull('disabled_until')
                            ->where('disabled_until', '<=', now());
                    });
            })
            ->where(fn (Builder $query): Builder => $query->whereNull('is_blacklisted')->orWhere('is_blacklisted', false));
    }

    private function isActive(User $user): bool
    {
        return filled($user->email) && ! $user->hasActiveLoginBlock() && ! $user->is_blacklisted;
    }

    private function isSecretariatAdministrator(User $user): bool
    {
        return $user->role && in_array($user->role->name, self::SECRETARIAT_ADMIN_ROLES, true);
    }

    private function isSecretariatProcurementOfficer(User $user): bool
    {
        return $user->role?->name === 'Procurement Officer'
            || collect(self::PROCUREMENT_PERMISSIONS)->contains(
                fn (string $permission): bool => $user->hasPermission($permission)
            );
    }

    private function addRecipient(Collection $recipients, User $user, string $scope, string $audience): void
    {
        $email = Str::lower(trim((string) $user->email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $existing = $recipients->get($email, [
            'user_id' => (string) $user->id,
            'email' => $email,
            'name' => filled($user->name) ? trim((string) $user->name) : null,
            'scope' => $scope,
            'audiences' => [],
        ]);
        $existing['audiences'] = array_values(array_unique([...$existing['audiences'], $audience]));
        if ($scope === 'think_tank') {
            $existing['scope'] = 'think_tank';
        }

        $recipients->put($email, $existing);
    }

    /** @return array{0: string, 1: string} */
    private function content(ThinkTankProcurementEvent $event): array
    {
        return match ($event->action) {
            'plan_submitted' => [
                'Annual procurement plan sent to the AUC-ATTP Secretariat',
                'The Think Tank submitted its annual procurement plan. The AUC-ATTP Secretariat procurement team can now review each procurement item and coordinate the required World Bank no-objection process.',
            ],
            'item_submitted_to_secretariat' => [
                'Procurement item sent to the AUC-ATTP Secretariat',
                'This procurement item has been submitted for Secretariat review and World Bank no-objection coordination.',
            ],
            'plan_approve' => [
                'Annual procurement plan accepted by the AUC-ATTP Secretariat',
                'The annual plan has passed Secretariat review. Each accepted item is now pending the applicable World Bank no-objection decision.',
            ],
            'item_approve' => [
                'Procurement item pending World Bank no-objection',
                'The AUC-ATTP Secretariat accepted this procurement item and will coordinate the World Bank no-objection process.',
            ],
            'plan_revision_requested', 'item_revision_requested' => [
                'Procurement revision requested',
                'The AUC-ATTP Secretariat requested corrections. Review the recorded action note, update the affected procurement information and resubmit it.',
            ],
            'plan_rejected', 'item_rejected' => [
                'Procurement submission rejected',
                'The AUC-ATTP Secretariat rejected this procurement submission. Review the recorded reason before preparing the next submission.',
            ],
            'world_bank_no_objection_recorded' => [
                'World Bank no-objection received — ready to execute',
                'The AUC-ATTP Secretariat recorded the World Bank no-objection for this procurement item. It is now ready for the Think Tank procurement team to begin execution.',
            ],
            'item_execution_created' => [
                'Procurement item moved into execution',
                'The approved procurement item has been moved into execution and its procurement opportunity has been published for applications.',
            ],
            'item_publication_recalled' => [
                'Procurement opportunity recalled',
                'The published procurement opportunity has been recalled. Review the recorded reason and complete the required correction before republication.',
            ],
            'item_publication_republished' => [
                'Procurement opportunity republished',
                'The corrected procurement opportunity has been republished with a new application window.',
            ],
            'item_publication_closed' => [
                'Procurement opportunity closed',
                'The published application window has ended and the procurement opportunity is now closed to new or updated applications.',
            ],
            'item_corrected' => [
                'Procurement item correction recorded',
                'The Think Tank updated an item that had required correction. Review the revised information before the next submission.',
            ],
            default => [
                'Procurement workflow status updated',
                'A status in the annual procurement workflow changed. Open the appropriate workspace to review the latest details and audit trail.',
            ],
        };
    }
}
