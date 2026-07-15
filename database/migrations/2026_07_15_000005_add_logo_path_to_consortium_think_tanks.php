<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
