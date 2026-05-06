<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            // Eski unique indeksni olib tashlaymiz: (specialty_id, level_code, semester_code).
            // Sessiyalar qo'shilgach, bir xil yo'nalish/kurs/semestr har sessiyada
            // alohida oyna sifatida yaratilishi mumkin.
            $table->dropUnique('retake_window_unique_idx');
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            // Yangi unique indeks — `session_id` ni ham qamrab oladi.
            $table->unique(
                ['session_id', 'specialty_id', 'level_code', 'semester_code'],
                'retake_window_unique_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->dropUnique('retake_window_unique_idx');
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->unique(
                ['specialty_id', 'level_code', 'semester_code'],
                'retake_window_unique_idx'
            );
        });
    }
};
