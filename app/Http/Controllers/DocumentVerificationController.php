<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;

class DocumentVerificationController extends Controller
{
    public function verify($token)
    {
        $verification = DocumentVerification::where('token', $token)->firstOrFail();

        $documentUrl = null;
        if ($verification->document_path) {
            $documentUrl = route('document.verify.pdf', $verification->token);
        }

        return view('document-verify', compact('verification', 'documentUrl'));
    }

    public function viewPdf($token)
    {
        $verification = DocumentVerification::where('token', $token)->firstOrFail();

        if (!$verification->document_path) {
            abort(404);
        }

        $filePath = storage_path('app/public/' . $verification->document_path);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
