<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Taqsimot rejasi — talabaning qaysi guruhdan qaysi guruhga ko'chirilishi.
 *
 * Faqat reja: students.group_id o'zgartirilmaydi.
 */
class DistributionDraftAssignment extends Model
{
    protected $table = 'distribution_draft_assignments';

    protected $fillable = [
        'student_id',
        'from_group_hemis_id',
        'to_group_hemis_id',
        'student_name',
        'student_id_number',
        'from_group_name',
        'to_group_name',
        'assigned_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'from_group_hemis_id' => 'integer',
        'to_group_hemis_id' => 'integer',
        'assigned_by' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
