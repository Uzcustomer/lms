<?php

namespace App\Http\Controllers;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use Illuminate\Support\Facades\Storage;

/**
 * Savol rasmini beradi.
 *
 * public/storage symlinki har doim ham mavjud bo'lmagani uchun rasm to'g'ridan
 * to'g'ri asset('storage/...') orqali emas, shu kontroller orqali uzatiladi —
 * test-subject-question-images bilan bir xil yondashuv.
 */
class FanTestiQuestionImageController extends Controller
{
    public function show(FanTesti $fanTesti, int $question)
    {
        $questions = $fanTesti->questions ?? [];
        abort_unless(array_key_exists($question, $questions), 404, 'Savol topilmadi.');

        return $this->stream($questions[$question]['image_path'] ?? null);
    }

    /**
     * Urinish davomidagi rasm: savollar nusxadan olinadi, chunki o'qituvchi
     * savolni o'chirsa asosiy massivdagi indekslar siljib ketadi.
     */
    public function attemptImage(FanTestiAttempt $attempt, int $question)
    {
        $questions = $attempt->questions_snapshot ?? [];
        abort_unless(array_key_exists($question, $questions), 404, 'Savol topilmadi.');

        return $this->stream($questions[$question]['image_path'] ?? null);
    }

    private function stream(?string $path)
    {
        $path = trim((string) $path);

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Rasm topilmadi.');
        }

        return Storage::disk('public')->response($path);
    }
}
