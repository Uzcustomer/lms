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
        'full_group_mode',
        'assigned_by',
        'notified_at',
        'notified_group_id',
        'seen_at',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'from_group_hemis_id' => 'integer',
        'to_group_hemis_id' => 'integer',
        'full_group_mode' => 'boolean',
        'assigned_by' => 'integer',
        'notified_at' => 'datetime',
        'notified_group_id' => 'integer',
        'seen_at' => 'datetime',
    ];

    /**
     * Xabar yuborish kerakmi: hali yuborilmagan yoki yuborilgandan keyin
     * talaba boshqa guruhga ko'chirilgan bo'lsa.
     */
    public function needsNotification(): bool
    {
        return $this->notified_at === null
            || (int) $this->notified_group_id !== (int) $this->to_group_hemis_id;
    }

    /**
     * Talabaga popup ko'rsatiladimi: hali ko'rmagan yoki ko'rgandan keyin
     * guruhi yana o'zgargan bo'lsa.
     */
    public function needsPopup(): bool
    {
        return $this->seen_at === null || $this->seen_at->lt($this->updated_at);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
