<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VedomostGroup extends Model
{
    protected $fillable = [
        "vedomost_id",
        "group_hemis_id",
        "subject_hemis_id",
        "subject_hemis_id_secend",
        "student_hemis_ids",
    ];
}