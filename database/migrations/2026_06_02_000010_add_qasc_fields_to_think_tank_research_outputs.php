<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attp_think_tank_research_outputs', function (Blueprint $table) {
            if (! Schema::hasColumn('attp_think_tank_research_outputs', 'qasc_data')) {
                $table->json('qasc_data')->nullable();
            }

            if (! Schema::hasColumn('attp_think_tank_research_outputs', 'qasc_author_signature_path')) {
                $table->string('qasc_author_signature_path')->nullable();
            }

            if (! Schema::hasColumn('attp_think_tank_research_outputs', 'qasc_think_tank_signature_path')) {
                $table->string('qasc_think_tank_signature_path')->nullable();
            }

            if (! Schema::hasColumn('attp_think_tank_research_outputs', 'qasc_pdf_path')) {
                $table->string('qasc_pdf_path')->nullable();
            }

            if (! Schema::hasColumn('attp_think_tank_research_outputs', 'qasc_email_sent_at')) {
                $table->timestamp('qasc_email_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attp_think_tank_research_outputs', function (Blueprint $table) {
            foreach ([
                'qasc_data',
                'qasc_author_signature_path',
                'qasc_think_tank_signature_path',
                'qasc_pdf_path',
                'qasc_email_sent_at',
            ] as $column) {
                if (Schema::hasColumn('attp_think_tank_research_outputs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
