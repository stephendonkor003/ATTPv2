<?php

namespace App\Console\Commands;

use App\Services\ThinkTankHistoricalFundingBackfillService;
use Illuminate\Console\Command;
use Throwable;

class BackfillThinkTankHistoricalFunding extends Command
{
    protected $signature = 'think-tank:finance:backfill-awards
        {--apply : Apply the validated plan atomically; omission is always read-only}
        {--expected-count= : Exact candidate count printed by the immediately preceding dry run}
        {--plan-hash= : SHA-256 plan hash printed by the immediately preceding dry run}';

    protected $description = 'Dry-run or apply the tenant-safe historical Funding-to-Think-Tanks award linkage';

    public function handle(ThinkTankHistoricalFundingBackfillService $backfill): int
    {
        $apply = (bool) $this->option('apply');
        $expectedCount = filter_var($this->option('expected-count'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $planHash = strtolower(trim((string) $this->option('plan-hash')));
        if ($apply && ($expectedCount === false || preg_match('/^[a-f0-9]{64}$/', $planHash) !== 1)) {
            $this->error('Apply mode requires --expected-count=<positive integer> and --plan-hash=<64-character hash> from a fresh dry run.');

            return self::FAILURE;
        }

        try {
            $result = $apply
                ? $backfill->apply((int) $expectedCount, $planHash)
                : $backfill->preview();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['dryRun']
            ? 'DRY RUN ONLY: no database records were changed.'
            : 'APPLY MODE: the validated plan was executed atomically.');
        $this->line('Source: '.$result['source']['programCode'].' / '.$result['source']['componentCode'].' / '.$result['source']['subActivityId']);
        $this->line('Plan hash: '.$result['planHash']);
        $this->table(
            ['Candidate awards', 'Ready', 'Recognized payments', 'Create allocations', 'Update POs', 'Update payments'],
            [[
                $result['candidates'],
                $result['ready'],
                $result['recognizedPayments'],
                $result['wouldCreateAllocations'],
                $result['wouldUpdatePurchaseOrders'],
                $result['wouldUpdatePayments'],
            ]],
        );
        $this->table(
            ['Award reference', 'Think tank member', 'Consortium', 'Award amount', 'Recognized paid', 'Allocation', 'Planned changes'],
            collect($result['rows'])->map(function (array $row): array {
                $changes = collect([
                    $row['willCreateAllocation'] ? 'create allocation' : null,
                    $row['willUpdateAllocation'] ? 'update allocation' : null,
                    $row['willUpdatePurchaseOrder'] ? 'assign PO tenant' : null,
                    $row['paymentsNeedingUpdate'] > 0 ? 'update '.$row['paymentsNeedingUpdate'].' payment(s)' : null,
                ])->filter()->implode(', ');

                return [
                    $row['referenceNumber'] ?: $row['purchaseOrderId'],
                    $row['thinkTankName'].' ('.$row['thinkTankMemberId'].')',
                    $row['consortiumId'],
                    $row['amount'].' USD',
                    $row['recognizedPaid'].' USD',
                    $row['allocationId'] ?: '[will create]',
                    $changes ?: 'none',
                ];
            })->all(),
        );

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                $this->error((string) $error);
            }
            $this->error('Backfill is blocked. Resolve every mapping/source conflict; no writes were made.');

            return self::FAILURE;
        }

        if ($result['dryRun']) {
            $this->warn(
                'Review every row, then rerun with --apply --expected-count='.$result['candidates']
                .' --plan-hash='.$result['planHash'].' to authorize the exact atomic plan.'
            );
        } else {
            $this->line('Allocations created: '.($result['allocationsCreated'] ?? 0));
            $this->line('Allocations updated: '.($result['allocationsUpdated'] ?? 0));
            $this->line('Purchase orders updated: '.($result['purchaseOrdersUpdated'] ?? 0));
            $this->line('Payments updated: '.($result['paymentsUpdated'] ?? 0));
            $this->info(($result['changed'] ?? false)
                ? 'Historical awards are linked to tenant finance.'
                : 'No changes were needed; the backfill is already complete.');
        }

        return self::SUCCESS;
    }
}
