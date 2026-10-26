<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Qayta topshirish testidan jurnalga yozilgan otrabotkalarda "Baho qo'ydi"
 * "Test markazi" emas, "Kafedra" bo'lib ko'rinsin (FanTestiJournalSync::GRADED_BY).
 */
return new class extends Migration
{
    private const OLD = 'Test markazi (qayta topshirish testi)';
    private const NEW = 'Kafedra';

    public function up(): void
    {
        DB::table('student_grades')
            ->where('retake_by', self::OLD)
            ->where('retake_comment', 'like', 'Qayta topshirish testi:%')
            ->update(['retake_by' => self::NEW]);
    }

    public function down(): void
    {
        DB::table('student_grades')
            ->where('retake_by', self::NEW)
            ->where('retake_comment', 'like', 'Qayta topshirish testi:%')
            ->update(['retake_by' => self::OLD]);
    }
};
