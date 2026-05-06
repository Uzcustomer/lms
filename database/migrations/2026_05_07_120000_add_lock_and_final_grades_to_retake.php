<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // O'qituvchi guruhni "yopish" (yakuniy yuborish) — keyin tahrirlash mumkin emas.
        // Test markaziga vedomost yuborish (PDF saqlanadi).
        Schema::table('retake_groups', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('status');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by_user_id')->nullable()->after('locked_at');
            $table->string('locked_by_name')->nullable()->after('locked_by_user_id');

            $table->string('vedomost_path')->nullable()->after('locked_by_name');
            $table->timestamp('vedomost_generated_at')->nullable()->after('vedomost_path');

            $table->timestamp('sent_to_test_markazi_at')->nullable()->after('vedomost_generated_at');
            $table->unsignedBigInteger('sent_to_test_markazi_by')->nullable()->after('sent_to_test_markazi_at');
        });

        // Test markazi va o'qituvchi yakuniy baholarni shu yerga yozadi
        Schema::table('retake_applications', function (Blueprint $table) {
            // Test markazi tomonidan
            $table->decimal('oske_score', 5, 2)->nullable()->after('has_sinov');
            $table->decimal('test_score', 5, 2)->nullable()->after('oske_score');

            // Yakuniy hisoblangan baho
            $table->decimal('final_grade_value', 5, 2)->nullable()->after('test_score');
            $table->timestamp('final_grade_set_at')->nullable()->after('final_grade_value');
        });
    }

    public function down(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            $table->dropColumn([
                'is_locked',
                'locked_at',
                'locked_by_user_id',
                'locked_by_name',
                'vedomost_path',
                'vedomost_generated_at',
                'sent_to_test_markazi_at',
                'sent_to_test_markazi_by',
            ]);
        });

        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropColumn([
                'oske_score',
                'test_score',
                'final_grade_value',
                'final_grade_set_at',
            ]);
        });
    }
};
