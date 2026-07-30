<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE appointments MODIFY COLUMN status ENUM('.
            "'pending','approved','rejected','rescheduled','completed','cancelled','no_show','expired'".
            ") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE appointments MODIFY COLUMN status ENUM('.
            "'pending','approved','rejected','rescheduled','completed','cancelled','no_show'".
            ") NOT NULL DEFAULT 'pending'"
        );
    }
};
