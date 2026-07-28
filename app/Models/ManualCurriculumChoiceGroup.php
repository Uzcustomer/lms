<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qo'lda belgilangan tanlov guruhi: namunaviy rejadagi muqobil fanlar va
 * ularga mos keluvchi ishchi reja fan(lar)i.
 *
 * Solishtirishda shu guruh bitta qator bo'lib chiqadi: tanlanmagan muqobillar
 * "Ishchi rejada yo'q" bo'lib ogohlantirmaydi, jami soat/kredit esa faqat
 * norma muqobil hisobidan olinadi.
 */
class ManualCurriculumChoiceGroup extends Model
{
    protected $fillable = [
        'curricula_hemis_id',
        'label',
        'ref_names',
        'work_names',
        'norm_name',
        'note',
        'created_by',
    ];

    protected $casts = [
        'ref_names' => 'array',
        'work_names' => 'array',
    ];
}
