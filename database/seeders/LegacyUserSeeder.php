<?php

namespace Database\Seeders;

use App\Models\GovernanceNode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyUserSeeder extends Seeder
{
    private const ROLE_MAP = [
        1 => 'System Admin',
        2 => 'HR Manager',
        3 => 'HR Officer',
        4 => 'Finance Manager',
        5 => 'Finance Officer',
        6 => 'Budget Officer',
        7 => 'Auditor',
        8 => 'Prescreening Evaluator',
        9 => 'Evaluation Evaluator',
        10 => 'Funding Partner',
    ];

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
        $roles = Role::pluck('id', 'name');
        $nodes = GovernanceNode::pluck('id', 'name');

        $users = [
            [
                'legacy_id' => 1,
                'name' => 'System Admin',
                'email' => 'amodonlimited@gmail.com',
                'password' => '$2y$12$W1eIZaPW/in1djWJdYWgsO4gbRav0UZ33LShtw40etbWYiGf5rHsi',
                'password_changed_at' => null,
                'user_type' => 'admin',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 1,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 13:55:40',
                'updated_at' => '2026-02-01 13:55:40',
            ],
            [
                'legacy_id' => 2,
                'name' => 'Prescreening Evaluator',
                'email' => 'prescreener@example.com',
                'password' => '$2y$12$gxWLcvO1KFxtv.L9Fu2pb.O28Se8LuoZcjb7D0GeUk1XH9C8xwO/S',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 8,
                'governance_node_id' => 5,
                'created_at' => '2026-02-01 13:55:40',
                'updated_at' => '2026-02-01 13:55:40',
            ],
            [
                'legacy_id' => 3,
                'name' => 'Evaluation Evaluator',
                'email' => 'evaluator@example.com',
                'password' => '$2y$12$1BUfeJIlRJKfp.U3eYnuAeLYOVCKWeZSTanp4tm/LAnzH44nLsOrO',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 9,
                'governance_node_id' => 6,
                'created_at' => '2026-02-01 13:55:41',
                'updated_at' => '2026-02-01 13:55:41',
            ],
            [
                'legacy_id' => 4,
                'name' => 'HR Manager',
                'email' => 'hr.manager@example.com',
                'password' => '$2y$12$L3o9ApbObmgjs4bkIayCOeY6LNEbxyTBT5zo9740AocgkwGqomJBK',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 2,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 13:55:41',
                'updated_at' => '2026-02-01 13:55:41',
            ],
            [
                'legacy_id' => 5,
                'name' => 'HR Officer',
                'email' => 'hr.officer@example.com',
                'password' => '$2y$12$CST4MQXSoqRrSSr5QwGQxeggjeD2pZbDU/IkTPFSISwqw6Zze7NU.',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 3,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 13:55:41',
                'updated_at' => '2026-02-01 13:55:41',
            ],
            [
                'legacy_id' => 6,
                'name' => 'Finance Manager',
                'email' => 'finance.manager@example.com',
                'password' => '$2y$12$h8sKi689l8qdgtJtGvrr3ug9ZU35/yY7frlHTSx7pCsH6or6UOfHe',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 4,
                'governance_node_id' => 4,
                'created_at' => '2026-02-01 13:55:41',
                'updated_at' => '2026-02-01 13:55:41',
            ],
            [
                'legacy_id' => 7,
                'name' => 'Finance Officer',
                'email' => 'finance.officer@example.com',
                'password' => '$2y$12$OgZgC5XodsIvHkSEVPJKvu1FB0c5a9UMN3EM2hYMRdkGrTkAHu8XW',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 5,
                'governance_node_id' => 4,
                'created_at' => '2026-02-01 13:55:41',
                'updated_at' => '2026-02-01 13:55:41',
            ],
            [
                'legacy_id' => 8,
                'name' => 'Budget Officer',
                'email' => 'budget.officer@example.com',
                'password' => '$2y$12$0vrOD7h.lmvjL1aA/m92DeiyCK2eyHrtklmX9WzFssFr7HFnnuWKi',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 6,
                'governance_node_id' => 3,
                'created_at' => '2026-02-01 13:55:42',
                'updated_at' => '2026-02-01 13:55:42',
            ],
            [
                'legacy_id' => 9,
                'name' => 'Auditor',
                'email' => 'auditor@example.com',
                'password' => '$2y$12$2ymwD9wAiimlegz21pvVcutEOsr0ympeNNUgHYFJiEfqJs.eDpp7i',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 7,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 13:55:42',
                'updated_at' => '2026-02-01 13:55:42',
            ],
            [
                'legacy_id' => 10,
                'name' => 'Don Kors',
                'email' => 'donkors@africanunion.org',
                'password' => '$2y$12$56YGntiP9Qs8yKHphZ3g7uzex9uEZUqNyNzH5YlqMsh3EDaACr3s.',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:42',
                'updated_at' => '2026-02-01 13:55:42',
            ],
            [
                'legacy_id' => 11,
                'name' => 'Hailemeskel Yilma Tadesse',
                'email' => 'hailemeskely@africanunion.org',
                'password' => '$2y$12$YFmuX8rOOs1M4u/U0ii/Z.if5kSdkTDti8Z/Y9wFngTD4SmzTvoiG',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:42',
                'updated_at' => '2026-02-01 13:55:42',
            ],
            [
                'legacy_id' => 12,
                'name' => 'Rasheed Omar',
                'email' => 'climate@africanunion.org',
                'password' => '$2y$12$wWBz9SXu1SweCdHDQDoKKu0A.ghY51xaaHQARN2MQU153gqr38klG',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:42',
                'updated_at' => '2026-02-01 13:55:42',
            ],
            [
                'legacy_id' => 13,
                'name' => 'Lena Wanjiru',
                'email' => 'eastinnovation@africanunion.org',
                'password' => '$2y$12$dXtZw0H/WElErrULKDgvseUDRnr1YT.vmg19w1ceMtMi440tEg3Iy',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:43',
                'updated_at' => '2026-02-01 13:55:43',
            ],
            [
                'legacy_id' => 14,
                'name' => 'Stephen Amoakoh Donkor',
                'email' => 'digital@africanunion.org',
                'password' => '$2y$12$hlcaNLmWQ8UoaaQv46SwrudqhlqUl/K8hF.vR/NszaDBIkPZwcjbK',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:43',
                'updated_at' => '2026-02-01 13:55:43',
            ],
            [
                'legacy_id' => 15,
                'name' => 'David Nzadon',
                'email' => 'energy@africanunion.org',
                'password' => '$2y$12$PhOQyUtZWgbK8DP9mrpkFuqQzghuYXWwzk.yY9I.YjUGmwq7jngRW',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:43',
                'updated_at' => '2026-02-01 13:55:43',
            ],
            [
                'legacy_id' => 16,
                'name' => 'Amina Essien',
                'email' => 'health@africanunion.org',
                'password' => '$2y$12$6Lpy3JMSaiXvVWx0y5ITa.3W8SeznKALuo5v8XXqFGZhbCcwC9GQm',
                'password_changed_at' => null,
                'user_type' => 'funding_partner',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 13:55:43',
                'updated_at' => '2026-02-01 13:55:43',
            ],
            [
                'legacy_id' => 17,
                'name' => 'David Nzadon',
                'email' => 'nzadond@africanuion.org',
                'password' => '$2y$12$5RT3p5.V0AeTt.d6eZPH0eepqgLfLmm8m3iPnAVVJ6djZgjmKtzh6',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 1,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 14:04:35',
                'updated_at' => '2026-02-01 14:04:35',
            ],
            [
                'legacy_id' => 18,
                'name' => 'Themba Gilbert Chirwa',
                'email' => 'tchirwa@gmail.com',
                'password' => '$2y$12$pgWmrfVwloMPIAuU7T55Ie0kgg7/802iwKztQHosxfE6UE6YbqLU6',
                'password_changed_at' => '2026-02-02 10:10:19',
                'user_type' => 'staff',
                'must_change_password' => 0,
                'otp_verified_at' => '2026-02-02 10:11:55',
                'role_id' => 1,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 14:04:55',
                'updated_at' => '2026-02-02 10:11:55',
            ],
            [
                'legacy_id' => 19,
                'name' => 'David Nzadon',
                'email' => 'Tufenzadon@gmail.com',
                'password' => '$2y$12$rPTtiSJQPB/m7rbfFfcbTOp69IJkLtdDexjypL0RvHWBkIeeEwSDm',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 1,
                'governance_node_id' => 2,
                'created_at' => '2026-02-01 14:05:41',
                'updated_at' => '2026-02-01 14:05:41',
            ],
            [
                'legacy_id' => 20,
                'name' => 'Stephen Amoakoh Donkor',
                'email' => 'stephendonkor003@gmail.com',
                'password' => '$2y$12$gObkus8d.O7xdGnfNYSi4OLj6HJKa0pZ3uNcj/AY7x1m744K3wMQ.',
                'password_changed_at' => '2026-02-01 15:18:52',
                'user_type' => 'funding_partner',
                'must_change_password' => 0,
                'otp_verified_at' => null,
                'role_id' => 10,
                'governance_node_id' => null,
                'created_at' => '2026-02-01 15:17:32',
                'updated_at' => '2026-02-01 15:18:52',
            ],
            [
                'legacy_id' => 21,
                'name' => 'Stephen Amoakoh Donkor',
                'email' => 'consult.pmcgh@gmail.com',
                'password' => '$2y$12$qBo0Hn2B.NlB8d.t78G1L.zQGZkQRtbtrkp02bZHSYA5bK.UW6v3m',
                'password_changed_at' => '2026-02-02 10:30:26',
                'user_type' => 'staff',
                'must_change_password' => 0,
                'otp_verified_at' => '2026-02-02 10:31:38',
                'role_id' => 11,
                'governance_node_id' => 2,
                'created_at' => '2026-02-02 10:29:41',
                'updated_at' => '2026-02-02 10:31:38',
            ],
            [
                'legacy_id' => 22,
                'name' => 'Adamu Mengesha Gamtessa',
                'email' => 'AdamuM@africanunion.org',
                'password' => '$2y$12$VKh39Y8V5hhjUcovjHAvkuEcXpXItAx6c2qEERD.bOSziLqchBEhm',
                'password_changed_at' => null,
                'user_type' => 'staff',
                'must_change_password' => 1,
                'otp_verified_at' => null,
                'role_id' => 11,
                'governance_node_id' => 2,
                'created_at' => '2026-02-02 10:41:55',
                'updated_at' => '2026-02-02 10:41:55',
            ],
            [
                'legacy_id' => 23,
                'name' => 'Adamu Mengesha Gamtessa',
                'email' => 'adamumengesha3@gmail.com',
                'password' => '$2y$12$5vQHddhlKNaG8I8fOAeohuj00DVLWFFUNTe/OYBZw23Tz61pz3ZKe',
                'password_changed_at' => '2026-02-03 08:35:13',
                'user_type' => 'staff',
                'must_change_password' => 0,
                'otp_verified_at' => null,
                'role_id' => 11,
                'governance_node_id' => 2,
                'created_at' => '2026-02-02 13:28:12',
                'updated_at' => '2026-02-03 08:35:13',
            ],
        ];

        $rows = [];
        foreach ($users as $user) {
            $roleName = self::ROLE_MAP[$user['role_id']] ?? null;
            if (!$roleName && !is_null($user['role_id'])) {
                $roleName = 'Legacy Role ' . $user['role_id'];
                $this->command?->warn("Unmapped legacy role_id {$user['role_id']} for {$user['email']}; using '{$roleName}'.");
            }

            $roleId = null;
            if ($roleName) {
                if (!isset($roles[$roleName])) {
                    $role = Role::firstOrCreate(['name' => $roleName]);
                    $roles[$roleName] = $role->id;
                }
                $roleId = $roles[$roleName];
            }

            $nodeId = null;
            if (!is_null($user['governance_node_id'])) {
                $nodeName = self::NODE_MAP[$user['governance_node_id']] ?? null;
                $nodeId = $nodeName ? ($nodes[$nodeName] ?? null) : null;
                if ($nodeName && !$nodeId) {
                    $this->command?->warn("Governance node '{$nodeName}' not found for {$user['email']}.");
                }
            }

            $rows[] = [
                'id' => $this->legacyUuid('users', $user['legacy_id']),
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'password_changed_at' => $user['password_changed_at'],
                'user_type' => $user['user_type'],
                'must_change_password' => (bool) $user['must_change_password'],
                'otp_verified_at' => $user['otp_verified_at'],
                'role_id' => $roleId,
                'governance_node_id' => $nodeId,
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ];
        }

        foreach ($rows as $row) {
            $existingUser = DB::table('users')
                ->where('id', $row['id'])
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($row['email'])])
                ->first();

            if ($existingUser) {
                $update = $row;
                unset($update['id'], $update['created_at']);

                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update($update);

                continue;
            }

            DB::table('users')->insert($row);
        }

        $adminRoleId = $roles['System Admin'] ?? null;
        if ($adminRoleId) {
            $adminUsers = User::where('role_id', $adminRoleId)->get();
            $permissionIds = Permission::pluck('id');
            foreach ($adminUsers as $adminUser) {
                $adminUser->permissions()->sync($permissionIds);
            }
        }

        $evaluationRoleId = $roles['Evaluation Evaluator'] ?? null;
        if ($evaluationRoleId) {
            $evaluationUsers = User::where('role_id', $evaluationRoleId)->get();
            $evaluationPermissions = Permission::whereIn('name', ['evaluations.evaluate'])->pluck('id');
            foreach ($evaluationUsers as $evaluationUser) {
                $evaluationUser->permissions()->syncWithoutDetaching($evaluationPermissions);
            }
        }
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
