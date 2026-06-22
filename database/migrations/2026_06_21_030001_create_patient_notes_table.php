<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            $table->string('title', 255)->nullable();
            $table->text('body');
            // Private by default; shared notes are visible to the patient in the app.
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_notes');
    }
};
