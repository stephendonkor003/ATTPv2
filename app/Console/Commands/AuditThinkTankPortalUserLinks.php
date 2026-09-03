<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditThinkTankPortalUserLinks extends Command
{
    protected $signature = 'think-tank:portal-user-links:audit';

    protected $description = 'Read-only audit of Think Tank primary portal-user and explicit tenant assignments';

    public function handle(): int
    {
        $duplicates = DB::table('attp_consortium_think_tanks')
            ->select('portal_user_id')
            ->selectRaw('COUNT(*) AS assignment_count')
            ->whereNotNull('portal_user_id')
            ->groupBy('portal_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('assignment_count')
            ->get();

        $invalidLinks = DB::table('attp_consortium_think_tanks as memberships')
            ->leftJoin('users', 'users.id', '=', 'memberships.portal_user_id')
            ->whereNotNull('memberships.portal_user_id')
            ->where(function ($query): void {
                $query->whereNull('users.id')
                    ->orWhere('users.user_type', '!=', 'think_tank')
                    ->orWhereNull('users.think_tank_member_id')
                    ->orWhereColumn('users.think_tank_member_id', '!=', 'memberships.id');
            })
            ->orderBy('memberships.name')
            ->get([
                'memberships.id as membership_id',
                'memberships.name as membership_name',
                'memberships.portal_user_id',
                'users.email as user_email',
                'users.user_type',
                'users.think_tank_member_id as explicit_membership_id',
            ]);

        $this->info('Read-only audit: no account or membership data was changed.');
        $this->line('Duplicate primary portal-user assignments: '.$duplicates->count());
        $this->line('Missing, cross-tenant, or wrong-type explicit links: '.$invalidLinks->count());

        if ($duplicates->isNotEmpty()) {
            $this->table(
                ['Portal user ID', 'Membership assignments'],
                $duplicates->map(fn ($row): array => [
                    (string) $row->portal_user_id,
                    (string) $row->assignment_count,
                ])->all(),
            );
        }

        if ($invalidLinks->isNotEmpty()) {
            $this->table(
                ['Membership', 'Portal user', 'Email', 'Type', 'Explicit membership'],
                $invalidLinks->map(fn ($row): array => [
                    $row->membership_name.' ('.$row->membership_id.')',
                    (string) $row->portal_user_id,
                    (string) $row->user_email,
                    (string) $row->user_type,
                    (string) ($row->explicit_membership_id ?: 'missing'),
                ])->all(),
            );
        }

        if ($duplicates->isNotEmpty() || $invalidLinks->isNotEmpty()) {
            $this->error('Think Tank portal-user links require explicit administrator review. No automatic repair was attempted.');

            return self::FAILURE;
        }

        $this->info('Think Tank portal-user links are internally consistent.');

        return self::SUCCESS;
    }
}
