<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attp_think_tank_procurement_items')) {
            return;
        }

        Schema::table('attp_think_tank_procurement_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('attp_think_tank_procurement_items', 'step_activity_status')) {
                $table->string('step_activity_status', 20)->nullable()->index('attp_tt_proc_items_step_status_idx');
            }
            if (! Schema::hasColumn('attp_think_tank_procurement_items', 'step_status_updated_at')) {
                $table->timestamp('step_status_updated_at')->nullable();
            }
            if (! Schema::hasColumn('attp_think_tank_procurement_items', 'step_status_updated_by')) {
                $table->foreignUuid('step_status_updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        DB::table('attp_think_tank_procurement_items')
            ->whereNull('step_activity_status')
            ->select(['id', 'status', 'source_activity_status', 'source_payload', 'no_objection_recorded_at'])
            ->orderBy('id')
            ->get()
            ->each(function (object $item): void {
                $payload = is_string($item->source_payload)
                    ? json_decode($item->source_payload, true)
                    : (is_array($item->source_payload) ? $item->source_payload : []);
                $imported = trim((string) data_get($payload, 'source_statuses.activity_status'));
                $compatibility = trim((string) $item->source_activity_status);
                $hasRecordedNoObjection = in_array($item->status, ['no_objection_obtained', 'published'], true)
                    || filled($item->no_objection_recorded_at);
                $status = $hasRecordedNoObjection
                    ? 'Cleared'
                    : (in_array($imported, ['New', 'Returned', 'Cleared'], true)
                        ? $imported
                        : (in_array($compatibility, ['New', 'Returned', 'Cleared'], true)
                            ? $compatibility
                            : match ($item->status) {
                                'approved' => 'New',
                                default => null,
                            }));

                if ($status !== null) {
                    DB::table('attp_think_tank_procurement_items')
                        ->where('id', $item->id)
                        ->whereNull('step_activity_status')
                        ->update(['step_activity_status' => $status]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive: these fields may contain staff-recorded
        // STEP state that must survive an application rollback.
    }
};
