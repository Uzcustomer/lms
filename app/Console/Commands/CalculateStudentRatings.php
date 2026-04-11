<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateStudentRatings extends Command
{
    protected $signature = 'ratings:calculate {--semester= : Semestr kodi} {--year= : O\'quv yili kodi}';
    protected $description = 'Talabalar JN reytingini hisoblash (kunlik)';

    public function handle()
    {
        $this->info('Talabalar reytingi hisoblanmoqda...');

        $students = Student::whereNotNull('hemis_id')
            ->whereNotNull('semester_code')
            ->get();

        $this->info("Jami talabalar: {$students->count()}");

        $semesterCode = $this->option('semester');
        $yearCode = $this->option('year');
        $excludeTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $now = now();
        $bar = $this->output->createProgressBar($students->count());
        $ratings = [];

        foreach ($students as $student) {
            $bar->advance();

            $sSemester = $semesterCode ?: $student->semester_code;
            $sYear = $yearCode ?: $student->education_year_code;

            if (!$sSemester) continue;

            // Talabaning barcha fanlari bo'yicha baholari
            $grades = StudentGrade::where('student_hemis_id', $student->hemis_id)
                ->where('semester_code', $sSemester)
                ->when($sYear, fn($q) => $q->where(function ($q2) use ($sYear) {
                    $q2->where('education_year_code', $sYear)->orWhereNull('education_year_code');
                }))
                ->whereNotIn('training_type_code', $excludeTypes)
                ->whereNotNull('lesson_date')
                ->get();

            if ($grades->isEmpty()) continue;

            // Fan bo'yicha guruhlash
            $bySubject = $grades->groupBy('subject_id');
            $subjectAverages = [];

            foreach ($bySubject as $subjectId => $subjectGrades) {
                // Kun bo'yicha guruhlash
                $byDate = $subjectGrades->groupBy(function ($g) {
                    return substr($g->lesson_date, 0, 10);
                });

                $totalDaily = 0;
                $daysCount = 0;

                foreach ($byDate as $date => $dailyGrades) {
                    $dayTotal = 0;
                    $dayCount = 0;
                    $absentCount = 0;

                    foreach ($dailyGrades as $g) {
                        if ($g->status === 'retake') {
                            $dayTotal += $g->retake_grade ?? 0;
                        } elseif ($g->status === 'pending' && $g->reason === 'absent') {
                            $dayTotal += 0;
                            $absentCount++;
                        } else {
                            $dayTotal += $g->grade ?? 0;
                        }
                        $dayCount++;
                    }

                    if ($dayCount === 0) continue;
                    if ($absentCount === $dayCount) {
                        // Nb — hamma dars sababsiz, 0 hisoblanadi
                        $totalDaily += 0;
                    } else {
                        $totalDaily += round($dayTotal / $dayCount);
                    }
                    $daysCount++;
                }

                if ($daysCount > 0) {
                    $subjectAverages[] = round($totalDaily / $daysCount, 2);
                }
            }

            if (empty($subjectAverages)) continue;

            $jnAverage = round(array_sum($subjectAverages) / count($subjectAverages), 2);

            $ratings[] = [
                'student_hemis_id' => $student->hemis_id,
                'full_name' => $student->full_name,
                'group_name' => $student->group_name,
                'department_code' => $student->department_code,
                'department_name' => $student->department_name,
                'specialty_code' => $student->specialty_code,
                'specialty_name' => $student->specialty_name,
                'level_code' => $student->level_code,
                'semester_code' => $sSemester,
                'education_year_code' => $sYear,
                'subjects_count' => count($subjectAverages),
                'jn_average' => $jnAverage,
                'calculated_at' => $now,
            ];
        }

        $bar->finish();
        $this->newLine();
        $this->info("Hisoblangan: " . count($ratings) . " ta talaba");

        // Bazaga yozish
        DB::transaction(function () use ($ratings) {
            // Eski ma'lumotlarni tozalash
            StudentRating::truncate();

            // Yangilarni kiritish
            foreach (array_chunk($ratings, 500) as $chunk) {
                StudentRating::insert(array_map(function ($r) {
                    $r['created_at'] = now();
                    $r['updated_at'] = now();
                    return $r;
                }, $chunk));
            }

            // Rank hisoblash — department + specialty ichida
            DB::statement("
                UPDATE student_ratings sr
                JOIN (
                    SELECT id,
                           RANK() OVER (
                               PARTITION BY department_code, specialty_code
                               ORDER BY jn_average DESC
                           ) as computed_rank
                    FROM student_ratings
                ) ranked ON sr.id = ranked.id
                SET sr.rank = ranked.computed_rank
            ");
        });

        $this->info('Reyting muvaffaqiyatli yangilandi!');
    }
}
