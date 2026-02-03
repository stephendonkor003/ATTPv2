<?php

namespace Database\Seeders;

use App\Models\GovernanceNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyProcurementSeeder extends Seeder
{
    private const NODE_MAP = [
        1 => 'Assembly',
        2 => 'African Union Commission',
        3 => 'Department of Health',
        4 => 'Department of Trade',
        5 => 'Directorate of Governance',
        6 => 'Directorate of Elections',
        7 => 'Program Operations Unit',
        8 => 'Consortia Alpha',
        9 => 'Think Tank A',
    ];

    public function run(): void
    {
        $nodes = GovernanceNode::pluck('id', 'name');

        $procurements = [
            [
                'legacy_id' => 1,
                'resource_id' => 1,
                'governance_node_id' => null,
                'title' => 'Procurement of ICT Supply Goods / Equipment\'s',
                'slug' => 'procurement-of-ict-supply-goods-equipments',
                'reference_no' => 'PR-2026-FXDPX',
                'description' => '<p>Procurement of ICT Supply Goods / Equipment\'s&nbsp;</p>',
                'fiscal_year' => 2025,
                'estimated_budget' => 1.00,
                'status' => 'closed',
                'created_by' => '1',
                'created_at' => '2026-01-27 14:54:54',
                'updated_at' => '2026-01-27 15:35:00',
            ],
        ];

        $rows = [];
        foreach ($procurements as $procurement) {
            $legacyResourceId = $procurement['resource_id'];
            $resourceId = null;
            if (!is_null($legacyResourceId)) {
                $resourceId = $this->legacyUuid('resources', (int) $legacyResourceId);
            }

            $nodeId = null;
            if (!is_null($procurement['governance_node_id'])) {
                $nodeName = self::NODE_MAP[$procurement['governance_node_id']] ?? null;
                $nodeId = $nodeName ? ($nodes[$nodeName] ?? null) : null;
                if ($nodeName && !$nodeId) {
                    $this->command?->warn("Governance node '{$nodeName}' not found for procurement {$procurement['reference_no']}.");
                }
            }

            $createdBy = $procurement['created_by'];
            if (is_numeric($createdBy)) {
                $createdBy = $this->legacyUuid('users', (int) $createdBy);
            }

            $rows[] = [
                'id' => $this->legacyUuid('procurements', (int) $procurement['legacy_id']),
                'resource_id' => $resourceId,
                'governance_node_id' => $nodeId,
                'title' => $procurement['title'],
                'slug' => $procurement['slug'],
                'reference_no' => $procurement['reference_no'],
                'description' => $procurement['description'],
                'fiscal_year' => $procurement['fiscal_year'],
                'estimated_budget' => $procurement['estimated_budget'],
                'status' => $procurement['status'],
                'created_by' => $createdBy,
                'created_at' => $procurement['created_at'],
                'updated_at' => $procurement['updated_at'],
            ];
        }

        DB::table('procurements')->upsert(
            $rows,
            ['id'],
            [
                'resource_id',
                'governance_node_id',
                'title',
                'slug',
                'reference_no',
                'description',
                'fiscal_year',
                'estimated_budget',
                'status',
                'created_by',
                'updated_at',
            ]
        );
    }

    private function legacyUuid(string $scope, int $legacyId): string
    {
        $hash = md5($scope . ':' . $legacyId);
        $timeHi = hexdec(substr($hash, 12, 4));
        $timeHi = ($timeHi & 0x0fff) | 0x5000;
        $clockSeq = hexdec(substr($hash, 16, 4));
        $clockSeq = ($clockSeq & 0x3fff) | 0x8000;

        return sprintf(
            '%s-%s-%04x-%04x-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            $timeHi,
            $clockSeq,
            substr($hash, 20, 12)
        );
    }
}
