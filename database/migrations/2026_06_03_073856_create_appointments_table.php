<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('clinician_id')->nullable()->constrained('clinicians')->onDelete('set null');
            $table->dateTime('requested_at');
            $table->dateTime('scheduled_at')->nullable();
            $table->enum('mode', ['in_person', 'online'])->default('in_person');
            $table->string('meeting_link', 512)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'rescheduled', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->string('reason', 500)->nullable();
            $table->text('clinic_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['clinician_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
