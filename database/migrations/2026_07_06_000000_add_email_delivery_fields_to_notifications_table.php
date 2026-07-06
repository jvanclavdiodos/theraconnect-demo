<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('sent_at');
            $table->timestamp('email_failed_at')->nullable()->after('email_sent_at');
            $table->text('email_error')->nullable()->after('email_failed_at');
            $table->index(['user_id', 'email_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'email_sent_at']);
            $table->dropColumn(['email_sent_at', 'email_failed_at', 'email_error']);
        });
    }
};
