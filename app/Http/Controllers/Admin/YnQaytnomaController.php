<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\YnSubmission;
use App\Models\DocumentVerification;
use App\Enums\ProjectRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class YnQaytnomaController extends Controller
{
    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    private function baseQuery()
    {
        return DB::table('yn_submissions as ys')
            ->join('groups as g', 'g.group_hemis_id', '=', 'ys.group_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'g.curriculum_hemis_id')
                    ->on('s.code', '=', 'ys.semester_code');
            })
            ->leftJoin('curriculum_subjects as cs', function ($join) {
                $join->on('cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                    ->on('cs.subject_id', '=', 'ys.subject_id')
                    ->on('cs.semester_code', '=', 'ys.semester_code');
            })
            ->leftJoin('departments as d', function ($join) {
                $join->on('d.department_hemis_id', '=', 'g.department_hemis_id')
                    ->where('d.structure_type_code', '=', 11);
            })
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->where('g.active', true);
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->education_type) {
            $query->where('c.education_type_code', $request->education_type);
        }
        if ($request->faculty_id) {
            $query->where('d.id', $request->faculty_id);
        }
        if ($request->specialty_id) {
            $query->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($request->level_code) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->semester_code) {
            $query->where('ys.semester_code', $request->semester_code);
        }
        if ($request->subject_id) {
            $query->where('ys.subject_id', $request->subject_id);
        }
        if ($request->group_id) {
            $query->where('g.group_hemis_id', $request->group_id);
        }
        return $query;
    }

    public function index()
    {
        $educationTypes = $this->baseQuery()
            ->select('c.education_type_code', 'c.education_type_name')
            ->whereNotNull('c.education_type_code')
            ->distinct()
            ->get();

        $faculties = $this->baseQuery()
            ->select('d.id', 'd.name')
            ->whereNotNull('d.id')
            ->distinct()
            ->orderBy('d.name')
            ->get();

        $routePrefix = $this->routePrefix();

        return view('admin.yn_qaytnoma.index', compact('educationTypes', 'faculties', 'routePrefix'));
    }

    public function getData(Request $request)
    {
        $query = $this->baseQuery()
            ->select(
                'g.id as group_id',
                'g.group_hemis_id',
                'g.name as group_name',
                'd.name as faculty_name',
                'sp.name as specialty_name',
                's.level_code',
                's.level_name',
                's.code as semester_code',
                's.name as semester_name',
                'c.education_type_name'
            )
            ->selectRaw('COUNT(DISTINCT ys.subject_id) as subject_count')
            ->selectRaw('MAX(ys.submitted_at) as last_submitted_at');

        $query = $this->applyFilters($query, $request);

        $groups = $query
            ->groupBy(
                'g.group_hemis_id', 'ys.semester_code',
                'g.id', 'g.name', 'd.name', 'sp.name',
                's.level_code', 's.level_name', 's.code', 's.name',
                'c.education_type_name'
            )
            ->orderBy('d.name')
            ->orderBy('sp.name')
            ->orderBy('s.level_code')
            ->orderBy('s.code')
            ->orderBy('g.name')
            ->get();

        return response()->json($groups);
    }

    public function getSpecialties(Request $request)
    {
        $query = $this->baseQuery()
            ->select('sp.specialty_hemis_id as id', 'sp.name')
            ->whereNotNull('sp.specialty_hemis_id');

        $query = $this->applyFilters($query, $request);

        return response()->json($query->distinct()->orderBy('sp.name')->pluck('name', 'id'));
    }

    public function getLevelCodes(Request $request)
    {
        $query = $this->baseQuery()
            ->select('s.level_code', 's.level_name')
            ->whereNotNull('s.level_code');

        $query = $this->applyFilters($query, $request);

        return response()->json($query->distinct()->orderBy('s.level_code')->pluck('level_name', 'level_code'));
    }

    public function getSemesters(Request $request)
    {
        $query = $this->baseQuery()
            ->select('s.code', 's.name')
            ->whereNotNull('s.code');

        $query = $this->applyFilters($query, $request);

        return response()->json($query->distinct()->orderBy('s.code')->pluck('name', 'code'));
    }

    public function getSubjects(Request $request)
    {
        $query = $this->baseQuery()
            ->select('ys.subject_id as id', 'cs.subject_name as name')
            ->whereNotNull('ys.subject_id')
            ->whereNotNull('cs.subject_name');

        $query = $this->applyFilters($query, $request);

        return response()->json($query->distinct()->orderBy('cs.subject_name')->pluck('name', 'id'));
    }

    public function getFilterGroups(Request $request)
    {
        $query = $this->baseQuery()
            ->select('g.group_hemis_id as id', 'g.name');

        $query = $this->applyFilters($query, $request);

        return response()->json($query->distinct()->orderBy('g.name')->pluck('name', 'id'));
    }

    public function generateRuxsatnoma(Request $request)
    {
        $request->validate([
            'groups' => 'required|array|min:1',
            'groups.*.group_hemis_id' => 'required|string',
            'groups.*.semester_code' => 'required|string',
        ]);

        $selectedGroups = $request->groups;
        $files = [];
        $tempDir = storage_path('app/public/yn_qaytnoma');

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($selectedGroups as $groupData) {
            $group = Group::where('group_hemis_id', $groupData['group_hemis_id'])->first();
            if (!$group) continue;

            $semesterCode = $groupData['semester_code'];

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            $department = Department::where('department_hemis_id', $group->department_hemis_id)
                ->where('structure_type_code', 11)
                ->first();

            $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();

            $submissions = YnSubmission::where('group_hemis_id', $groupData['group_hemis_id'])
                ->where('semester_code', $semesterCode)
                ->get();

            $subjects = [];
            foreach ($submissions as $submission) {
                $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('subject_id', $submission->subject_id)
                    ->where('semester_code', $semesterCode)
                    ->first();
                if ($subject) {
                    $subjects[] = $subject->subject_name;
                }
            }

            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Times New Roman');
            $phpWord->setDefaultFontSize(14);

            $section = $phpWord->addSection([
                'marginTop' => 600,
                'marginBottom' => 600,
                'marginLeft' => 1200,
                'marginRight' => 800,
            ]);

            $section->addText(
                'RUXSATNOMA',
                ['bold' => true, 'size' => 18],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
            );

            $section->addText(
                'YN testiga ruxsat berish to\'g\'risida',
                ['bold' => true, 'size' => 14],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 400]
            );

            $section->addText(
                'Sana: ' . now()->format('d.m.Y'),
                ['size' => 12],
                ['alignment' => Jc::RIGHT, 'spaceAfter' => 300]
            );

            $section->addText(
                'Quyidagi guruh YN yuborish jarayonini muvaffaqiyatli yakunlagan va yakuniy nazorat testini topshirishga tayyor:',
                ['size' => 13],
                ['spaceAfter' => 200]
            );

            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 80,
            ];
            $tableName = 'GroupTable_' . $group->id . '_' . $semesterCode;
            $phpWord->addTableStyle($tableName, $tableStyle);
            $table = $section->addTable($tableName);

            $headerStyle = ['bold' => true, 'size' => 12];
            $cellStyle = ['size' => 12];
            $headerBg = ['bgColor' => 'D9E2F3'];

            $rows = [
                ['Fakultet', $department->name ?? '-'],
                ['Yo\'nalish', $specialty->name ?? '-'],
                ['Kurs', $semester->level_name ?? '-'],
                ['Semestr', $semester->name ?? '-'],
                ['Guruh', $group->name ?? '-'],
            ];

            foreach ($rows as $row) {
                $tableRow = $table->addRow();
                $tableRow->addCell(3000, $headerBg)->addText($row[0], $headerStyle);
                $tableRow->addCell(6000)->addText($row[1], $cellStyle);
            }

            $section->addTextBreak(1);

            if (!empty($subjects)) {
                $section->addText(
                    'YN yuborilgan fanlar:',
                    ['bold' => true, 'size' => 13],
                    ['spaceAfter' => 100]
                );

                foreach ($subjects as $idx => $subjectName) {
                    $section->addText(
                        ($idx + 1) . '. ' . $subjectName,
                        ['size' => 12],
                        ['spaceAfter' => 50]
                    );
                }
            }

            $section->addTextBreak(2);

            $section->addText(
                'Ushbu guruh yakuniy nazorat testini topshirishga ruxsat etiladi.',
                ['bold' => true, 'size' => 13],
                ['spaceAfter' => 400]
            );

            $section->addText(
                'Test markazi rahbari: ___________________',
                ['size' => 12],
                ['spaceAfter' => 200]
            );

            $section->addText(
                'Sana: ' . now()->format('d.m.Y'),
                ['size' => 12]
            );

            $fileName = 'Ruxsatnoma_' . str_replace(' ', '_', $group->name) . '_' . $semesterCode . '.docx';
            $tempPath = $tempDir . '/' . time() . '_' . $fileName;

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            $files[] = [
                'path' => $tempPath,
                'name' => $fileName,
            ];
        }

        if (count($files) === 0) {
            return response()->json(['error' => 'Hech qanday guruh topilmadi'], 404);
        }

        if (count($files) === 1) {
            return response()->download($files[0]['path'], $files[0]['name'])->deleteFileAfterSend(true);
        }

        $zipPath = $tempDir . '/' . time() . '_ruxsatnomalar.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();

        foreach ($files as $file) {
            @unlink($file['path']);
        }

        return response()->download($zipPath, 'Ruxsatnomalar_' . now()->format('d_m_Y') . '.zip')->deleteFileAfterSend(true);
    }

    public function generateYnOldiWord(Request $request)
    {
        $request->validate([
            'groups' => 'required|array|min:1',
            'groups.*.group_hemis_id' => 'required|string',
            'groups.*.semester_code' => 'required|string',
        ]);

        $selectedGroups = $request->groups;
        $files = [];
        $tempDir = storage_path('app/public/yn_oldi_qaydnoma');

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Step 1: Collect all data and group by subject_id
        $subjectGroups = []; // subject_id => [ 'subject' => ..., 'entries' => [ ['group' => ..., 'students' => ..., ...], ... ] ]

        foreach ($selectedGroups as $groupData) {
            $group = Group::where('group_hemis_id', $groupData['group_hemis_id'])->first();
            if (!$group) continue;

            $semesterCode = $groupData['semester_code'];

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            $department = Department::where('department_hemis_id', $group->department_hemis_id)
                ->where('structure_type_code', 11)
                ->first();

            $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();

            $submissions = YnSubmission::where('group_hemis_id', $groupData['group_hemis_id'])
                ->where('semester_code', $semesterCode)
                ->get();

            foreach ($submissions as $submission) {
                $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('subject_id', $submission->subject_id)
                    ->where('semester_code', $semesterCode)
                    ->first();

                if (!$subject) continue;

                $currentDate = now();
                $lessonCount = Schedule::where('subject_id', $subject->subject_id)
                    ->where('group_id', $group->group_hemis_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->where('lesson_date', '<=', $currentDate)
                    ->distinct('lesson_date')
                    ->count();

                if ($lessonCount == 0) $lessonCount = 1;

                $students = Student::selectRaw('
                    students.full_name as student_name,
                    students.student_id_number as student_id,
                    students.hemis_id as hemis_id,
                    ROUND(
                        (SELECT sum(inner_table.average_grade) / ' . $lessonCount . '
                        FROM (
                            SELECT lesson_date, AVG(COALESCE(
                                CASE
                                    WHEN status = "retake" AND (reason = "absent" OR reason = "teacher_victim")
                                    THEN retake_grade
                                    WHEN status = "retake" AND reason = "low_grade"
                                    THEN retake_grade
                                    WHEN status = "pending" AND reason = "absent"
                                    THEN grade
                                    ELSE grade
                                END, 0)) AS average_grade
                            FROM student_grades
                            WHERE student_grades.student_hemis_id = students.hemis_id
                            AND student_grades.subject_id = ' . $subject->subject_id . '
                            AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                            GROUP BY student_grades.lesson_date
                        ) AS inner_table)
                    ) as jn,
                    ROUND(
                        (SELECT avg(student_grades.grade) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.training_type_code = 99
                        GROUP BY student_grades.student_hemis_id)
                    ) as mt
                ')
                    ->where('students.group_id', $group->group_hemis_id)
                    ->groupBy('students.id')
                    ->orderBy('students.full_name')
                    ->get();

                // Get teachers for this subject and group
                $studentIds = Student::where('group_id', $group->group_hemis_id)
                    ->groupBy('hemis_id')
                    ->pluck('hemis_id');

                $maruzaTeacher = DB::table('student_grades as s')
                    ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                    ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                    ->where('s.subject_id', $subject->subject_id)
                    ->where('s.training_type_code', 11)
                    ->whereIn('s.student_hemis_id', $studentIds)
                    ->groupBy('s.employee_id')
                    ->first();

                $otherTeachers = DB::table('student_grades as s')
                    ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                    ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                    ->where('s.subject_id', $subject->subject_id)
                    ->where('s.training_type_code', '!=', 11)
                    ->whereIn('s.student_hemis_id', $studentIds)
                    ->groupBy('s.employee_id')
                    ->get();

                $otherTeacherNames = [];
                foreach ($otherTeachers as $t) {
                    foreach (explode(', ', $t->full_names) as $name) {
                        $name = trim($name);
                        if ($name && !in_array($name, $otherTeacherNames)) {
                            $otherTeacherNames[] = $name;
                        }
                    }
                }

                $maruzaTeacherNames = [];
                if ($maruzaTeacher && $maruzaTeacher->full_names) {
                    foreach (explode(', ', $maruzaTeacher->full_names) as $name) {
                        $name = trim($name);
                        if ($name && !in_array($name, $maruzaTeacherNames)) {
                            $maruzaTeacherNames[] = $name;
                        }
                    }
                }

                $subjectKey = $subject->subject_id;
                if (!isset($subjectGroups[$subjectKey])) {
                    $subjectGroups[$subjectKey] = [
                        'subject' => $subject,
                        'semester' => $semester,
                        'department' => $department,
                        'specialty' => $specialty,
                        'groupNames' => [],
                        'allMaruzaTeachers' => [],
                        'allOtherTeachers' => [],
                        'entries' => [],
                    ];
                }

                // Accumulate group names
                if ($group->name && !in_array($group->name, $subjectGroups[$subjectKey]['groupNames'])) {
                    $subjectGroups[$subjectKey]['groupNames'][] = $group->name;
                }

                // Accumulate lecture teachers
                foreach ($maruzaTeacherNames as $name) {
                    if (!in_array($name, $subjectGroups[$subjectKey]['allMaruzaTeachers'])) {
                        $subjectGroups[$subjectKey]['allMaruzaTeachers'][] = $name;
                    }
                }

                // Accumulate practical teachers
                foreach ($otherTeacherNames as $name) {
                    if (!in_array($name, $subjectGroups[$subjectKey]['allOtherTeachers'])) {
                        $subjectGroups[$subjectKey]['allOtherTeachers'][] = $name;
                    }
                }

                $subjectGroups[$subjectKey]['entries'][] = [
                    'group' => $group,
                    'semester' => $semester,
                    'department' => $department,
                    'students' => $students,
                    'subject' => $subject,
                ];
            }
        }

        // Step 2: Generate one Word document per subject
        foreach ($subjectGroups as $subjectKey => $subjectData) {
            $subject = $subjectData['subject'];
            $semester = $subjectData['semester'];
            $department = $subjectData['department'];
            $groupNames = $subjectData['groupNames'];
            $allMaruzaText = implode(', ', $subjectData['allMaruzaTeachers']) ?: '-';
            $allOtherText = implode(', ', $subjectData['allOtherTeachers']) ?: '-';

            // Build Word document
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Times New Roman');
            $phpWord->setDefaultFontSize(12);

            $section = $phpWord->addSection([
                'orientation' => 'landscape',
                'marginTop' => 600,
                'marginBottom' => 600,
                'marginLeft' => 800,
                'marginRight' => 600,
            ]);

            // Header - 12-shakl
            $section->addText(
                '12-shakl',
                ['bold' => true, 'size' => 11],
                ['alignment' => Jc::END, 'spaceAfter' => 100]
            );

            // Title
            $section->addText(
                'YAKUNIY NAZORAT OLDIDAN QAYDNOMA',
                ['bold' => true, 'size' => 14],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
            );

            // Info section
            $infoStyle = ['size' => 11];
            $infoBold = ['bold' => true, 'size' => 11];
            $infoParaStyle = ['spaceAfter' => 40];

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Fakultet: ', $infoBold);
            $textRun->addText($department->name ?? '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Kurs: ', $infoBold);
            $textRun->addText($semester->level_name ?? '-', $infoStyle);
            $textRun->addText('     Semestr: ', $infoBold);
            $textRun->addText($semester->name ?? '-', $infoStyle);
            $textRun->addText('     Guruh: ', $infoBold);
            $textRun->addText(implode(', ', $groupNames) ?: '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Fan: ', $infoBold);
            $textRun->addText($subject->subject_name ?? '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText("Ma'ruzachi: ", $infoBold);
            $textRun->addText($allMaruzaText, $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText("Amaliyot o'qituvchilari: ", $infoBold);
            $textRun->addText($allOtherText, $infoStyle);

            $textRun = $section->addTextRun(['spaceAfter' => 150]);
            $textRun->addText('Soatlar soni: ', $infoBold);
            $textRun->addText($subject->total_acload ?? '-', $infoStyle);

            $section->addText(
                'Sana: ' . now()->format('d.m.Y'),
                $infoStyle,
                ['alignment' => Jc::END, 'spaceAfter' => 150]
            );

            // Table
            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 40,
            ];
            $tableName = 'YnOldiTable_' . $subjectKey;
            $phpWord->addTableStyle($tableName, $tableStyle);
            $table = $section->addTable($tableName);

            $headerFont = ['bold' => true, 'size' => 10];
            $cellFont = ['size' => 10];
            $cellFontRed = ['size' => 10, 'color' => 'FF0000'];
            $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
            $groupSeparatorBg = ['bgColor' => 'E2EFDA', 'valign' => 'center', 'gridSpan' => 7];
            $cellCenter = ['alignment' => Jc::CENTER];
            $cellLeft = ['alignment' => Jc::START];

            // Header row
            $headerRow = $table->addRow(400);
            $headerRow->addCell(600, $headerBg)->addText('№', $headerFont, $cellCenter);
            $headerRow->addCell(4500, $headerBg)->addText('Talaba F.I.O', $headerFont, $cellCenter);
            $headerRow->addCell(1800, $headerBg)->addText('Talaba ID', $headerFont, $cellCenter);
            $headerRow->addCell(1200, $headerBg)->addText('JN', $headerFont, $cellCenter);
            $headerRow->addCell(1200, $headerBg)->addText("O'N", $headerFont, $cellCenter);
            $headerRow->addCell(1500, $headerBg)->addText('Davomat %', $headerFont, $cellCenter);
            $headerRow->addCell(2000, $headerBg)->addText('YN ga ruxsat', $headerFont, $cellCenter);

            // Data rows - iterate through all groups for this subject
            $rowNum = 1;
            $multipleGroups = count($subjectData['entries']) > 1;

            foreach ($subjectData['entries'] as $entry) {
                $entryGroup = $entry['group'];
                $entryStudents = $entry['students'];
                $entrySubject = $entry['subject'];

                // Add group separator row if multiple groups
                if ($multipleGroups) {
                    $separatorRow = $table->addRow(350);
                    $separatorRow->addCell(12800, $groupSeparatorBg)->addText(
                        $entryGroup->name,
                        ['bold' => true, 'size' => 10, 'color' => '1F4E20'],
                        $cellCenter
                    );
                }

                foreach ($entryStudents as $student) {
                    $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);

                    $qoldirgan = (int) Attendance::where('group_id', $entryGroup->group_hemis_id)
                        ->where('subject_id', $entrySubject->subject_id)
                        ->where('student_hemis_id', $student->hemis_id)
                        ->sum('absent_off');

                    $totalAcload = $entrySubject->total_acload ?: 1;
                    $qoldiq = round($qoldirgan * 100 / $totalAcload, 2);

                    $holat = 'Ruxsat';
                    $jnFailed = false;
                    $mtFailed = false;
                    $davomatFailed = false;

                    if ($student->jn < $markingScore->effectiveLimit('jn')) {
                        $jnFailed = true;
                        $holat = 'X';
                    }
                    if ($student->mt < $markingScore->effectiveLimit('mt')) {
                        $mtFailed = true;
                        $holat = 'X';
                    }
                    if ($qoldiq > 25) {
                        $davomatFailed = true;
                        $holat = 'X';
                    }

                    $dataRow = $table->addRow();
                    $dataRow->addCell(600)->addText($rowNum, $cellFont, $cellCenter);
                    $dataRow->addCell(4500)->addText($student->student_name, $cellFont, $cellLeft);
                    $dataRow->addCell(1800)->addText($student->student_id, $cellFont, $cellCenter);

                    $jnCell = $dataRow->addCell(1200);
                    $jnCell->addText(
                        $student->jn ?? '0',
                        $jnFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $mtCell = $dataRow->addCell(1200);
                    $mtCell->addText(
                        $student->mt ?? '0',
                        $mtFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $davomatCell = $dataRow->addCell(1500);
                    $davomatCell->addText(
                        ($qoldiq != 0 ? $qoldiq . '%' : '0%'),
                        $davomatFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $holatCell = $dataRow->addCell(2000);
                    $holatCell->addText(
                        $holat,
                        $holat === 'X' ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $rowNum++;
                }
            }

            // Signatures
            $section->addTextBreak(1);

            $dekan = Teacher::whereHas('deanFaculties', fn($q) => $q->where('dean_faculties.department_hemis_id', $department->department_hemis_id ?? ''))
                ->whereHas('roles', fn($q) => $q->where('name', ProjectRole::DEAN->value))
                ->first();

            $signTable = $section->addTable();

            $signRow = $signTable->addRow();
            $signRow->addCell(6500)->addText("Dekan: ___________________  " . ($dekan->full_name ?? ''), ['size' => 11]);
            $signRow->addCell(6500)->addText("Ma'ruzachi: ___________________  " . ($allMaruzaText), ['size' => 11]);

            // QR code yaratish
            $generatedBy = null;
            if (auth()->guard('teacher')->check()) {
                $generatedBy = auth()->guard('teacher')->user()->full_name ?? auth()->guard('teacher')->user()->name ?? null;
            } elseif (auth()->guard('web')->check()) {
                $generatedBy = auth()->guard('web')->user()->name ?? null;
            }

            $verification = DocumentVerification::createForDocument([
                'document_type' => 'YN oldi qaydnoma',
                'subject_name' => $subject->subject_name,
                'group_names' => implode(', ', $groupNames),
                'semester_name' => $semester->name ?? null,
                'department_name' => $department->name ?? null,
                'generated_by' => $generatedBy,
            ]);

            $verificationUrl = $verification->getVerificationUrl();
            $qrImagePath = null;

            try {
                $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($verificationUrl) . '&size=200x200';
                $client = new \GuzzleHttp\Client();
                $qrTempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_qr.png';
                $response = $client->request('GET', $qrApiUrl, [
                    'verify' => false,
                    'sink' => $qrTempPath,
                    'timeout' => 10,
                ]);
                if ($response->getStatusCode() == 200 && file_exists($qrTempPath) && filesize($qrTempPath) > 0) {
                    $qrImagePath = $qrTempPath;
                }
            } catch (\Exception $e) {
                // QR code generatsiyasi muvaffaqiyatsiz bo'lsa, davom etamiz
            }

            if ($qrImagePath) {
                $section->addTextBreak(1);
                $section->addImage($qrImagePath, [
                    'width' => 80,
                    'height' => 80,
                    'alignment' => Jc::START,
                ]);
                $section->addText(
                    'Hujjat haqiqiyligini tekshirish uchun QR kodni skanerlang',
                    ['size' => 8, 'italic' => true, 'color' => '666666'],
                    ['spaceAfter' => 0]
                );
            }

            $groupNamesStr = str_replace(['/', '\\', ' '], '_', implode('_', $groupNames));
            $subjectNameStr = str_replace(['/', '\\', ' '], '_', $subject->subject_name);
            $fileName = 'YN_oldi_qaydnoma_' . $groupNamesStr . '_' . $subjectNameStr . '.docx';
            $tempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $fileName;

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            // QR vaqtinchalik faylni tozalash
            if ($qrImagePath) {
                @unlink($qrImagePath);
            }

            $files[] = [
                'path' => $tempPath,
                'name' => $fileName,
            ];
        }

        if (count($files) === 0) {
            return response()->json(['error' => 'Hech qanday ma\'lumot topilmadi'], 404);
        }

        if (count($files) === 1) {
            return response()->download($files[0]['path'], $files[0]['name'])->deleteFileAfterSend(true);
        }

        $zipPath = $tempDir . '/' . time() . '_yn_oldi_qaydnomalar.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();

        foreach ($files as $file) {
            @unlink($file['path']);
        }

        return response()->download($zipPath, 'YN_oldi_qaydnomalar_' . now()->format('d_m_Y') . '.zip')->deleteFileAfterSend(true);
    }
}
