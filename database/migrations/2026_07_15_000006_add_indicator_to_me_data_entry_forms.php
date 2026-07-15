<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_data_entry_forms', function (Blueprint $table): void {
            $table->foreignUuid('indicator_id')
                ->nullable()
                ->after('portfolio_id')
                ->constrained('myb_indicators')
                ->nullOnDelete();

            $table->index(
                ['indicator_id', 'status'],
                'me_forms_indicator_status_idx'
            );
        });

        // Preserve the intent of existing single-indicator templates. Forms that
        // intentionally map several indicators stay unlinked until an owner
        // explicitly chooses the primary indicator in Template details.
        DB::table('me_data_entry_forms')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $form): void {
                $indicatorIds = DB::table('me_data_entry_form_fields')
                    ->where('form_id', $form->id)
                    ->whereNotNull('indicator_id')
                    ->distinct()
                    ->pluck('indicator_id');

                if ($indicatorIds->count() === 1) {
                    DB::table('me_data_entry_forms')
                        ->where('id', $form->id)
                        ->update(['indicator_id' => $indicatorIds->first()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('me_data_entry_forms', function (Blueprint $table): void {
            $table->dropIndex('me_forms_indicator_status_idx');
            $table->dropConstrainedForeignId('indicator_id');
        });
    }
};
