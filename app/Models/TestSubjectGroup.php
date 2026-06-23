<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestSubjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_subject_id',
        'group_id',
        'group_hemis_id',
        'group_name',
    ];

    public function testSubject()
    {
        return $this->belongsTo(TestSubject::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
