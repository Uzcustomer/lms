<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_capacity_overrides', function (Blueprint $table) {
            // Shu kun uchun ishlamaydigan kompyuter raqamlari (1..N). null →
            // o'sha kun hech qanday cheklov yo'q. Slot sig'imi va kompyuter
            // raqamini berishda chetlab o'tiladi.
            $table->json('broken_computers')->nullable()->after('test_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('exam_capacity_overrides', function (Blueprint $table) {
            $table->dropColumn('broken_computers');
        });
    }
};
