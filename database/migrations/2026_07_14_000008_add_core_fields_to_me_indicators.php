<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addIndicatorCode = ! Schema::hasColumn('myb_indicators', 'indicator_code');
        $addResponsibleUser = ! Schema::hasColumn('myb_indicators', 'responsible_user_id');
        if ($addIndicatorCode || $addResponsibleUser) {
            Schema::table('myb_indicators', function (Blueprint $table) use ($addIndicatorCode, $addResponsibleUser) {
                if ($addIndicatorCode) {
                    $table->string('indicator_code', 32)
                        ->nullable()
                        ->after('id');
                }

                if ($addResponsibleUser) {
                    $table->foreignUuid('responsible_user_id')
                        ->nullable()
                        ->after('responsible_party')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasColumn('me_indicator_targets', 'target_context')) {
            Schema::table('me_indicator_targets', function (Blueprint $table) {
                $table->string('target_context', 24)
                    ->nullable()
                    ->after('indicator_id');
            });
        }

        $usedCodes = DB::table('myb_indicators')
            ->whereNotNull('indicator_code')
            ->where('indicator_code', '<>', '')
            ->pluck('indicator_code')
            ->mapWithKeys(fn ($code) => [(string) $code => true])
            ->all();
        DB::table('myb_indicators')
            ->where(function ($query) {
                $query->whereNull('indicator_code')
                    ->orWhere('indicator_code', '');
            })
            ->orderBy('id')
            ->select(['id'])
            ->each(function (object $indicator) use (&$usedCodes): void {
                $token = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', (string) $indicator->id));
                $base = 'IND-'.substr(str_pad($token, 10, '0'), 0, 10);
                $code = $base;
                $suffix = 2;

                while (isset($usedCodes[$code])) {
                    $code = substr($base, 0, 28).'-'.$suffix;
                    $suffix++;
                }

                DB::table('myb_indicators')
                    ->where('id', $indicator->id)
                    ->update(['indicator_code' => $code]);
                $usedCodes[$code] = true;
            });

        DB::table('myb_indicators')
            ->whereNull('responsible_user_id')
            ->whereNotNull('responsible_party')
            ->orderBy('id')
            ->select(['id', 'responsible_party'])
            ->each(function (object $indicator): void {
                $responsibleIds = json_decode((string) $indicator->responsible_party, true);
                $responsibleUserId = collect(is_array($responsibleIds) ? $responsibleIds : [])
                    ->first(fn ($id) => is_scalar($id)
                        && trim((string) $id) !== ''
                        && DB::table('users')->where('id', (string) $id)->exists());

                if ($responsibleUserId) {
                    DB::table('myb_indicators')
                        ->where('id', $indicator->id)
                        ->update(['responsible_user_id' => (string) $responsibleUserId]);
                }
            });

        Schema::table('myb_indicators', function (Blueprint $table) {
            $table->unique('indicator_code', 'myb_indicators_indicator_code_unique');
        });

        Schema::table('me_indicator_targets', function (Blueprint $table) {
            $table->unique(
                ['indicator_id', 'target_context'],
                'me_indicator_targets_indicator_context_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('me_indicator_targets', function (Blueprint $table) {
            $table->dropUnique('me_indicator_targets_indicator_context_unique');
            $table->dropColumn('target_context');
        });

        Schema::table('myb_indicators', function (Blueprint $table) {
            $table->dropUnique('myb_indicators_indicator_code_unique');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn('indicator_code');
        });
    }
};
