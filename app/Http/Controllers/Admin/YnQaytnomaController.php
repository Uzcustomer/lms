<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\YnSubmission;
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
}
