<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractList extends Model
{
    protected $table = 'contract_list';

    protected $fillable = [
        'hemis_id',
        'key',
        'education_year',
        'student_hemis_id',
        'year',
        'status',
        'status_id',
        'edu_form',
        'edu_form_id',
        'edu_year',
        'full_name',
        'edu_course',
        'edu_cours_id',
        'edu_type_code',
        'edu_type_name',
        'faculty_code',
        'faculty_name',
        'contract_number',
        'edu_contract_sum',
        'edu_organization',
        'edu_organization_code',
        'paid_credit_amount',
        'edu_speciality_code',
        'edu_speciality_name',
        'end_rest_debet_amount',
        'unpaid_credit_amount',
        'vozvrat_debet_amount',
        'contract_debet_amount',
        'edu_contract_type_code',
        'edu_contract_type_name',
        'end_rest_credit_amount',
        'begin_rest_debet_amount',
        'begin_rest_credit_amount',
        'edu_contract_sum_type_code',
        'edu_contract_sum_type_name',
        'hemis_created_at',
        'hemis_updated_at',
    ];

    protected $casts = [
        'edu_contract_sum' => 'decimal:2',
        'paid_credit_amount' => 'decimal:2',
        'end_rest_debet_amount' => 'decimal:2',
        'unpaid_credit_amount' => 'decimal:2',
        'vozvrat_debet_amount' => 'decimal:2',
        'contract_debet_amount' => 'decimal:2',
        'end_rest_credit_amount' => 'decimal:2',
        'begin_rest_debet_amount' => 'decimal:2',
        'begin_rest_credit_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}
