<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * O'quv prorektori tomonidan sistemaga adashib yuklangan test bahosini
 * asoslovchi hujjat bilan tuzatish (almashtirish / o'chirish) yozuvi.
 */
class QuizGradeAppeal extends Model
{
    public const ACTION_REPLACE = 'replace';
    public const ACTION_DELETE = 'delete';

    protected $fillable = [
        'student_grade_id',
        'quiz_result_id',
        'retake_application_id',
        'retake_component',
        'student_hemis_id',
        'student_name',
        'subject_id',
        'subject_name',
        'action',
        'old_grade',
        'new_grade',
        'reason',
        'document_path',
        'document_original_name',
        'performed_by_guard',
        'performed_by_id',
        'performed_by_name',
        'performed_by_role',
    ];

    protected $casts = [
        'old_grade' => 'float',
        'new_grade' => 'float',
    ];

    public function actionLabel(): string
    {
        return $this->action === self::ACTION_DELETE ? "O'chirildi" : 'Almashtirildi';
    }
}
