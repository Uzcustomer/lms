<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentSubject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubjectMatcherService
{
    /**
     * Talabaning hozirgi semestri uchun fanlarini olish
     */
    public static function getStudentSubjects(Student $student): Collection
    {
        return StudentSubject::where('student_hemis_id', $student->hemis_id)
            ->when($student->semester_id, function ($q) use ($student) {
                $q->where('semester_id', $student->semester_id);
            })
            ->get();
    }

    /**
     * Fan nomini normalize qilish (qavslar ichidagi harflarni olib tashlash, kichik harfga o'tkazish)
     */
    public static function normalizeSubjectName(string $name): string
    {
        // Qavslar ichidagi bo'limni olib tashlash: "Xirurgik kasalliklar (c)" -> "Xirurgik kasalliklar"
        $normalized = preg_replace('/\s*\([a-z]\)\s*$/i', '', $name);
        // Bosh va oxirgi bo'sh joylarni olib tashlash
        $normalized = trim($normalized);
        // Kichik harfga
        $normalized = mb_strtolower($normalized);
        // Ikki bo'sh joyni bitta qilish
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    /**
     * Fan nomini kalit so'zlarga ajratish (fuzzy match uchun)
     */
    public static function extractKeywords(string $name): array
    {
        $normalized = self::normalizeSubjectName($name);
        // Qisqa so'zlarni (2 harfdan kam) olib tashlash
        $words = preg_split('/\s+/', $normalized);
        return array_values(array_filter($words, fn($w) => mb_strlen($w) >= 3));
    }

    /**
     * Ikki fan nomi o'rtasidagi o'xshashlik foizini hisoblash
     */
    public static function similarity(string $name1, string $name2): float
    {
        $norm1 = self::normalizeSubjectName($name1);
        $norm2 = self::normalizeSubjectName($name2);

        // To'liq mos
        if ($norm1 === $norm2) {
            return 100.0;
        }

        // Kalit so'zlar bo'yicha
        $keywords1 = self::extractKeywords($name1);
        $keywords2 = self::extractKeywords($name2);

        if (empty($keywords1) || empty($keywords2)) {
            return 0.0;
        }

        $matched = 0;
        $total = max(count($keywords1), count($keywords2));

        foreach ($keywords1 as $kw1) {
            foreach ($keywords2 as $kw2) {
                // To'liq mos
                if ($kw1 === $kw2) {
                    $matched++;
                    break;
                }
                // Biri ikkinchisining boshlanishi bo'lsa (qisqartma)
                if (mb_strlen($kw1) >= 4 && mb_strlen($kw2) >= 4) {
                    if (str_starts_with($kw1, mb_substr($kw2, 0, 4)) || str_starts_with($kw2, mb_substr($kw1, 0, 4))) {
                        $matched += 0.7;
                        break;
                    }
                }
            }
        }

        return round(($matched / $total) * 100, 1);
    }

    /**
     * Berilgan fan nomi uchun eng yaqin matchni topish student_subjects dan
     *
     * @return array|null ['subject_id' => ..., 'subject_name' => ..., 'similarity' => ...]
     */
    public static function findBestMatch(string $subjectName, Collection $studentSubjects, float $minSimilarity = 50.0): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($studentSubjects as $ss) {
            $score = self::similarity($subjectName, $ss->subject_name);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $ss;
            }
        }

        if ($bestMatch && $bestScore >= $minSimilarity) {
            return [
                'subject_id' => $bestMatch->subject_id,
                'subject_name' => $bestMatch->subject_name,
                'similarity' => $bestScore,
                'semester_id' => $bestMatch->semester_id,
            ];
        }

        return null;
    }

    /**
     * Makeup uchun subject_id ni aniqlash
     * 1. Avval exact match (subject_id orqali)
     * 2. Keyin exact name match
     * 3. Keyin fuzzy match
     *
     * @return array|null ['subject_id' => ..., 'subject_name' => ..., 'match_type' => 'exact_id'|'exact_name'|'fuzzy']
     */
    public static function resolveSubjectId(string $subjectName, ?string $existingSubjectId, Student $student): ?array
    {
        $studentSubjects = self::getStudentSubjects($student);

        if ($studentSubjects->isEmpty()) {
            return null;
        }

        // 1. Exact match by subject_id
        if ($existingSubjectId) {
            $exactById = $studentSubjects->firstWhere('subject_id', $existingSubjectId);
            if ($exactById) {
                return [
                    'subject_id' => $exactById->subject_id,
                    'subject_name' => $exactById->subject_name,
                    'match_type' => 'exact_id',
                ];
            }
        }

        // 2. Exact name match (normalized)
        $normalizedInput = self::normalizeSubjectName($subjectName);
        foreach ($studentSubjects as $ss) {
            if (self::normalizeSubjectName($ss->subject_name) === $normalizedInput) {
                return [
                    'subject_id' => $ss->subject_id,
                    'subject_name' => $ss->subject_name,
                    'match_type' => 'exact_name',
                ];
            }
        }

        // 3. Fuzzy match
        $fuzzy = self::findBestMatch($subjectName, $studentSubjects);
        if ($fuzzy) {
            return [
                'subject_id' => $fuzzy['subject_id'],
                'subject_name' => $fuzzy['subject_name'],
                'match_type' => 'fuzzy',
                'similarity' => $fuzzy['similarity'],
            ];
        }

        return null;
    }
}
