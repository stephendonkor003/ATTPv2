<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attp_consortium_think_tanks', 'portal_branding')) {
            Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
                $table->json('portal_branding')->nullable()->after('logo_path');
            });
        }

        if (! Schema::hasColumn('users', 'think_tank_portal_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('think_tank_portal_preferences')->nullable()->after('think_tank_access_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'think_tank_portal_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('think_tank_portal_preferences');
            });
        }

        if (Schema::hasColumn('attp_consortium_think_tanks', 'portal_branding')) {
            Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
                $table->dropColumn('portal_branding');
            });
        }
    }
};
