<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectKafedraOverride extends Model
{
    protected $fillable = [
        'norm_name',
        'sample_name',
        'kafedra_name',
        'department_id',
        'practice_group_size',
        // NULL — nomdan avtomatik; 1 — klinik (umumiy karta); 0 — klinik emas
        'is_clinical',
        'updated_by',
    ];
}
