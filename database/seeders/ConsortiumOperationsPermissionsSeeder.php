<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ConsortiumOperationsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'consortiums.view', 'module' => 'Consortium Operations', 'description' => 'View ATTP consortium operations dashboards'],
            ['name' => 'consortiums.manage', 'module' => 'Consortium Operations', 'description' => 'Create and manage consortia, think tank members, and workplans'],
            ['name' => 'consortiums.reports.submit', 'module' => 'Consortium Operations', 'description' => 'Submit think tank consortium activity reports'],
            ['name' => 'consortiums.reports.review', 'module' => 'Consortium Operations', 'description' => 'Review and approve consortium activity reports'],
            ['name' => 'consortiums.finance.manage', 'module' => 'Consortium Operations', 'description' => 'Manage consortium allocations, disbursements, and expense reviews'],
            ['name' => 'consortiums.disbursements.request', 'module' => 'Consortium Operations', 'description' => 'Request consortium fund disbursements'],
            ['name' => 'consortiums.expenses.submit', 'module' => 'Consortium Operations', 'description' => 'Submit consortium expense reports'],
            ['name' => 'consortiums.risks.manage', 'module' => 'Consortium Operations', 'description' => 'Create and manage consortium risk flags'],
            ['name' => 'partner.runtime_overview.view', 'module' => 'partner_portal', 'description' => 'View runtime overview of funded consortium implementation'],
            ['name' => 'think_tank.portal.access', 'module' => 'Think Tank Portal', 'description' => 'Access the dedicated think tank portal'],
            ['name' => 'think_tank.reports.submit', 'module' => 'Think Tank Portal', 'description' => 'Submit activity reports from a think tank portal'],
            ['name' => 'think_tank.research.submit', 'module' => 'Think Tank Portal', 'description' => 'Submit research outputs from a think tank portal'],
            ['name' => 'think_tank.procurement.manage', 'module' => 'Think Tank Portal', 'description' => 'Create procurement plans and opportunities from a think tank portal'],
            ['name' => 'think_tank.procurement.evaluate', 'module' => 'Think Tank Portal', 'description' => 'Evaluate procurement applications in a think tank portal'],
            ['name' => 'think_tank.procurement.select', 'module' => 'Think Tank Portal', 'description' => 'Select winning procurement applications in a think tank portal'],
        ];

        $created = collect();
        foreach ($permissions as $permission) {
            $created->push(Permission::updateOrCreate(['name' => $permission['name']], $permission));
        }

        $this->syncRole('System Admin', $created->pluck('id')->all(), true);
        $this->syncRole('Finance Manager', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.finance.manage',
            'consortiums.disbursements.request',
            'consortiums.expenses.submit',
        ])->pluck('id')->all(), true);
        $this->syncRole('Finance Officer', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.finance.manage',
        ])->pluck('id')->all(), true);
        $this->syncRole('Evaluation Evaluator', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.reports.review',
            'consortiums.risks.manage',
        ])->pluck('id')->all(), true);
        $this->syncRole('Funding Partner', $created->whereIn('name', [
            'partner.runtime_overview.view',
        ])->pluck('id')->all(), true);

        Role::updateOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank consortium member user who can submit reports, requests, and expenses']
        );

        $this->syncRole('Think Tank User', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.reports.submit',
            'consortiums.disbursements.request',
            'consortiums.expenses.submit',
            'think_tank.portal.access',
            'think_tank.reports.submit',
            'think_tank.research.submit',
            'think_tank.procurement.manage',
            'think_tank.procurement.evaluate',
            'think_tank.procurement.select',
        ])->pluck('id')->all(), false);
    }

    private function syncRole(string $roleName, array $permissionIds, bool $merge): void
    {
        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            return;
        }

        $ids = collect($permissionIds);
        if ($merge) {
            $ids = $role->permissions()->pluck('permissions.id')->merge($ids)->unique();
        }

        $role->permissions()->sync($ids->values()->all());
    }
}
