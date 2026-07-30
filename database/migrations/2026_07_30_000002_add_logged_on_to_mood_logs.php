<?php

use App\Support\MoodLogDates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('mood_logs')->whereNull('created_at')->whereNull('updated_at')->exists()) {
            throw new RuntimeException(
                'At least one mood log has no timestamp; its Manila date requires manual review.'
            );
        }

        Schema::create('mood_log_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_mood_log_id')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->unsignedBigInteger('retained_mood_log_id')->index();
            $table->unsignedTinyInteger('score');
            $table->string('note', 255)->nullable();
            $table->date('logged_on');
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->string('reason', 64);
        });

        Schema::table('mood_logs', function (Blueprint $table) {
            $table->date('logged_on')->nullable()->after('note');
        });

        DB::transaction(function () {
            DB::table('mood_logs')
                ->orderBy('id')
                ->chunkById(500, function ($logs) {
                    foreach ($logs as $log) {
                        $sourceTimestamp = $log->created_at ?? $log->updated_at;

                        DB::table('mood_logs')
                            ->where('id', $log->id)
                            ->update(['logged_on' => MoodLogDates::fromLegacyTimestamp($sourceTimestamp)]);
                    }
                });

            $duplicateGroups = DB::table('mood_logs')
                ->select('patient_id', 'logged_on')
                ->groupBy('patient_id', 'logged_on')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicateGroups as $group) {
                $logs = DB::table('mood_logs')
                    ->where('patient_id', $group->patient_id)
                    ->where('logged_on', $group->logged_on)
                    ->orderByRaw('COALESCE(created_at, updated_at) DESC')
                    ->orderByDesc('id')
                    ->get();

                $retained = $logs->first();
                $archivedAt = now();

                foreach ($logs->skip(1) as $duplicate) {
                    DB::table('mood_log_archives')->insert([
                        'original_mood_log_id' => $duplicate->id,
                        'patient_id' => $duplicate->patient_id,
                        'retained_mood_log_id' => $retained->id,
                        'score' => $duplicate->score,
                        'note' => $duplicate->note,
                        'logged_on' => $duplicate->logged_on,
                        'original_created_at' => $duplicate->created_at,
                        'original_updated_at' => $duplicate->updated_at,
                        'archived_at' => $archivedAt,
                        'reason' => 'duplicate_daily_cleanup',
                    ]);

                    DB::table('mood_logs')->where('id', $duplicate->id)->delete();
                }
            }
        });

        Schema::table('mood_logs', function (Blueprint $table) {
            $table->date('logged_on')->nullable(false)->change();
            $table->unique(['patient_id', 'logged_on'], 'mood_logs_patient_logged_on_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mood_logs', function (Blueprint $table) {
            $table->dropUnique('mood_logs_patient_logged_on_unique');
            $table->dropColumn('logged_on');
        });

        Schema::dropIfExists('mood_log_archives');
    }
};
