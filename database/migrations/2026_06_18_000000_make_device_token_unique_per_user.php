<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the legacy global UNIQUE on device_tokens.token with a composite
 * UNIQUE on (user_id, token). The original constraint made it impossible for
 * two patients on a shared device to register the same physical FCM token —
 * the second user's INSERT hit a unique violation and returned HTTP 500.
 *
 * With the composite key, each (user, token) pair is unique, which matches
 * DeviceTokenController::store's existing updateOrCreate(['user_id','token'])
 * match key. A shared physical token simply creates a new row per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_token_unique');
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unique(['user_id', 'token'], 'device_tokens_user_token_unique');
        });
    }

    /**
     * Rolling back drops the composite unique. We do NOT attempt to restore
     * the legacy global UNIQUE on `token` alone, because — by the time anyone
     * would roll this back — the table may legitimately contain multiple rows
     * sharing a physical FCM token (per-user), and forcing the legacy unique
     * would fail with a constraint violation against existing data. Restore
     * the legacy constraint manually only if your data has no per-user
     * duplicates for the same physical token.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_user_token_unique');
        });
    }
};
