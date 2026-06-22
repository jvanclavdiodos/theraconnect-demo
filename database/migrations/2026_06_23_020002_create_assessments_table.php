<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            // Standardized scale assigned to the patient.
            $table->enum('instrument', ['phq9', 'gad7']);
            $table->enum('status', ['pending', 'completed'])->default('pending');
            // Total score + per-item responses (0–3 each), populated on completion.
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('responses')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'instrument', 'completed_at']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
