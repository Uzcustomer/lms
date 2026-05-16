<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('student_photos', 'face_height_ratio')) {
                $table->decimal('face_height_ratio', 5, 4)->nullable()->after('quality_ok');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            if (Schema::hasColumn('student_photos', 'face_height_ratio')) {
                $table->dropColumn('face_height_ratio');
            }
        });
    }
};
