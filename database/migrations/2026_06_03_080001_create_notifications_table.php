<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'appointment_requested',
                'appointment_approved',
                'appointment_rejected',
                'appointment_rescheduled',
                'appointment_reminder',
                'assignment_created',
                'assignment_deadline',
                'message_received',
                'generic',
            ]);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->enum('channel', ['fcm'])->default('fcm');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
