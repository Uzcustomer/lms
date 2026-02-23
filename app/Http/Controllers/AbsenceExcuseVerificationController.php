<?php

namespace App\Http\Controllers;

use App\Models\AbsenceExcuse;

class AbsenceExcuseVerificationController extends Controller
{
    public function verify($token)
    {
        $excuse = AbsenceExcuse::where('verification_token', $token)->firstOrFail();

        $documentUrl = null;
        if ($excuse->isApproved() && $excuse->approved_pdf_path) {
            $documentUrl = route('absence-excuse.verify.pdf', $excuse->verification_token);
        }

        return view('absence-excuse-verify', compact('excuse', 'documentUrl'));
    }

    public function viewPdf($token)
    {
        $excuse = AbsenceExcuse::where('verification_token', $token)->firstOrFail();

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            abort(404);
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
