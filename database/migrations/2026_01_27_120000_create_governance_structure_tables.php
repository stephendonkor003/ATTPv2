<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Governance Levels - Defines the hierarchy levels (Organ → Commission → Department → Directorate → Division/Unit)
        Schema::create('myb_governance_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique()->comment('Unique identifier key');
            $table->string('name', 100)->comment('Display name of the level');
            $table->unsignedInteger('sort_order')->default(0)->index()->comment('Display order in hierarchy');
            $table->text('description')->nullable()->comment('Description of this level');
            $table->timestamps();

            // Index for efficient sorting queries
            $table->index('name');
        });

        // Governance Nodes - Individual organizational units/entities
        Schema::create('myb_governance_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('level_id')
                ->constrained('myb_governance_levels')
                ->onDelete('restrict') // Prevent deletion if nodes exist
                ->comment('Reference to governance level');
            $table->string('name', 200)->index()->comment('Name of the organizational unit');
            $table->string('code', 50)->nullable()->unique()->comment('Optional unique code/identifier');
            $table->text('description')->nullable()->comment('Description of the node');
            $table->enum('status', ['active', 'inactive', 'pending', 'archived'])->default('active')->comment('Current status');
            $table->date('effective_start')->nullable()->comment('When this node becomes effective');
            $table->date('effective_end')->nullable()->comment('When this node becomes inactive');
            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who created this node');
            $table->timestamps();
            $table->softDeletes(); // Enable soft deletes for data integrity

            // Composite indexes for common queries
            $table->index(['level_id', 'status']);
            $table->index(['status', 'effective_start']);
            $table->index(['level_id', 'status', 'effective_start']); // For hierarchy queries with date filtering
        });

        // Governance Reporting Lines - Defines relationships between nodes
        Schema::create('myb_governance_reporting_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_node_id')
                ->constrained('myb_governance_nodes')
                ->onDelete('cascade')
                ->comment('The subordinate node');
            $table->foreignUuid('parent_node_id')
                ->constrained('myb_governance_nodes')
                ->onDelete('cascade')
                ->comment('The superior/parent node');
            $table->enum('line_type', ['primary', 'dotted', 'advisory'])->default('primary')
                ->comment('Type of reporting relationship: primary (hierarchy), dotted (matrix), advisory (guidance)');
            $table->date('effective_start')->nullable()->comment('When this reporting line becomes active');
            $table->date('effective_end')->nullable()->comment('When this reporting line ends');
            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who created this reporting line');
            $table->timestamps();
            $table->softDeletes(); // Enable soft deletes for historical tracking

            // Prevent duplicate relationships (same child-parent-type combination)
            $table->unique(['child_node_id', 'parent_node_id', 'line_type', 'effective_start'], 'unique_reporting_line');

            // Composite indexes for common queries
            $table->index(['child_node_id', 'line_type']);
            $table->index(['parent_node_id', 'line_type']);
            $table->index(['line_type', 'effective_start']);
            $table->index(['child_node_id', 'effective_start', 'effective_end'], 'idx_reporting_child_dates'); // For date range queries
        });

        // Governance Node Assignments - Assigns users/employees to nodes with roles
        Schema::create('myb_governance_node_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')
                ->constrained('myb_governance_nodes')
                ->onDelete('cascade')
                ->comment('The organizational node');
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('The assigned employee/user');
            $table->string('role_title', 150)->nullable()->index()->comment('Job title or role in this node');
            $table->boolean('is_primary')->default(false)->comment('Is this the primary assignment for the user?');
            $table->date('effective_start')->nullable()->comment('Assignment start date');
            $table->date('effective_end')->nullable()->comment('Assignment end date');
            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who created this assignment');
            $table->timestamps();
            $table->softDeletes(); // Enable soft deletes for historical tracking

            // Prevent duplicate assignments (same user-node combination during same period)
            $table->index(['user_id', 'node_id', 'effective_start'], 'user_node_assignment');

            // Composite indexes for common queries
            $table->index(['node_id', 'is_primary']);
            $table->index(['user_id', 'is_primary']);
            $table->index(['node_id', 'effective_start', 'effective_end'], 'idx_assignment_node_dates'); // For date range queries
            $table->index(['user_id', 'effective_start', 'effective_end'], 'idx_assignment_user_dates'); // For user's assignment history
        });
    }

    public function down(): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        Schema::dropIfExists('myb_governance_node_assignments');
        Schema::dropIfExists('myb_governance_reporting_lines');
        Schema::dropIfExists('myb_governance_nodes');
        Schema::dropIfExists('myb_governance_levels');
    }
};
