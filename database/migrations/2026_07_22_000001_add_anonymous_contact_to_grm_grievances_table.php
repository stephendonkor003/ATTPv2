<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grm_grievances', function (Blueprint $table) {
            $table->string('anonymous_contact_method', 20)->nullable()->after('is_anonymous');
            $table->text('anonymous_contact_value')->nullable()->after('anonymous_contact_method');
        });
    }

    public function down(): void
    {
        Schema::table('grm_grievances', function (Blueprint $table) {
            $table->dropColumn(['anonymous_contact_method', 'anonymous_contact_value']);
        });
    }
};
