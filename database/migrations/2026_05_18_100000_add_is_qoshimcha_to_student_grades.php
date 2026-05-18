<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            if (!Schema::hasColumn('student_grades', 'is_qoshimcha')) {
                $table->boolean('is_qoshimcha')->default(false)->after('attempt')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            if (Schema::hasColumn('student_grades', 'is_qoshimcha')) {
                $table->dropIndex(['is_qoshimcha']);
                $table->dropColumn('is_qoshimcha');
            }
        });
    }
};
