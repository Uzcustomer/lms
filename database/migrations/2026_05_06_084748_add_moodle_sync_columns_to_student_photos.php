<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('student_photos', 'moodle_synced_at')) {
                $table->timestamp('moodle_synced_at')->nullable()->after('embedding_extracted_at');
            }
            if (!Schema::hasColumn('student_photos', 'moodle_sync_status')) {
                $table->string('moodle_sync_status', 32)->nullable()->after('moodle_synced_at');
            }
            if (!Schema::hasColumn('student_photos', 'moodle_sync_error')) {
                $table->text('moodle_sync_error')->nullable()->after('moodle_sync_status');
            }
            if (!Schema::hasColumn('student_photos', 'moodle_file_hash')) {
                $table->string('moodle_file_hash', 64)->nullable()->after('moodle_sync_error');
            }
            if (!Schema::hasColumn('student_photos', 'moodle_response')) {
                $table->json('moodle_response')->nullable()->after('moodle_file_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            foreach ([
                'moodle_synced_at',
                'moodle_sync_status',
                'moodle_sync_error',
                'moodle_file_hash',
                'moodle_response',
            ] as $col) {
                if (Schema::hasColumn('student_photos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
