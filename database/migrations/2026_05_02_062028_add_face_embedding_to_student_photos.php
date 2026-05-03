<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            // ArcFace 512-dim embedding (L2-normalized) JSON ko'rinishida
            $table->json('face_embedding')->nullable()->after('quality_checked_at');
            $table->timestamp('embedding_extracted_at')->nullable()->after('face_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->dropColumn(['face_embedding', 'embedding_extracted_at']);
        });
    }
};
