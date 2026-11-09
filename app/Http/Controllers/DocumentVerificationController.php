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

        // YN qaydnoma — tasdiqlangan Excel fayli: bosilganda yuklab olinadi
        if (str_ends_with($filePath, '.xlsx')) {
            $name = 'YN_qaydnoma_' . $verification->group_names . '_' . $verification->subject_name . '.xlsx';

            return response()->download($filePath, str_replace(['/', '\\'], '_', $name));
        }

        // Eski YN qaydnoma tasdig'i — o'qituvchi tasdiqlagan paytdagi sahifa nusxasi (HTML)
        return response()->file($filePath, [
            'Content-Type' => str_ends_with($filePath, '.html') ? 'text/html; charset=UTF-8' : 'application/pdf',
        ]);
    }
}
