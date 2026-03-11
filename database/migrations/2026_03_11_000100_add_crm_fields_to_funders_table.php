<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('myb_funders', function (Blueprint $table) {
            $table->foreignUuid('relationship_manager_id')->nullable()->after('user_id')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Internal user responsible for managing this partner');

            $table->string('partnership_status')->nullable()->after('relationship_manager_id')
                ->comment('Lifecycle stage: prospect, active, at_risk, dormant, closed');

            $table->date('partnership_started_at')->nullable()->after('partnership_status')
                ->comment('Date the partnership formally started');

            $table->date('next_follow_up_at')->nullable()->after('partnership_started_at')
                ->comment('Next planned follow-up date');

            $table->timestamp('last_contact_at')->nullable()->after('next_follow_up_at')
                ->comment('Most recent communication date/time logged by staff');

            $table->string('last_contact_subject')->nullable()->after('last_contact_at')
                ->comment('Subject or topic of the latest communication');

            $table->string('last_contact_status')->nullable()->after('last_contact_subject')
                ->comment('Communication state: pending, attended, follow_up_needed');

            $table->foreignUuid('last_contact_user_id')->nullable()->after('last_contact_status')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Internal user who handled the latest communication');

            $table->text('last_contact_notes')->nullable()->after('last_contact_user_id')
                ->comment('Detailed notes on the latest communication');

            $table->index('relationship_manager_id');
            $table->index('partnership_status');
            $table->index('next_follow_up_at');
            $table->index('last_contact_at');
            $table->index('last_contact_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('myb_funders', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
            $table->dropForeign(['last_contact_user_id']);
            $table->dropIndex(['relationship_manager_id']);
            $table->dropIndex(['partnership_status']);
            $table->dropIndex(['next_follow_up_at']);
            $table->dropIndex(['last_contact_at']);
            $table->dropIndex(['last_contact_user_id']);
            $table->dropColumn([
                'relationship_manager_id',
                'partnership_status',
                'partnership_started_at',
                'next_follow_up_at',
                'last_contact_at',
                'last_contact_subject',
                'last_contact_status',
                'last_contact_user_id',
                'last_contact_notes',
            ]);
        });
    }
};
