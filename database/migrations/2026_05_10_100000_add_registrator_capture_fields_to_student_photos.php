<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('student_photos', 'source')) {
                $table->string('source', 32)->nullable()->index()
                    ->after('photo_path');
            }
            if (!Schema::hasColumn('student_photos', 'similarity_hemis')) {
                $table->float('similarity_hemis')->nullable()
                    ->after('similarity_score');
            }
            if (!Schema::hasColumn('student_photos', 'similarity_mark')) {
                $table->float('similarity_mark')->nullable()
                    ->after('similarity_hemis');
            }
            if (!Schema::hasColumn('student_photos', 'captured_by_user_id')) {
                $table->unsignedBigInteger('captured_by_user_id')->nullable()
                    ->after('similarity_mark');
                $table->index('captured_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            foreach (['source', 'similarity_hemis', 'similarity_mark', 'captured_by_user_id'] as $col) {
                if (Schema::hasColumn('student_photos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
