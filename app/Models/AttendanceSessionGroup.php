<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSessionGroup extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'group_hemis_id', 'group_name'];
}
