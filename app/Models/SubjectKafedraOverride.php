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
        'updated_by',
    ];
}
