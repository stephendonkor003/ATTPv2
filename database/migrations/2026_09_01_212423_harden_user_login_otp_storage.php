<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DIGEST_LENGTH = 64;

    private const LOOKUP_INDEX = 'login_otps_user_session_state_idx';

    private const EXPIRY_INDEX = 'login_otps_expires_at_idx';

    private const PLAINTEXT_INDEX = 'user_login_otps_user_id_otp_code_index';

    public function up(): void
    {
        if (! Schema::hasTable('user_login_otps') || ! Schema::hasColumn('user_login_otps', 'otp_code')) {
            return;
        }

        if (Schema::hasIndex('user_login_otps', self::PLAINTEXT_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->dropIndex(self::PLAINTEXT_INDEX);
            });
        }

        Schema::table('user_login_otps', function (Blueprint $table): void {
            $table->string('otp_code', self::DIGEST_LENGTH)->change();
        });

        $now = now();

        // No challenge issued by the plaintext implementation may survive the
        // deployment boundary, even if it had time left on its original TTL.
        DB::table('user_login_otps')
            ->whereNull('verified_at')
            ->update([
                'verified_at' => $now,
                'expires_at' => $now,
                'updated_at' => $now,
            ]);

        // Retire every historical plaintext value. These random, irreversible
        // tombstones cannot be submitted as six-digit codes and preserve rows
        // needed for operational history.
        DB::table('user_login_otps')
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    DB::table('user_login_otps')
                        ->where('id', $row->id)
                        ->update([
                            'otp_code' => hash('sha256', random_bytes(32).(string) $row->id),
                            'updated_at' => $now,
                        ]);
                }
            });

        if (! Schema::hasIndex('user_login_otps', self::LOOKUP_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->index(
                    ['user_id', 'session_id', 'verified_at', 'expires_at'],
                    self::LOOKUP_INDEX
                );
            });
        }

        if (! Schema::hasIndex('user_login_otps', self::EXPIRY_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->index('expires_at', self::EXPIRY_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_login_otps') || ! Schema::hasColumn('user_login_otps', 'otp_code')) {
            return;
        }

        // OTP challenges are intentionally ephemeral and their digests cannot
        // be converted back to plaintext. Clearing them is the only safe rollback.
        DB::table('user_login_otps')->delete();

        if (Schema::hasIndex('user_login_otps', self::EXPIRY_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->dropIndex(self::EXPIRY_INDEX);
            });
        }

        if (Schema::hasIndex('user_login_otps', self::LOOKUP_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->dropIndex(self::LOOKUP_INDEX);
            });
        }

        Schema::table('user_login_otps', function (Blueprint $table): void {
            $table->string('otp_code', 6)->change();
        });

        if (! Schema::hasIndex('user_login_otps', self::PLAINTEXT_INDEX)) {
            Schema::table('user_login_otps', function (Blueprint $table): void {
                $table->index(['user_id', 'otp_code'], self::PLAINTEXT_INDEX);
            });
        }
    }
};
