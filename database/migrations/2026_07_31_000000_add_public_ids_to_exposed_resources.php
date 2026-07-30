<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users',
        'patients',
        'clinicians',
        'appointments',
        'assignments',
        'assignment_submissions',
        'assessments',
        'conversations',
        'messages',
        'notifications',
        'patient_notes',
        'therapy_goals',
        'chatbot_intents',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->ulid('public_id')->nullable()->unique();
            });
        }

        foreach ($this->tables as $tableName) {
            DB::table($tableName)
                ->select('id')
                ->whereNull('public_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->whereNull('public_id')
                            ->update(['public_id' => (string) Str::ulid()]);
                    }
                });
        }

        $this->migrateNotificationReferencesToPublicIds();

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->ulid('public_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $this->migrateNotificationReferencesToInternalIds();

        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_public_id_unique');
                $table->dropColumn('public_id');
            });
        }
    }

    private function migrateNotificationReferencesToPublicIds(): void
    {
        $references = [
            'appointment_id' => ['table' => 'appointments', 'public_key' => 'appointment_public_id'],
            'assignment_id' => ['table' => 'assignments', 'public_key' => 'assignment_public_id'],
        ];

        DB::table('notifications')
            ->select(['id', 'data'])
            ->whereNotNull('data')
            ->orderBy('id')
            ->chunkById(500, function ($notifications) use ($references): void {
                foreach ($notifications as $notification) {
                    $data = json_decode($notification->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($references as $internalKey => $reference) {
                        if (! array_key_exists($internalKey, $data)) {
                            continue;
                        }

                        $publicId = is_numeric($data[$internalKey])
                            ? DB::table($reference['table'])
                                ->where('id', (int) $data[$internalKey])
                                ->value('public_id')
                            : null;

                        unset($data[$internalKey]);
                        if ($publicId !== null) {
                            $data[$reference['public_key']] = $publicId;
                        }
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('notifications')
                            ->where('id', $notification->id)
                            ->update(['data' => json_encode($data)]);
                    }
                }
            });
    }

    private function migrateNotificationReferencesToInternalIds(): void
    {
        $references = [
            'appointment_public_id' => ['table' => 'appointments', 'internal_key' => 'appointment_id'],
            'assignment_public_id' => ['table' => 'assignments', 'internal_key' => 'assignment_id'],
        ];

        DB::table('notifications')
            ->select(['id', 'data'])
            ->whereNotNull('data')
            ->orderBy('id')
            ->chunkById(500, function ($notifications) use ($references): void {
                foreach ($notifications as $notification) {
                    $data = json_decode($notification->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($references as $publicKey => $reference) {
                        if (! array_key_exists($publicKey, $data)) {
                            continue;
                        }

                        $id = DB::table($reference['table'])
                            ->where('public_id', $data[$publicKey])
                            ->value('id');

                        unset($data[$publicKey]);
                        if ($id !== null) {
                            $data[$reference['internal_key']] = $id;
                        }
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('notifications')
                            ->where('id', $notification->id)
                            ->update(['data' => json_encode($data)]);
                    }
                }
            });
    }
};
