<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('photo_path');
            $table->string('reviewed_by_name')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_name');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->decimal('similarity_score', 5, 2)->nullable()->after('rejection_reason');
            $table->string('similarity_status', 20)->nullable()->after('similarity_score');
            $table->timestamp('similarity_checked_at')->nullable()->after('similarity_status');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'reviewed_by_name',
                'reviewed_at',
                'rejection_reason',
                'similarity_score',
                'similarity_status',
                'similarity_checked_at',
            ]);
        });
    }
};
