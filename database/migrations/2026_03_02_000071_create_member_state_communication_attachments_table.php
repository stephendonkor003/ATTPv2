<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'myb_member_state_communication_attachments';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('communication_id');
                $table->string('file_path');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->uuid('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['communication_id', 'created_at'], 'ms_comm_att_comm_created_idx');
            });
        }

        if (
            Schema::hasTable($tableName)
            && Schema::hasColumn($tableName, 'communication_id')
            && !$this->foreignKeyExists($tableName, 'ms_comm_att_comm_fk')
        ) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('communication_id', 'ms_comm_att_comm_fk')
                    ->references('id')
                    ->on('myb_member_state_communications')
                    ->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable($tableName)
            && Schema::hasColumn($tableName, 'uploaded_by')
            && !$this->foreignKeyExists($tableName, 'ms_comm_att_user_fk')
        ) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('uploaded_by', 'ms_comm_att_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_member_state_communication_attachments');
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $tableName)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
