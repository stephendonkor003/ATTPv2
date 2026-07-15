<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'me_data_entry_form_fields';

    private const CHECK_CONSTRAINT = 'me_data_entry_form_fields_field_type_check';

    private const LEGACY_TYPES = [
        'number',
        'percentage',
        'text',
        'textarea',
        'date',
        'select',
        'checkbox',
    ];

    private const ALLOWED_TYPES = [
        'integer',
        'number',
        'percentage',
        'currency',
        'text',
        'textarea',
        'email',
        'phone',
        'url',
        'date',
        'time',
        'datetime',
        'month',
        'year',
        'select',
        'radio',
        'multiselect',
        'checkbox',
        'yes_no',
        'rating',
        'scale',
        'file',
        'image',
    ];

    /**
     * Safe legacy representations used before restoring the original constraint.
     * Existing answer values remain untouched.
     */
    private const LEGACY_TYPE_MAP = [
        'integer' => 'number',
        'currency' => 'number',
        'email' => 'text',
        'phone' => 'text',
        'url' => 'text',
        'time' => 'text',
        'datetime' => 'text',
        'month' => 'text',
        'year' => 'number',
        'radio' => 'select',
        'multiselect' => 'select',
        'yes_no' => 'select',
        'rating' => 'number',
        'scale' => 'number',
        'file' => 'text',
        'image' => 'text',
    ];

    public function up(): void
    {
        $this->replaceAllowedTypes(self::ALLOWED_TYPES);
    }

    public function down(): void
    {
        foreach (self::LEGACY_TYPE_MAP as $expandedType => $legacyType) {
            DB::table(self::TABLE)
                ->where('field_type', $expandedType)
                ->update(['field_type' => $legacyType]);
        }

        $this->replaceAllowedTypes(self::LEGACY_TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceAllowedTypes(array $types): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->replacePostgresCheckConstraint($types);

            return;
        }

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `field_type` ENUM(%s) NOT NULL',
                self::TABLE,
                $this->quoteValues($types),
            ));

            return;
        }

        // Laravel rebuilds altered tables on SQLite, preserving their columns,
        // foreign keys and indexes while replacing the enum CHECK constraint.
        Schema::table(self::TABLE, function (Blueprint $table) use ($types): void {
            $table->enum('field_type', $types)->change();
        });
    }

    /**
     * @param  list<string>  $types
     */
    private function replacePostgresCheckConstraint(array $types): void
    {
        DB::statement(sprintf(
            'ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s"',
            self::TABLE,
            self::CHECK_CONSTRAINT,
        ));

        DB::statement(sprintf(
            'ALTER TABLE "%s" ADD CONSTRAINT "%s" CHECK ("field_type" IN (%s))',
            self::TABLE,
            self::CHECK_CONSTRAINT,
            $this->quoteValues($types),
        ));
    }

    /**
     * @param  list<string>  $values
     */
    private function quoteValues(array $values): string
    {
        $pdo = DB::connection()->getPdo();

        return implode(', ', array_map(
            static fn (string $value): string => (string) $pdo->quote($value),
            $values,
        ));
    }
};
