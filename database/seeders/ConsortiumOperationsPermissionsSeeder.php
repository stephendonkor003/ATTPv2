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
            ['name' => 'consortiums.analysis.view', 'module' => 'Consortium Operations', 'description' => 'View consortium and think tank graphical comparison analytics'],
            ['name' => 'consortiums.manage', 'module' => 'Consortium Operations', 'description' => 'Create and manage consortia, think tank members, and workplans'],
            ['name' => 'consortiums.reports.submit', 'module' => 'Consortium Operations', 'description' => 'Submit think tank consortium activity reports'],
            ['name' => 'consortiums.reports.review', 'module' => 'Consortium Operations', 'description' => 'Review and approve consortium activity reports'],
            ['name' => 'consortiums.finance.manage', 'module' => 'Consortium Operations', 'description' => 'Manage consortium allocations, disbursements, and expense reviews'],
            ['name' => 'consortiums.disbursements.request', 'module' => 'Consortium Operations', 'description' => 'Request consortium fund disbursements'],
            ['name' => 'consortiums.expenses.submit', 'module' => 'Consortium Operations', 'description' => 'Submit consortium expense reports'],
            ['name' => 'consortiums.risks.manage', 'module' => 'Consortium Operations', 'description' => 'Create and manage consortium risk flags'],
            ['name' => 'think_tanks.directory.view', 'module' => 'Think Tank Management', 'description' => 'View the think tank directory and profiles'],
            ['name' => 'think_tanks.directory.create', 'module' => 'Think Tank Management', 'description' => 'Create think tank directory profiles'],
            ['name' => 'think_tanks.directory.edit', 'module' => 'Think Tank Management', 'description' => 'Edit think tank directory profiles'],
            ['name' => 'think_tanks.funding.view', 'module' => 'Think Tank Management', 'description' => 'View Funding to Think Tanks dashboard'],
            ['name' => 'think_tanks.funding.transfer.create', 'module' => 'Think Tank Management', 'description' => 'Record transfers to think tanks'],
            ['name' => 'think_tanks.funding.transfer.edit', 'module' => 'Think Tank Management', 'description' => 'Edit recorded think tank transfer information'],
            ['name' => 'think_tanks.funding.history.view', 'module' => 'Think Tank Management', 'description' => 'View think tank transfer history and transaction details'],
            ['name' => 'think_tank.portal.access', 'module' => 'Think Tank Portal', 'description' => 'Access the dedicated think tank portal'],
            ['name' => 'think_tank.dashboard.download', 'module' => 'Think Tank Portal', 'description' => 'Download think tank dashboard report PDFs'],
            ['name' => 'think_tank.reports.view', 'module' => 'Think Tank Portal', 'description' => 'View think tank activity reporting dashboard'],
            ['name' => 'think_tank.reports.download', 'module' => 'Think Tank Portal', 'description' => 'Download think tank activity reporting PDFs'],
            ['name' => 'think_tank.reports.submit', 'module' => 'Think Tank Portal', 'description' => 'Submit activity reports from a think tank portal'],
            ['name' => 'think_tank.me.view', 'module' => 'Think Tank Portal', 'description' => 'View M&E data collections assigned to the think tank'],
            ['name' => 'think_tank.me.submit', 'module' => 'Think Tank Portal', 'description' => 'Save and submit assigned M&E data collections'],
            ['name' => 'think_tank.me.reports.view', 'module' => 'Think Tank Portal', 'description' => 'View performance reports owned by the think tank or implementing partner'],
            ['name' => 'think_tank.me.reports.manage', 'module' => 'Think Tank Portal', 'description' => 'Create and edit draft performance reports for assigned indicators'],
            ['name' => 'think_tank.me.reports.submit', 'module' => 'Think Tank Portal', 'description' => 'Submit complete performance reports to the Secretariat or M&E Officer'],
            ['name' => 'think_tank.me.notifications.view', 'module' => 'Think Tank Portal', 'description' => 'View reporting deadlines, review decisions and M&E reminders'],
            ['name' => 'think_tank.finance.view', 'module' => 'Think Tank Portal', 'description' => 'View think tank finance records and funding transfers'],
            ['name' => 'think_tank.finance.manage', 'module' => 'Think Tank Portal', 'description' => 'Manage think tank finance confirmations and records'],
            ['name' => 'think_tank.procurement_plans.view', 'module' => 'Think Tank Portal', 'description' => 'View procurement plans for the assigned think tank'],
            ['name' => 'think_tank.procurement_plans.manage', 'module' => 'Think Tank Portal', 'description' => 'Create and maintain procurement plans for the assigned think tank'],
            ['name' => 'think_tank.team.manage', 'module' => 'Think Tank Portal', 'description' => 'Manage portal staff and access levels for the assigned think tank'],
            ['name' => 'think_tank.research.view', 'module' => 'Think Tank Portal', 'description' => 'View think tank research output dashboard'],
            ['name' => 'think_tank.research.download', 'module' => 'Think Tank Portal', 'description' => 'Download think tank research output PDFs'],
            ['name' => 'think_tank.research.submit', 'module' => 'Think Tank Portal', 'description' => 'Submit research outputs from a think tank portal'],
            ['name' => 'think_tank.procurement.view', 'module' => 'Think Tank Portal', 'description' => 'View think tank procurement dashboard and analysis'],
            ['name' => 'think_tank.procurement.download', 'module' => 'Think Tank Portal', 'description' => 'Download think tank procurement dashboard PDFs'],
            ['name' => 'think_tank.procurement.manage', 'module' => 'Think Tank Portal', 'description' => 'Create procurement plans and opportunities from a think tank portal'],
            ['name' => 'think_tank.procurement.evaluate', 'module' => 'Think Tank Portal', 'description' => 'Evaluate procurement applications in a think tank portal'],
            ['name' => 'think_tank.procurement.select', 'module' => 'Think Tank Portal', 'description' => 'Select winning procurement applications in a think tank portal'],
        ];

        $created = collect();
        foreach ($permissions as $permission) {
            $created->push(Permission::updateOrCreate(['name' => $permission['name']], $permission));
        }

        if ($deprecatedPermission = Permission::where('name', 'partner.runtime_overview.view')->first()) {
            $deprecatedPermission->roles()->detach();
            $deprecatedPermission->delete();
        }

        $this->syncRole('System Admin', $created->pluck('id')->all(), true);
        $this->syncRole('Finance Manager', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.analysis.view',
            'consortiums.finance.manage',
            'consortiums.disbursements.request',
            'consortiums.expenses.submit',
            'think_tanks.directory.view',
            'think_tanks.funding.view',
            'think_tanks.funding.transfer.create',
            'think_tanks.funding.transfer.edit',
            'think_tanks.funding.history.view',
        ])->pluck('id')->all(), true);
        $this->syncRole('Finance Officer', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.analysis.view',
            'consortiums.finance.manage',
            'think_tanks.directory.view',
            'think_tanks.funding.view',
            'think_tanks.funding.transfer.create',
            'think_tanks.funding.transfer.edit',
            'think_tanks.funding.history.view',
        ])->pluck('id')->all(), true);
        $this->syncRole('Evaluation Evaluator', $created->whereIn('name', [
            'consortiums.view',
            'consortiums.analysis.view',
            'consortiums.reports.review',
            'consortiums.risks.manage',
            'think_tanks.directory.view',
        ])->pluck('id')->all(), true);
        Role::updateOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank consortium member user who can submit reports, requests, and expenses']
        );

        $this->syncRole('Think Tank User', $created->whereIn('name', [
            'think_tank.portal.access',
            'think_tank.dashboard.download',
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
