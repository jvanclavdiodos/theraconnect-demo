<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_intents', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('chatbot_responses', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_intents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('chatbot_responses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
