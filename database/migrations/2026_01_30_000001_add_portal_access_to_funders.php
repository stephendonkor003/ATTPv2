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
            $table->boolean('has_portal_access')->default(false)->after('currency')
                ->comment('Whether this funder has access to the partner portal');

            $table->foreignUuid('user_id')->nullable()->after('has_portal_access')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User account for portal access');

            $table->string('contact_person')->nullable()->after('user_id')
                ->comment('Primary contact person name');

            $table->string('contact_email')->nullable()->after('contact_person')
                ->comment('Contact email address');

            $table->string('contact_phone', 20)->nullable()->after('contact_email')
                ->comment('Contact phone number');

            $table->text('notes')->nullable()->after('contact_phone')
                ->comment('Additional notes about this funder');

            // Indexes for performance
            $table->index('has_portal_access');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('myb_funders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['has_portal_access']);
            $table->dropIndex(['user_id']);
            $table->dropColumn([
                'has_portal_access',
                'user_id',
                'contact_person',
                'contact_email',
                'contact_phone',
                'notes',
            ]);
        });
    }
};
