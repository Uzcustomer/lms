<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaCountryRule extends Model
{
    protected $fillable = ['country_name', 'show_choice', 'default_basis'];

    protected $casts = [
        'show_choice' => 'boolean',
    ];
}
