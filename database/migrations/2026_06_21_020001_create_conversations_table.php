<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('patient_last_read_at')->nullable();
            $table->timestamp('clinician_last_read_at')->nullable();
            $table->timestamps();

            // One ongoing thread per patient-clinician pair.
            $table->unique(['patient_id', 'clinician_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
