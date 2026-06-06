<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaApplication extends Model
{
    protected $table = 'visa_applications';

    protected $fillable = [
        'student_id', 'student_hemis_id', 'student_number',
        'last_name', 'first_name', 'middle_name',
        'birth_date', 'passport_number',
        'phone_number', 'phone_dial_code', 'phone_country_iso2',
        'messenger_type', 'messenger_username',
        'passport_pdf_path', 'application_pdf_path', 'receipt_pdf_path',
        'status', 'application_number',
        'admin_note', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'birth_date'   => 'date',
        'reviewed_at'  => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
