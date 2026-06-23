<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapy_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('clinician_id')->constrained('clinicians')->onDelete('cascade');
            $table->string('description', 500);
            $table->enum('status', ['active', 'met', 'dropped'])->default('active');
            $table->date('target_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapy_goals');
    }
};
