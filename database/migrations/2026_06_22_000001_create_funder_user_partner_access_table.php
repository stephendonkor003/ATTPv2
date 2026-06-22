<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funder_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('funder_id')
                ->constrained('myb_funders')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignUuid('invited_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->unique(['funder_id', 'user_id']);
            $table->index(['user_id', 'funder_id']);
        });

        DB::table('myb_funders')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->select(['id', 'user_id', 'created_at', 'updated_at'])
            ->chunkById(100, function ($funders) {
                foreach ($funders as $funder) {
                    DB::table('funder_user')->updateOrInsert(
                        [
                            'funder_id' => $funder->id,
                            'user_id' => $funder->user_id,
                        ],
                        [
                            'is_primary' => true,
                            'invited_at' => $funder->created_at ?? now(),
                            'created_at' => $funder->created_at ?? now(),
                            'updated_at' => $funder->updated_at ?? now(),
                        ]
                    );
                }
            });

        $readOnlyPermissionNames = [
            'partner.dashboard.access' => 'Access partner portal dashboard',
            'partner.programs.view' => 'View funded programs',
            'partner.projects.view' => 'View projects under funded programs',
            'partner.budgets.view' => 'View budget commitments and expenditures',
            'partner.documents.view' => 'View and download program documents',
            'partner.requests.view' => 'View own information requests',
        ];

        foreach ($readOnlyPermissionNames as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'module' => 'partner_portal',
                    'description' => $description,
                ]
            );
        }

        Permission::updateOrCreate(
            ['name' => 'partner.workplan.review'],
            [
                'module' => 'partner_portal',
                'description' => 'Review funded work plan items from the partner portal',
            ]
        );

        $partnerRole = Role::where('name', 'Funding Partner')->first();
        $readOnlyPermissions = Permission::whereIn('name', array_keys($readOnlyPermissionNames))->pluck('id');

        if ($partnerRole && $readOnlyPermissions->isNotEmpty()) {
            $partnerRole->permissions()->sync($readOnlyPermissions);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funder_user');
    }
};
