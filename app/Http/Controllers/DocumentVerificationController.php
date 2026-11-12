<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use App\Models\YnQaydnomaConfirmation;
use Illuminate\Support\Facades\Storage;

class DocumentVerificationController extends Controller
{
    public function verify($token)
    {
        $verification = DocumentVerification::where('token', $token)->firstOrFail();

        $documentUrl = null;
        if ($verification->document_path) {
            $documentUrl = route('document.verify.pdf', $verification->token);
        }

        // YN qaydnoma — hujjatning barcha tasdiqlari (o'qituvchilar, kafedra mudiri, dekan) va PDF (dekan tasdiqlagach)
        $ynqConfirmations = collect();
        $ynqPdfUrl = null;
        if ($verification->document_type === 'yn_qaydnoma' && ($vedomost = $this->ynqVedomost($verification))) {
            $ynqConfirmations = YnQaydnomaConfirmation::forVedomost(...$vedomost)->orderBy('confirmed_at')->get()
                ->unique(fn ($c) => $c->role . '|' . $c->teacher_id)
                ->sortBy(fn ($c) => array_search($c->role, YnQaydnomaConfirmation::CHAIN, true))->values();
            if ($ynqConfirmations->contains('role', YnQaydnomaConfirmation::ROLE_DEAN)) {
                $ynqPdfUrl = route('document.verify.pdf', $verification->token);
            }
        }

        return view('document-verify', compact('verification', 'documentUrl', 'ynqConfirmations', 'ynqPdfUrl'));
    }

    public function viewPdf($token)
    {
        $verification = DocumentVerification::where('token', $token)->firstOrFail();

        // YN qaydnoma — dekan tasdiqlagan bo'lsa PDF (brauzerda ochiladi; yo'q bo'lsa tayyorlanadi)
        if ($verification->document_type === 'yn_qaydnoma' && ($vedomost = $this->ynqVedomost($verification))) {
            $dean = YnQaydnomaConfirmation::forVedomost(...$vedomost)->where('role', YnQaydnomaConfirmation::ROLE_DEAN)
                ->latest('confirmed_at')->first();
            if ($dean) {
                $disk = Storage::disk('public');
                $path = $dean->pdf_path && $disk->exists($dean->pdf_path)
                    ? $dean->pdf_path
                    : app(Admin\JournalController::class)->generateYnQaydnomaPdf(...$vedomost);
                if ($path) {
                    $name = str_replace(['/', '\\', ' '], '_', 'YN_qaydnoma_' . $verification->group_names . '_' . $verification->subject_name) . '.pdf';

                    return response()->file($disk->path($path), [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $name . '"',
                    ]);
                }
            }
        }

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

    /** YN qaydnoma tasdig'idan: [fan, semestr, guruh] (tasdiq yaratilganda meta ga yoziladi) */
    private function ynqVedomost(DocumentVerification $verification): ?array
    {
        $meta = $verification->meta ?? [];
        if (empty($meta['subjectId']) || empty($meta['semesterCode']) || empty($meta['groupHemisId'])) {
            return null;
        }

        return [(string) $meta['subjectId'], (string) $meta['semesterCode'], (string) $meta['groupHemisId']];
    }
}
