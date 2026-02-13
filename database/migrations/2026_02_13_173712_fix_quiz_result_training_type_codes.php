<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Oldin training_type_code=103 bilan yuklangan quiz natijalarni
     * quiz_type ga qarab to'g'ri kodga o'zgartirish:
     *   OSKI (eng/rus/uzb) → 101, YN test (eng/rus/uzb) → 102
     */
    public function up(): void
    {
        // OSKI natijalarni 101 ga o'zgartirish
        DB::table('student_grades')
            ->where('training_type_code', 103)
            ->where('reason', 'quiz_result')
            ->whereNotNull('quiz_result_id')
            ->whereIn('quiz_result_id', function ($query) {
                $query->select('id')
                    ->from('hemis_quiz_results')
                    ->whereIn('quiz_type', ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)']);
            })
            ->update([
                'training_type_code' => 101,
                'training_type_name' => 'Oski',
            ]);

        // Test natijalarni 102 ga o'zgartirish
        DB::table('student_grades')
            ->where('training_type_code', 103)
            ->where('reason', 'quiz_result')
            ->whereNotNull('quiz_result_id')
            ->whereIn('quiz_result_id', function ($query) {
                $query->select('id')
                    ->from('hemis_quiz_results')
                    ->whereIn('quiz_type', ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)']);
            })
            ->update([
                'training_type_code' => 102,
                'training_type_name' => 'Yakuniy test',
            ]);
    }

    public function down(): void
    {
        // Qaytarish: quiz natijalarni yana 103 ga
        DB::table('student_grades')
            ->whereIn('training_type_code', [101, 102])
            ->where('reason', 'quiz_result')
            ->whereNotNull('quiz_result_id')
            ->update([
                'training_type_code' => 103,
                'training_type_name' => 'Quiz test',
            ]);
    }
};
