<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapy_goal_id')->constrained('therapy_goals')->onDelete('cascade');
            // The review session this rating was made at (optional).
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            // Goal Attainment Scaling: -2 (much less) … 0 (expected) … +2 (much more).
            $table->tinyInteger('rating');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['therapy_goal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_ratings');
    }
};
