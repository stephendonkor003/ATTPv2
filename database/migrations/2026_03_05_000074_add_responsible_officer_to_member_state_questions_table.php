<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_member_state_questions')) {
            return;
        }

        $hasResponsibleOfficerId = Schema::hasColumn('myb_member_state_questions', 'responsible_officer_id');
        $hasResponsibleOfficerEmail = Schema::hasColumn('myb_member_state_questions', 'responsible_officer_email');

        if ($hasResponsibleOfficerId && $hasResponsibleOfficerEmail) {
            return;
        }

        Schema::table('myb_member_state_questions', function (Blueprint $table) use ($hasResponsibleOfficerId, $hasResponsibleOfficerEmail) {
            if (!$hasResponsibleOfficerId) {
                $table->foreignUuid('responsible_officer_id')
                    ->nullable()
                    ->after('status');

                $table->foreign('responsible_officer_id', 'ms_questions_resp_officer_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (!$hasResponsibleOfficerEmail) {
                $table->string('responsible_officer_email')
                    ->nullable()
                    ->after('responsible_officer_id');
            }

            $table->index(['member_state_id', 'responsible_officer_id'], 'ms_questions_state_officer_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('myb_member_state_questions')) {
            return;
        }

        Schema::table('myb_member_state_questions', function (Blueprint $table) {
            if (Schema::hasColumn('myb_member_state_questions', 'responsible_officer_id')) {
                $table->dropForeign('ms_questions_resp_officer_fk');
            }

            if (Schema::hasColumn('myb_member_state_questions', 'responsible_officer_email')) {
                $table->dropColumn('responsible_officer_email');
            }

            if (Schema::hasColumn('myb_member_state_questions', 'responsible_officer_id')) {
                $table->dropColumn('responsible_officer_id');
            }
        });
    }
};
