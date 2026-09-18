<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dars ochish so'rovini endi o'qituvchi o'zi yuboradi va soni cheklanadi.
 *
 * Semestr ichida o'qituvchining 1-so'rovini faqat o'quv prorektori
 * tasdiqlaydi; 2-so'rovga tushuntirish xati majburiy va uni registrator
 * ofisi ham, prorektor ham tasdiqlashi kerak; 3-dan boshlab so'rovni faqat
 * admin yubora oladi (ikki bosqichli tasdiq bilan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        Schema::table('lesson_openings', function (Blueprint $table) {
            if (!Schema::hasColumn('lesson_openings', 'teacher_id')) {
                $table->unsignedBigInteger('teacher_id')->nullable()->after('semester_code')->index();
            }
            if (!Schema::hasColumn('lesson_openings', 'teacher_name')) {
                $table->string('teacher_name')->nullable()->after('teacher_id');
            }
            if (!Schema::hasColumn('lesson_openings', 'request_number')) {
                $table->unsignedSmallInteger('request_number')->nullable()->after('teacher_name');
            }
            if (!Schema::hasColumn('lesson_openings', 'explanation_file_path')) {
                $table->string('explanation_file_path')->nullable()->after('file_original_name');
            }
            if (!Schema::hasColumn('lesson_openings', 'explanation_file_original_name')) {
                $table->string('explanation_file_original_name')->nullable()->after('explanation_file_path');
            }
            if (!Schema::hasColumn('lesson_openings', 'needs_registrar')) {
                $table->boolean('needs_registrar')->default(false)->after('status');
            }
            if (!Schema::hasColumn('lesson_openings', 'prorektor_status')) {
                $table->string('prorektor_status')->nullable()->after('needs_registrar');
            }
            if (!Schema::hasColumn('lesson_openings', 'registrar_status')) {
                $table->string('registrar_status')->nullable()->after('review_comment');
            }
            if (!Schema::hasColumn('lesson_openings', 'registrar_id')) {
                $table->unsignedBigInteger('registrar_id')->nullable()->after('registrar_status');
            }
            if (!Schema::hasColumn('lesson_openings', 'registrar_name')) {
                $table->string('registrar_name')->nullable()->after('registrar_id');
            }
            if (!Schema::hasColumn('lesson_openings', 'registrar_guard')) {
                $table->string('registrar_guard')->nullable()->after('registrar_name');
            }
            if (!Schema::hasColumn('lesson_openings', 'registrar_at')) {
                $table->dateTime('registrar_at')->nullable()->after('registrar_guard');
            }
        });

        // Oldingi (bir bosqichli) so'rovlarda prorektor qarori reviewed_* da edi
        DB::table('lesson_openings')
            ->whereNotNull('reviewed_at')
            ->whereNull('prorektor_status')
            ->update([
                'prorektor_status' => DB::raw("CASE WHEN status = 'rejected' THEN 'rejected' ELSE 'approved' END"),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        Schema::table('lesson_openings', function (Blueprint $table) {
            if (Schema::hasColumn('lesson_openings', 'teacher_id')) {
                $table->dropIndex(['teacher_id']);
            }
            foreach ([
                'teacher_id', 'teacher_name', 'request_number',
                'explanation_file_path', 'explanation_file_original_name',
                'needs_registrar', 'prorektor_status',
                'registrar_status', 'registrar_id', 'registrar_name', 'registrar_guard', 'registrar_at',
            ] as $column) {
                if (Schema::hasColumn('lesson_openings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
