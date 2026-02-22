<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_reporting_frequencies', function (Blueprint $table) {
            if (!Schema::hasColumn('me_reporting_frequencies', 'interval_unit')) {
                $table->string('interval_unit', 40)
                    ->nullable()
                    ->after('code')
                    ->comment('Frequency unit: second, minute, hour, day, week, month, quarterly, year, annual, quinquennial, once');
            }

            if (!Schema::hasColumn('me_reporting_frequencies', 'interval_value')) {
                $table->unsignedInteger('interval_value')
                    ->nullable()
                    ->after('interval_unit')
                    ->comment('How many interval units between reports');
            }
        });

        if (!Schema::hasTable('me_reporting_frequencies')) {
            return;
        }

        $rows = DB::table('me_reporting_frequencies')
            ->select('id', 'name', 'code', 'frequency_in_days', 'interval_unit', 'interval_value')
            ->get();

        foreach ($rows as $row) {
            if (!empty($row->interval_unit) && !empty($row->interval_value)) {
                continue;
            }

            $code = strtoupper(trim((string) $row->code));
            $name = strtolower(trim((string) $row->name));
            $days = (int) ($row->frequency_in_days ?? 0);

            $intervalUnit = 'day';
            $intervalValue = 1;

            if (str_contains($code, 'SECOND') || str_contains($name, 'second')) {
                $intervalUnit = 'second';
                $intervalValue = 1;
            } elseif (str_contains($code, 'MINUTE') || str_contains($name, 'minute')) {
                $intervalUnit = 'minute';
                $intervalValue = 1;
            } elseif (str_contains($code, 'HOUR') || str_contains($name, 'hour')) {
                $intervalUnit = 'hour';
                $intervalValue = 1;
            } elseif (str_contains($code, 'WEEK') || str_contains($name, 'week')) {
                $intervalUnit = 'week';
                $intervalValue = $days > 0 ? max(1, (int) round($days / 7)) : 1;
            } elseif (str_contains($code, 'MONTH') || str_contains($name, 'month')) {
                $intervalUnit = 'month';
                $intervalValue = $days > 0 ? max(1, (int) round($days / 30)) : 1;
            } elseif (str_contains($code, 'QUARTER') || str_contains($name, 'quarter')) {
                $intervalUnit = 'quarterly';
                $intervalValue = $days > 0 ? max(1, (int) round($days / 90)) : 1;
            } elseif (str_contains($code, 'QUINQ') || str_contains($name, 'quinquen')) {
                $intervalUnit = 'quinquennial';
                $intervalValue = $days > 0 ? max(1, (int) round($days / (365 * 5))) : 1;
            } elseif (str_contains($code, 'ANNUAL') || str_contains($name, 'annual')) {
                $intervalUnit = 'annual';
                $intervalValue = $days > 0 ? max(1, (int) round($days / 365)) : 1;
            } elseif (str_contains($code, 'YEAR') || str_contains($name, 'year')) {
                $intervalUnit = 'year';
                $intervalValue = $days > 0 ? max(1, (int) round($days / 365)) : 1;
            } elseif (str_contains($code, 'ONCE') || str_contains($name, 'once')) {
                $intervalUnit = 'once';
                $intervalValue = null;
            } elseif ($days >= (365 * 5)) {
                $intervalUnit = 'quinquennial';
                $intervalValue = max(1, (int) round($days / (365 * 5)));
            } elseif ($days >= 365) {
                $intervalUnit = 'annual';
                $intervalValue = max(1, (int) round($days / 365));
            } elseif ($days >= 90) {
                $intervalUnit = 'quarterly';
                $intervalValue = max(1, (int) round($days / 90));
            } elseif ($days >= 30) {
                $intervalUnit = 'month';
                $intervalValue = max(1, (int) round($days / 30));
            } elseif ($days >= 7) {
                $intervalUnit = 'week';
                $intervalValue = max(1, (int) round($days / 7));
            } elseif ($days > 0) {
                $intervalUnit = 'day';
                $intervalValue = $days;
            }

            DB::table('me_reporting_frequencies')
                ->where('id', $row->id)
                ->update([
                    'interval_unit' => $intervalUnit,
                    'interval_value' => $intervalValue,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('me_reporting_frequencies', function (Blueprint $table) {
            if (Schema::hasColumn('me_reporting_frequencies', 'interval_value')) {
                $table->dropColumn('interval_value');
            }
            if (Schema::hasColumn('me_reporting_frequencies', 'interval_unit')) {
                $table->dropColumn('interval_unit');
            }
        });
    }
};

