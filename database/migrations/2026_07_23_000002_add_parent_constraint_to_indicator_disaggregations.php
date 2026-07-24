<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_indicator_disaggregations', function (Blueprint $table) {
            $table->foreign('parent_id', 'me_indicator_disaggregations_parent_foreign')
                ->references('id')
                ->on('me_indicator_disaggregations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('me_indicator_disaggregations', function (Blueprint $table) {
            $table->dropForeign('me_indicator_disaggregations_parent_foreign');
        });
    }
};
