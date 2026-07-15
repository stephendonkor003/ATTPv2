<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grm_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable()->constrained('myb_programs')->nullOnDelete();
            $table->foreignUuid('governance_node_id')->nullable()->constrained('myb_governance_nodes')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->default('#0f766e');
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('response_due_hours')->nullable();
            $table->unsignedInteger('resolution_due_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['program_id', 'is_active']);
            $table->index(['governance_node_id', 'is_active']);
        });

        Schema::create('grm_escalation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable()->constrained('myb_programs')->nullOnDelete();
            $table->foreignUuid('governance_node_id')->nullable()->constrained('myb_governance_nodes')->nullOnDelete();
            $table->foreignUuid('level_id')->nullable()->constrained('grm_levels')->nullOnDelete();
            $table->unsignedInteger('response_due_hours')->default(24);
            $table->unsignedInteger('resolution_due_hours')->default(120);
            $table->unsignedInteger('reminder_after_hours')->default(24);
            $table->unsignedInteger('reminder_interval_hours')->default(24);
            $table->unsignedInteger('escalate_after_hours')->default(72);
            $table->string('escalation_email')->nullable();
            $table->string('auto_response_subject')->nullable();
            $table->text('auto_response_body')->nullable();
            $table->string('reminder_subject')->nullable();
            $table->text('reminder_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['program_id', 'level_id', 'is_active']);
            $table->index(['governance_node_id', 'is_active']);
        });

        Schema::create('grm_grievances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('case_number')->unique();
            $table->foreignUuid('program_id')->nullable()->constrained('myb_programs')->nullOnDelete();
            $table->foreignUuid('governance_node_id')->nullable()->constrained('myb_governance_nodes')->nullOnDelete();
            $table->foreignUuid('level_id')->nullable()->constrained('grm_levels')->nullOnDelete();
            $table->foreignUuid('escalation_rule_id')->nullable()->constrained('grm_escalation_rules')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('submitter_phone')->nullable();
            $table->string('channel')->default('portal');
            $table->string('subject');
            $table->longText('description');
            $table->string('status')->default('submitted');
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('due_response_at')->nullable();
            $table->timestamp('due_resolution_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('last_escalated_at')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'status']);
            $table->index(['governance_node_id', 'status']);
            $table->index(['level_id', 'status']);
            $table->index('submitted_at');
        });

        Schema::create('grm_grievance_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grievance_id')->constrained('grm_grievances')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['grievance_id', 'event_type']);
        });

        $this->seedDefaultLevels();
        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('grm_grievance_events');
        Schema::dropIfExists('grm_grievances');
        Schema::dropIfExists('grm_escalation_rules');
        Schema::dropIfExists('grm_levels');
    }

    private function seedDefaultLevels(): void
    {
        $now = now();
        $levels = [
            ['name' => 'Low', 'slug' => 'low', 'color' => '#16a34a', 'priority' => 1, 'response_due_hours' => 72, 'resolution_due_hours' => 240],
            ['name' => 'Medium', 'slug' => 'medium', 'color' => '#ca8a04', 'priority' => 2, 'response_due_hours' => 48, 'resolution_due_hours' => 168],
            ['name' => 'High', 'slug' => 'high', 'color' => '#dc2626', 'priority' => 3, 'response_due_hours' => 24, 'resolution_due_hours' => 120],
            ['name' => 'Critical', 'slug' => 'critical', 'color' => '#7f1d1d', 'priority' => 4, 'response_due_hours' => 12, 'resolution_due_hours' => 72],
        ];

        foreach ($levels as $level) {
            DB::table('grm_levels')->updateOrInsert(
                ['program_id' => null, 'slug' => $level['slug']],
                [
                    'id' => DB::table('grm_levels')->whereNull('program_id')->where('slug', $level['slug'])->value('id') ?: (string) Str::uuid(),
                    'name' => $level['name'],
                    'color' => $level['color'],
                    'priority' => $level['priority'],
                    'response_due_hours' => $level['response_due_hours'],
                    'resolution_due_hours' => $level['resolution_due_hours'],
                    'is_active' => true,
                    'created_at' => DB::table('grm_levels')->whereNull('program_id')->where('slug', $level['slug'])->value('created_at') ?: $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        $now = now();
        $permissions = [
            'grm.submit' => 'Submit grievances and generate case numbers',
            'grm.view' => 'View grievance logs and case details',
            'grm.configure' => 'Manage grievance levels and configuration',
            'grm.escalations' => 'Manage grievance escalation timing, reminders, and email responses',
            'grm.reports' => 'View grievance metrics and reports',
        ];

        foreach ($permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => DB::table('permissions')->where('name', $name)->value('id') ?: (string) Str::uuid(),
                    'module' => 'GRM',
                    'description' => $description,
                    'created_at' => DB::table('permissions')->where('name', $name)->value('created_at') ?: $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($permissions))
            ->pluck('id');

        $roleNames = [
            'System Admin',
            'Super Admin',
            'Portfolio Manager',
            'Portfolio Coordinator',
            'Monitoring and Evaluation Manager',
        ];

        DB::table('roles')
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->each(function ($roleId) use ($permissionIds) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            });
    }
};
