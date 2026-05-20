<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'transfer_reference')) {
                $table->string('transfer_reference')->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'recipient_confirmation_status')) {
                $table->string('recipient_confirmation_status', 30)->default('pending')->after('status')->index();
            }

            if (! Schema::hasColumn('procurement_disbursements', 'recipient_confirmed_by')) {
                $table->foreignUuid('recipient_confirmed_by')
                    ->nullable()
                    ->after('recipient_confirmation_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_disbursements', 'recipient_confirmed_at')) {
                $table->timestamp('recipient_confirmed_at')->nullable()->after('recipient_confirmed_by');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'recipient_confirmation_notes')) {
                $table->text('recipient_confirmation_notes')->nullable()->after('recipient_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_disbursements', 'recipient_confirmed_by')) {
                $table->dropConstrainedForeignId('recipient_confirmed_by');
            }

            foreach ([
                'transfer_reference',
                'recipient_confirmation_status',
                'recipient_confirmed_at',
                'recipient_confirmation_notes',
            ] as $column) {
                if (Schema::hasColumn('procurement_disbursements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
