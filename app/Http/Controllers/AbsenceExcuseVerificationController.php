<?php

namespace App\Http\Controllers;

use App\Models\AbsenceExcuse;

class AbsenceExcuseVerificationController extends Controller
{
    public function verify($token)
    {
        $excuse = AbsenceExcuse::where('verification_token', $token)->firstOrFail();

        return view('absence-excuse-verify', compact('excuse'));
    }
}
