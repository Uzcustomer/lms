<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;

class DocumentVerificationController extends Controller
{
    public function verify($token)
    {
        $verification = DocumentVerification::where('token', $token)->firstOrFail();

        return view('document-verify', compact('verification'));
    }
}
