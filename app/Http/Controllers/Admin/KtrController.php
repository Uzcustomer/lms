<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\KtrPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KtrController extends Controller
{
    public function index(Request $request)
    {
        // Ta'lim turlari
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            $selectedEducationType = $educationTypes
                ->first(function ($type) {
                    return str_contains(mb_strtolower($type->education_type_name ?? ''), 'bakalavr');
                })
                ?->education_type_code;
        }

        // Fakultetlar
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Base query
        $baseQuery = function () {
            return DB::table('curriculum_subjects as cs')
                ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
                ->join('semesters as s', function ($join) {
                    $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
                ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
                ->where('cs.is_active', true);
        };

        // Natija query
        $query = $baseQuery()
            ->select([
                'cs.id',
                'cs.subject_id',
                'cs.subject_name',
                'cs.semester_code',
                'cs.semester_name',
                'cs.total_acload',
                'cs.credit',
                'cs.subject_details',
                'c.education_type_name',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
                's.level_code',
            ]);

        // Filtrlar
        if ($selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }

        if ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
        }

        if ($request->filled('specialty')) {
            $query->where('sp.specialty_hemis_id', $request->specialty);
        }

        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }

        // Joriy semestr (default ON)
        if ($request->get('current_semester', '1') == '1') {
            $query->where('s.current', true);
        }

        // Sorting
        $sortColumn = $request->get('sort', 'faculty_name');
        $sortDirection = $request->get('direction', 'asc');

        $sortMap = [
            'faculty_name' => 'f.name',
            'specialty_name' => 'sp.name',
            'level_name' => 's.level_name',
            'semester_name' => 'cs.semester_name',
            'subject_name' => 'cs.subject_name',
            'total_acload' => 'cs.total_acload',
            'credit' => 'cs.credit',
        ];

        $orderByColumn = $sortMap[$sortColumn] ?? 'f.name';
        $query->orderBy($orderByColumn, $sortDirection);

        $perPage = $request->get('per_page', 50);
        $subjects = $query->paginate($perPage)->appends($request->query());

        // subject_details ni parse qilish va training type columnlarini aniqlash
        $trainingTypes = [];
        foreach ($subjects as $item) {
            $details = $item->subject_details;
            if (is_string($details)) {
                $details = json_decode($details, true);
            }
            if (is_array($details)) {
                foreach ($details as $detail) {
                    $code = (string) ($detail['trainingType']['code'] ?? '');
                    $name = $detail['trainingType']['name'] ?? '';
                    if ($code !== '' && $name !== '') {
                        $trainingTypes[$code] = $name;
                    }
                }
            }
        }
        // Mashg'ulot turlarini belgilangan tartibda saralash
        $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];
        $normalize = function ($str) {
            return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        };
        uksort($trainingTypes, function ($a, $b) use ($trainingTypes, $typeOrder, $normalize) {
            $nameA = $normalize($trainingTypes[$a]);
            $nameB = $normalize($trainingTypes[$b]);
            $posA = count($typeOrder);
            $posB = count($typeOrder);
            foreach ($typeOrder as $i => $keyword) {
                if ($posA === count($typeOrder) && str_contains($nameA, $keyword)) $posA = $i;
                if ($posB === count($typeOrder) && str_contains($nameB, $keyword)) $posB = $i;
            }
            return $posA <=> $posB;
        });

        return view('admin.ktr.index', compact(
            'subjects',
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'trainingTypes',
            'sortColumn',
            'sortDirection'
        ));
    }

    /**
     * Yo'nalishlar (specialties) ro'yxatini qaytaradi
     */
    public function getSpecialties(Request $request)
    {
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->where('cs.is_active', true)
            ->whereNotNull('sp.specialty_hemis_id')
            ->whereNotNull('sp.name');

        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }
        if ($request->filled('faculty_id')) {
            $query->where('f.id', $request->faculty_id);
        }
        if ($request->get('current_semester', '1') == '1') {
            $query->where('s.current', true);
        }

        $specialties = $query
            ->select('sp.specialty_hemis_id', 'sp.name')
            ->groupBy('sp.specialty_hemis_id', 'sp.name')
            ->orderBy('sp.name')
            ->get();

        $result = [];
        foreach ($specialties as $sp) {
            $result[$sp->specialty_hemis_id] = $sp->name;
        }

        return response()->json($result);
    }

    /**
     * Kurs (level) kodlari ro'yxatini qaytaradi
     */
    public function getLevelCodes(Request $request)
    {
        $levels = DB::table('semesters')
            ->select('level_code', 'level_name')
            ->whereNotNull('level_code')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get();

        $result = [];
        foreach ($levels as $level) {
            $result[$level->level_code] = $level->level_name;
        }

        return response()->json($result);
    }

    /**
     * Semestrlar ro'yxatini qaytaradi
     */
    public function getSemesters(Request $request)
    {
        $query = DB::table('semesters')
            ->whereNotNull('code')
            ->whereNotNull('name');

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        $semesters = $query
            ->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->get();

        $result = [];
        foreach ($semesters as $semester) {
            $result[$semester->code] = $semester->name;
        }

        return response()->json($result);
    }

    /**
     * Fan uchun KTR rejasini olish
     */
    public function getPlan($curriculumSubjectId)
    {
        $cs = CurriculumSubject::findOrFail($curriculumSubjectId);

        // Mashg'ulot turlarini subject_details dan olish
        $trainingTypes = [];
        $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
        if (is_array($details)) {
            foreach ($details as $detail) {
                $code = (string) ($detail['trainingType']['code'] ?? '');
                $name = $detail['trainingType']['name'] ?? '';
                $hours = (int) ($detail['academic_load'] ?? 0);
                if ($code !== '' && $name !== '') {
                    $trainingTypes[$code] = [
                        'name' => $name,
                        'hours' => $hours,
                    ];
                }
            }
        }

        // Belgilangan tartibda saralash
        $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];
        $normalize = function ($str) {
            return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        };
        uksort($trainingTypes, function ($a, $b) use ($trainingTypes, $typeOrder, $normalize) {
            $nameA = $normalize($trainingTypes[$a]['name']);
            $nameB = $normalize($trainingTypes[$b]['name']);
            $posA = count($typeOrder);
            $posB = count($typeOrder);
            foreach ($typeOrder as $i => $keyword) {
                if ($posA === count($typeOrder) && str_contains($nameA, $keyword)) $posA = $i;
                if ($posB === count($typeOrder) && str_contains($nameB, $keyword)) $posB = $i;
            }
            return $posA <=> $posB;
        });

        $plan = null;
        if (Schema::hasTable('ktr_plans')) {
            $plan = KtrPlan::where('curriculum_subject_id', $curriculumSubjectId)->first();
        }

        return response()->json([
            'subject_name' => $cs->subject_name,
            'total_acload' => (int) $cs->total_acload,
            'training_types' => $trainingTypes,
            'plan' => $plan ? [
                'week_count' => $plan->week_count,
                'plan_data' => $plan->plan_data,
            ] : null,
        ]);
    }

    /**
     * Fan uchun KTR rejasini saqlash
     */
    public function savePlan(Request $request, $curriculumSubjectId)
    {
        $cs = CurriculumSubject::findOrFail($curriculumSubjectId);

        $request->validate([
            'week_count' => 'required|integer|min:1|max:15',
            'plan_data' => 'required|array',
        ]);

        // Jami soatlarni tekshirish
        $totalEntered = 0;
        foreach ($request->plan_data as $weekData) {
            foreach ($weekData as $hours) {
                $totalEntered += (int) $hours;
            }
        }

        if ($totalEntered != (int) $cs->total_acload) {
            return response()->json([
                'success' => false,
                'message' => "Jami soatlar mos kelmadi! Kiritilgan: {$totalEntered}, Jami yuklama: " . (int) $cs->total_acload,
            ], 422);
        }

        if (!Schema::hasTable('ktr_plans')) {
            Schema::create('ktr_plans', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('curriculum_subject_id');
                $table->unsignedSmallInteger('week_count');
                $table->json('plan_data');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique('curriculum_subject_id');
            });
        }

        KtrPlan::updateOrCreate(
            ['curriculum_subject_id' => $curriculumSubjectId],
            [
                'week_count' => $request->week_count,
                'plan_data' => $request->plan_data,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'KTR rejasi muvaffaqiyatli saqlandi!',
        ]);
    }
}
