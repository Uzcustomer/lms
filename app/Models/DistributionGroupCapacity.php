<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionGroupCapacity extends Model
{
    protected $table = 'distribution_group_capacities';

    protected $fillable = [
        'group_hemis_id',
        'capacity',
        'updated_by',
    ];

    protected $casts = [
        'group_hemis_id' => 'integer',
        'capacity' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Kurs bo'yicha standart sig'im.
     *
     * 1-3 kurslarda 15 ta, 4-6 kurslarda 10 ta. Kurs aniqlanmagan bo'lsa
     * standart berilmaydi — bunday guruhda sig'imni qo'lda kiritish kerak.
     */
    public static function defaultFor(?int $course): ?int
    {
        if ($course === null) {
            return null;
        }

        return $course <= 3 ? 15 : 10;
    }
}
