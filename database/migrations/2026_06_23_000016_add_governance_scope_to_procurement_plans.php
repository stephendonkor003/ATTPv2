<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('myb_procurement_program_plans') && ! Schema::hasColumn('myb_procurement_program_plans', 'governance_node_id')) {
            Schema::table('myb_procurement_program_plans', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
                $table->index('governance_node_id', 'procurement_program_plans_node_idx');
            });
        }

        if (Schema::hasTable('myb_procurement_plans') && ! Schema::hasColumn('myb_procurement_plans', 'governance_node_id')) {
            Schema::table('myb_procurement_plans', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
                $table->index('governance_node_id', 'procurement_plans_node_idx');
            });
        }

        $this->dropGlobalProgramPlanNameUnique();
        $this->backfillProgramPlanNodesFromCreators();
        $this->backfillProcurementPlanNodes();
        $this->backfillProgramPlanNodesFromItems();
        $this->addScopedProgramPlanNameIndex();
    }

    public function down(): void
    {
        if (Schema::hasTable('myb_procurement_plans') && Schema::hasColumn('myb_procurement_plans', 'governance_node_id')) {
            Schema::table('myb_procurement_plans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('governance_node_id');
            });
        }

        if (Schema::hasTable('myb_procurement_program_plans') && Schema::hasColumn('myb_procurement_program_plans', 'governance_node_id')) {
            Schema::table('myb_procurement_program_plans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('governance_node_id');
            });
        }

        try {
            Schema::table('myb_procurement_program_plans', function (Blueprint $table) {
                $table->unique('name', 'myb_procurement_program_plans_name_unique');
            });
        } catch (Throwable $e) {
            // Existing duplicate names can prevent restoring the old global unique index.
        }
    }

    private function dropGlobalProgramPlanNameUnique(): void
    {
        try {
            Schema::table('myb_procurement_program_plans', function (Blueprint $table) {
                $table->dropUnique('myb_procurement_program_plans_name_unique');
            });
        } catch (Throwable $e) {
            // The index may already be absent in environments created from newer snapshots.
        }
    }

    private function addScopedProgramPlanNameIndex(): void
    {
        try {
            Schema::table('myb_procurement_program_plans', function (Blueprint $table) {
                $table->index(['governance_node_id', 'name'], 'procurement_program_plans_node_name_idx');
            });
        } catch (Throwable $e) {
            // Index may already exist after a partially-applied deploy.
        }
    }

    private function backfillProgramPlanNodesFromCreators(): void
    {
        if (! Schema::hasColumn('myb_procurement_program_plans', 'governance_node_id')) {
            return;
        }

        $nodeByUser = $this->nodeByUser();

        DB::table('myb_procurement_program_plans')
            ->select(['id', 'created_by'])
            ->whereNull('governance_node_id')
            ->get()
            ->each(function ($plan) use ($nodeByUser) {
                $nodeId = $nodeByUser[(string) $plan->created_by] ?? null;

                if ($nodeId) {
                    DB::table('myb_procurement_program_plans')
                        ->where('id', $plan->id)
                        ->update(['governance_node_id' => $nodeId]);
                }
            });
    }

    private function backfillProcurementPlanNodes(): void
    {
        if (! Schema::hasColumn('myb_procurement_plans', 'governance_node_id')) {
            return;
        }

        $nodeByUser = $this->nodeByUser();
        $subActivities = Schema::hasTable('myb_sub_activities')
            ? DB::table('myb_sub_activities')->select(['id', 'governance_node_id', 'activity_id'])->get()->keyBy('id')
            : collect();
        $activities = Schema::hasTable('myb_activities')
            ? DB::table('myb_activities')->select(['id', 'governance_node_id', 'project_id'])->get()->keyBy('id')
            : collect();
        $projects = Schema::hasTable('myb_projects')
            ? DB::table('myb_projects')->select(['id', 'governance_node_id'])->get()->keyBy('id')
            : collect();
        $programPlanNodes = Schema::hasColumn('myb_procurement_program_plans', 'governance_node_id')
            ? DB::table('myb_procurement_program_plans')->pluck('governance_node_id', 'id')->all()
            : [];

        DB::table('myb_procurement_plans')
            ->select(['id', 'sub_activity_id', 'activity_id', 'program_plan_id', 'created_by'])
            ->whereNull('governance_node_id')
            ->get()
            ->each(function ($plan) use ($nodeByUser, $subActivities, $activities, $projects, $programPlanNodes) {
                $nodeId = null;

                if ($plan->sub_activity_id && $subActivities->has($plan->sub_activity_id)) {
                    $subActivity = $subActivities->get($plan->sub_activity_id);
                    $nodeId = $subActivity->governance_node_id;

                    if (! $nodeId && $subActivity->activity_id && $activities->has($subActivity->activity_id)) {
                        $activity = $activities->get($subActivity->activity_id);
                        $nodeId = $activity->governance_node_id
                            ?: ($activity->project_id && $projects->has($activity->project_id)
                                ? $projects->get($activity->project_id)->governance_node_id
                                : null);
                    }
                }

                if (! $nodeId && $plan->activity_id && $activities->has($plan->activity_id)) {
                    $activity = $activities->get($plan->activity_id);
                    $nodeId = $activity->governance_node_id
                        ?: ($activity->project_id && $projects->has($activity->project_id)
                            ? $projects->get($activity->project_id)->governance_node_id
                            : null);
                }

                $nodeId ??= $programPlanNodes[$plan->program_plan_id] ?? null;
                $nodeId ??= $nodeByUser[(string) $plan->created_by] ?? null;

                if ($nodeId) {
                    DB::table('myb_procurement_plans')
                        ->where('id', $plan->id)
                        ->update(['governance_node_id' => $nodeId]);
                }
            });
    }

    private function backfillProgramPlanNodesFromItems(): void
    {
        if (
            ! Schema::hasColumn('myb_procurement_program_plans', 'governance_node_id')
            || ! Schema::hasColumn('myb_procurement_plans', 'governance_node_id')
        ) {
            return;
        }

        $nodeByProgramPlan = DB::table('myb_procurement_plans')
            ->select(['program_plan_id', 'governance_node_id'])
            ->whereNotNull('program_plan_id')
            ->whereNotNull('governance_node_id')
            ->get()
            ->groupBy('program_plan_id')
            ->map(fn ($rows) => $rows->first()->governance_node_id);

        DB::table('myb_procurement_program_plans')
            ->select(['id'])
            ->whereNull('governance_node_id')
            ->get()
            ->each(function ($plan) use ($nodeByProgramPlan) {
                $nodeId = $nodeByProgramPlan->get($plan->id);

                if ($nodeId) {
                    DB::table('myb_procurement_program_plans')
                        ->where('id', $plan->id)
                        ->update(['governance_node_id' => $nodeId]);
                }
            });
    }

    private function nodeByUser(): array
    {
        $nodes = Schema::hasTable('users') && Schema::hasColumn('users', 'governance_node_id')
            ? DB::table('users')->whereNotNull('governance_node_id')->pluck('governance_node_id', 'id')->all()
            : [];

        if (Schema::hasTable('myb_sectors')) {
            if (Schema::hasColumn('myb_sectors', 'portfolio_manager_user_id')) {
                DB::table('myb_sectors')
                    ->whereNotNull('governance_node_id')
                    ->whereNotNull('portfolio_manager_user_id')
                    ->pluck('governance_node_id', 'portfolio_manager_user_id')
                    ->each(fn ($nodeId, $userId) => $nodes[(string) $userId] = $nodeId);
            }

            if (Schema::hasColumn('myb_sectors', 'me_manager_user_id')) {
                DB::table('myb_sectors')
                    ->whereNotNull('governance_node_id')
                    ->whereNotNull('me_manager_user_id')
                    ->pluck('governance_node_id', 'me_manager_user_id')
                    ->each(fn ($nodeId, $userId) => $nodes[(string) $userId] = $nodeId);
            }
        }

        return array_filter($nodes);
    }
};
