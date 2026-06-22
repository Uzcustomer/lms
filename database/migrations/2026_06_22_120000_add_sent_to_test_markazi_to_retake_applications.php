<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->timestamp('sent_to_test_markazi_at')->nullable()->after('retake_group_id');
            $table->unsignedBigInteger('sent_to_test_markazi_by')->nullable()->after('sent_to_test_markazi_at');

            $table->index('sent_to_test_markazi_at', 'retake_app_sent_to_test_markazi_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropIndex('retake_app_sent_to_test_markazi_at_idx');
            $table->dropColumn([
                'sent_to_test_markazi_at',
                'sent_to_test_markazi_by',
            ]);
        });
    }
};
