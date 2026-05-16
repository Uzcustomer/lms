<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            $table->json('teacher_phones')->nullable()->after('teacher_name');
        });
    }

    public function down(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            $table->dropColumn('teacher_phones');
        });
    }
};
