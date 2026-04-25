<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->decimal('quality_score', 5, 2)->nullable()->after('similarity_checked_at');
            $table->boolean('quality_passed')->nullable()->after('quality_score');
            $table->json('quality_issues')->nullable()->after('quality_passed');
            $table->json('quality_ok')->nullable()->after('quality_issues');
            $table->timestamp('quality_checked_at')->nullable()->after('quality_ok');
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->dropColumn([
                'quality_score',
                'quality_passed',
                'quality_issues',
                'quality_ok',
                'quality_checked_at',
            ]);
        });
    }
};
