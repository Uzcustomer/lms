<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumSubjectTeacher;
use App\Models\Department;
use App\Models\KtrChangeApproval;
use App\Models\KtrChangeRequest;
use App\Models\KtrPlan;
use App\Models\Teacher;
use App\Models\Notification;
use App\Models\TeacherNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
                ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id');
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

        if ($request->filled('subject_name')) {
            $query->where('cs.subject_name', 'like', '%' . $request->subject_name . '%');
        }

        // Faol/nofaol fanlar filtri (default: faol)
        $activeFilter = $request->get('active_filter', 'active');
        if ($activeFilter === 'active') {
            $query->where('cs.is_active', true);
        } elseif ($activeFilter === 'inactive') {
            $query->where('cs.is_active', false);
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
     * Fanlar ro'yxatini qaytaradi (filter uchun)
     */
    public function getSubjects(Request $request)
    {
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->where('cs.is_active', true)
            ->whereNotNull('cs.subject_name');

        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }
        if ($request->get('current_semester', '1') == '1') {
            $query->where('s.current', true);
        }

        $subjects = $query
            ->select('cs.subject_name')
            ->groupBy('cs.subject_name')
            ->orderBy('cs.subject_name')
            ->get();

        $result = [];
        foreach ($subjects as $s) {
            $result[$s->subject_name] = $s->subject_name;
        }

        return response()->json($result);
    }

    /**
     * Excelga export
     */
    public function export(Request $request)
    {
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->select([
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
                'cs.semester_name',
                'cs.subject_name',
                'cs.credit',
                'cs.total_acload',
                'cs.subject_details',
            ]);

        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
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
        if ($request->filled('subject_name')) {
            $query->where('cs.subject_name', 'like', '%' . $request->subject_name . '%');
        }

        $activeFilter = $request->get('active_filter', 'active');
        if ($activeFilter === 'active') {
            $query->where('cs.is_active', true);
        } elseif ($activeFilter === 'inactive') {
            $query->where('cs.is_active', false);
        }

        if ($request->get('current_semester', '1') == '1') {
            $query->where('s.current', true);
        }

        $query->orderBy('f.name')->orderBy('cs.subject_name');
        $items = $query->get();

        // Barcha training type larni yig'ish
        $allTypes = [];
        foreach ($items as $item) {
            $details = is_string($item->subject_details) ? json_decode($item->subject_details, true) : $item->subject_details;
            if (is_array($details)) {
                foreach ($details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $name = $d['trainingType']['name'] ?? '';
                    if ($code !== '' && $name !== '') {
                        $allTypes[$code] = $name;
                    }
                }
            }
        }

        // Tartibda saralash
        $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];
        $normalize = function ($str) {
            return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        };
        uksort($allTypes, function ($a, $b) use ($allTypes, $typeOrder, $normalize) {
            $nameA = $normalize($allTypes[$a]);
            $nameB = $normalize($allTypes[$b]);
            $posA = count($typeOrder);
            $posB = count($typeOrder);
            foreach ($typeOrder as $i => $kw) {
                if ($posA === count($typeOrder) && str_contains($nameA, $kw)) $posA = $i;
                if ($posB === count($typeOrder) && str_contains($nameB, $kw)) $posB = $i;
            }
            return $posA <=> $posB;
        });

        $typeCodes = array_keys($allTypes);

        // CSV yaratish
        $filename = 'KTR_' . date('Y-m-d_H-i') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($items, $allTypes, $typeCodes) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8

            // Header
            $header = ['#', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Fan', 'Kredit', 'Jami yuklama'];
            foreach ($allTypes as $name) {
                $header[] = $name;
            }
            fputcsv($file, $header, ';');

            // Data
            $i = 1;
            foreach ($items as $item) {
                $details = is_string($item->subject_details) ? json_decode($item->subject_details, true) : $item->subject_details;
                $loadMap = [];
                if (is_array($details)) {
                    foreach ($details as $d) {
                        $tCode = (string) ($d['trainingType']['code'] ?? '');
                        $hours = (int) ($d['academic_load'] ?? 0);
                        if ($tCode !== '') $loadMap[$tCode] = $hours;
                    }
                }

                $row = [
                    $i++,
                    $item->faculty_name ?? '-',
                    $item->specialty_name ?? '-',
                    $item->level_name ?? '-',
                    $item->semester_name ?? '-',
                    $item->subject_name ?? '-',
                    $item->credit ?? '-',
                    $item->total_acload ?? '-',
                ];
                foreach ($typeCodes as $code) {
                    $row[] = isset($loadMap[$code]) && $loadMap[$code] > 0 ? $loadMap[$code] : '-';
                }
                fputcsv($file, $row, ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Foydalanuvchi ushbu fan KTR rejasini tahrirlash huquqiga ega ekanligini tekshirish
     */
    private function canEditSubjectKtr(CurriculumSubject $cs): bool
    {
        $user = auth()->user();

        // Superadmin/admin har doim tahrirlashi mumkin
        if ($user->hasRole(['superadmin', 'admin', 'kichik_admin'])) {
            return true;
        }

        // O'qituvchi/fan mas'uli - faqat o'z fanlari uchun
        if ($user instanceof Teacher && $user->hemis_id) {
            try {
                return CurriculumSubjectTeacher::where('employee_id', $user->hemis_id)
                    ->where('subject_id', $cs->subject_id)
                    ->where('active', true)
                    ->exists();
            } catch (\Exception $e) {
                Log::warning('canEditSubjectKtr: curriculum_subject_teachers query failed', ['error' => $e->getMessage()]);
                return false;
            }
        }

        // Registrator ofisi va boshqa rollar - faqat ko'rish
        return false;
    }

    /**
     * Fan uchun KTR rejasini olish
     */
    public function getPlan($curriculumSubjectId)
    {
        try {
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
            if (count($trainingTypes) > 1) {
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
            }

            $plan = null;
            if (Schema::hasTable('ktr_plans')) {
                $plan = KtrPlan::where('curriculum_subject_id', $curriculumSubjectId)->first();
            }

            // HEMIS dan mavzularni olish
            $hemisTopics = $this->fetchHemisTopics($cs);

            // O'zgartirish so'rovi holati
            $changeRequest = null;
            if ($plan && Schema::hasTable('ktr_change_requests') && Schema::hasTable('ktr_change_approvals')) {
                $cr = KtrChangeRequest::where('curriculum_subject_id', $curriculumSubjectId)
                    ->where('status', 'pending')
                    ->latest()
                    ->with('approvals')
                    ->first();
                if ($cr) {
                    $changeRequest = [
                        'id' => $cr->id,
                        'status' => $cr->status,
                        'approvals' => $cr->approvals->map(fn ($a) => [
                            'id' => $a->id,
                            'role' => $a->role,
                            'approver_name' => $a->approver_name,
                            'status' => $a->status,
                            'responded_at' => $a->responded_at?->format('d.m.Y H:i'),
                        ]),
                        'is_approved' => $cr->isFullyApproved(),
                        'draft_week_count' => $cr->draft_week_count,
                        'draft_plan_data' => $cr->draft_plan_data,
                    ];
                }
            }

            // Kafedra va fakultet ma'lumotlari
            $approverInfo = $this->getApproverInfo($cs);

            return response()->json([
                'subject_name' => $cs->subject_name,
                'total_acload' => (int) $cs->total_acload,
                'training_types' => $trainingTypes,
                'hemis_topics' => $hemisTopics,
                'can_edit' => $this->canEditSubjectKtr($cs),
                'approver_info' => $approverInfo,
                'change_request' => $changeRequest,
                'plan' => $plan ? [
                    'week_count' => $plan->week_count,
                    'plan_data' => $plan->plan_data,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('KTR getPlan xatolik', [
                'curriculum_subject_id' => $curriculumSubjectId,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * HEMIS API dan fan mavzularini olish (training type bo'yicha guruhlangan)
     */
    private function fetchHemisTopics(CurriculumSubject $cs): array
    {
        $baseUrl = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz/rest'), '/');
        if (!str_contains($baseUrl, '/v1')) {
            $baseUrl .= '/v1';
        }
        $token = config('services.hemis.token');

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->get($baseUrl . '/data/curriculum-subject-topic-list', [
                    '_curriculum' => $cs->curricula_hemis_id,
                    '_semester' => $cs->semester_code,
                    'limit' => 200,
                    'page' => 1,
                ]);

            if (!$response->successful()) {
                return [];
            }

            $items = $response->json('data.items') ?? [];

            // Fan bo'yicha filtrlash
            $subjectId = (int) $cs->subject_id;
            $items = array_filter($items, function ($item) use ($subjectId) {
                return isset($item['subject']['id']) && (int) $item['subject']['id'] === $subjectId;
            });

            // Training type bo'yicha guruhlash va position bo'yicha tartiblash
            $topicsByType = [];
            foreach ($items as $item) {
                $typeCode = (string) ($item['_training_type'] ?? '');
                if ($typeCode === '') continue;

                if (!isset($topicsByType[$typeCode])) {
                    $topicsByType[$typeCode] = [];
                }
                $topicsByType[$typeCode][] = [
                    'position' => (int) ($item['position'] ?? 0),
                    'name' => $item['name'] ?? '',
                ];
            }

            // Position bo'yicha tartiblash
            foreach ($topicsByType as &$topics) {
                usort($topics, fn($a, $b) => $a['position'] <=> $b['position']);
            }

            return $topicsByType;
        } catch (\Exception $e) {
            Log::warning('KTR hemis topics fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fan uchun KTR rejasini saqlash
     * Mavjud reja bo'lsa - draft sifatida saqlanadi va tasdiqlash so'rovi yuboriladi
     */
    public function savePlan(Request $request, $curriculumSubjectId)
    {
        $cs = CurriculumSubject::findOrFail($curriculumSubjectId);

        if (!$this->canEditSubjectKtr($cs)) {
            return response()->json([
                'success' => false,
                'message' => 'Sizda ushbu fan KTR rejasini tahrirlash huquqi yo\'q.',
            ], 403);
        }

        $user = auth()->user();
        $isAdmin = $user->hasRole(['superadmin', 'admin', 'kichik_admin']);
        $existingPlan = Schema::hasTable('ktr_plans')
            ? KtrPlan::where('curriculum_subject_id', $curriculumSubjectId)->first()
            : null;

        $request->validate([
            'week_count' => 'required|integer|min:1|max:18',
            'plan_data' => 'required|array',
        ]);

        // Yangi format: plan_data = {hours: {...}, topics: {...}}
        $planData = $request->plan_data;
        $hoursData = $planData['hours'] ?? $planData;

        // Jami soatlarni tekshirish (mustaqil ta'lim soatlarini ham hisoblash)
        $totalEntered = 0;
        foreach ($hoursData as $weekData) {
            if (is_array($weekData)) {
                foreach ($weekData as $hours) {
                    $totalEntered += (int) $hours;
                }
            }
        }

        // Mustaqil ta'lim soatlarini qo'shish (avtomatik)
        $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
        if (is_array($details)) {
            $normalize = function ($str) {
                return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
            };
            foreach ($details as $detail) {
                $name = $detail['trainingType']['name'] ?? '';
                if (preg_match('/mustaqil/i', $normalize($name))) {
                    $totalEntered += (int) ($detail['academic_load'] ?? 0);
                }
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

        // Mavjud reja bo'lsa va admin bo'lmasa - DRAFT sifatida saqlash
        if ($existingPlan && !$isAdmin && Schema::hasTable('ktr_change_requests')) {
            // Allaqachon pending so'rov bormi?
            $existingRequest = KtrChangeRequest::where('curriculum_subject_id', $curriculumSubjectId)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allaqachon tasdiqlash kutilayotgan so\'rov mavjud.',
                ], 422);
            }

            // Approver ma'lumotlarini olish
            $approverInfo = $this->getApproverInfo($cs);

            // Change request yaratish (draft bilan)
            $cr = KtrChangeRequest::create([
                'curriculum_subject_id' => $curriculumSubjectId,
                'requested_by' => $user->id,
                'requested_by_guard' => $user instanceof Teacher ? 'teacher' : 'web',
                'draft_week_count' => $request->week_count,
                'draft_plan_data' => $request->plan_data,
            ]);

            // Tasdiqlash yozuvlarini yaratish
            foreach (['kafedra_mudiri' => $approverInfo['kafedra_mudiri'] ?? [], 'dekan' => $approverInfo['dekan'] ?? []] as $role => $data) {
                KtrChangeApproval::create([
                    'change_request_id' => $cr->id,
                    'role' => $role,
                    'approver_name' => $data['name'] ?? 'Topilmadi',
                    'approver_id' => $data['id'] ?? null,
                ]);
            }

            KtrChangeApproval::create([
                'change_request_id' => $cr->id,
                'role' => 'registrator_ofisi',
                'approver_name' => 'Registrator ofisi xodimlari',
                'approver_id' => null,
            ]);

            $cr->load('approvals');

            // Xabarnoma yuborish (o'zgarishlar bilan)
            $this->sendApproverNotifications($cr, $cs, $user, $approverInfo);

            return response()->json([
                'success' => true,
                'is_draft' => true,
                'message' => 'O\'zgarishlar draft sifatida saqlandi. Tasdiqlash so\'rovi jo\'natildi!',
                'change_request' => [
                    'id' => $cr->id,
                    'status' => $cr->status,
                    'approvals' => $cr->approvals->map(fn ($a) => [
                        'id' => $a->id,
                        'role' => $a->role,
                        'approver_name' => $a->approver_name,
                        'status' => $a->status,
                        'responded_at' => null,
                    ]),
                    'is_approved' => false,
                    'draft_week_count' => $cr->draft_week_count,
                    'draft_plan_data' => $cr->draft_plan_data,
                ],
            ]);
        }

        // Yangi reja yoki admin - to'g'ridan-to'g'ri saqlash
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

    /**
     * Kafedra nomiga qarab tegishli fakultetni topish (aniq ro'yxat asosida)
     *
     * 1-son Davolash: Akusherlik, Ichki kasalliklar propedevtikasi, Ijtimoiy-gumanitar, Patologik anatomiya, Xirurgik kasalliklar
     * 2-son Davolash: Anatomiya va klinik, Ichki kasalliklar/harbiy dala, Travmatologiya, Umumiy xirurgiya, Yuqumli kasalliklar
     * Pediatriya: Bolalar kasalliklari, Mikrobiologiya, Normal va patologik fiziologiya, Otorinolaringologiya, Tibbiy psixologiya
     * Xalqaro ta'lim: Farmakologiya, O'zbek va xorijiy tillar, Tibbiy biologiya, Tibbiy va biologik kimyo
     */
    private function findFacultyByKafedraName(string $kafedraName): ?Department
    {
        $kafedraName = mb_strtolower($kafedraName);

        // Kafedra nomi kalit so'zlari -> fakultet nomi LIKE pattern
        $mappings = [
            // 1-SON DAVOLASH FAKULTETI
            ['keywords' => ['akusherlik', 'ginekologiya'], 'faculty' => '%1-son Davolash%'],
            ['keywords' => ['ichki kasalliklar', 'propedevtikasi', 'reabilitologiya'], 'faculty' => '%1-son Davolash%'],
            ['keywords' => ['ijtimoiy', 'gumanitar'], 'faculty' => '%1-son Davolash%'],
            ['keywords' => ['patologik anatomiya', 'sud tibbiyoti'], 'faculty' => '%1-son Davolash%'],
            ['keywords' => ['xirurgik kasalliklar', 'oilaviy shifokorlikda xirurgiya'], 'faculty' => '%1-son Davolash%'],

            // 2-SON DAVOLASH FAKULTETI
            ['keywords' => ['anatomiya', 'klinik anatomiya'], 'faculty' => '%2-son Davolash%'],
            ['keywords' => ['ichki kasalliklar', 'harbiy dala terapiyasi', 'gematologiya'], 'faculty' => '%2-son Davolash%'],
            ['keywords' => ['travmatologiya', 'ortopediya', 'harbiy dala jarrohligi'], 'faculty' => '%2-son Davolash%'],
            ['keywords' => ['umumiy xirurgiya', 'bolalar xirurgiyasi', 'urologiya'], 'faculty' => '%2-son Davolash%'],
            ['keywords' => ['yuqumli kasalliklar', 'dermatovenerologiya'], 'faculty' => '%2-son Davolash%'],

            // PEDIATRIYA FAKULTETI
            ['keywords' => ['bolalar kasalliklari', 'propedevtikasi', 'pediatriya'], 'faculty' => '%Pediatriya%'],
            ['keywords' => ['mikrobiologiya', 'jamoat salomatligi', 'gigiyena'], 'faculty' => '%Pediatriya%'],
            ['keywords' => ['normal', 'patologik fiziologiya'], 'faculty' => '%Pediatriya%'],
            ['keywords' => ['otorinolaringologiya', 'oftalmologiya', 'onkologiya'], 'faculty' => '%Pediatriya%'],
            ['keywords' => ['tibbiy psixologiya', 'nevrologiya', 'psixiatriya'], 'faculty' => '%Pediatriya%'],

            // XALQARO TA'LIM FAKULTETI
            ['keywords' => ['farmakologiya', 'klinik farmakologiya'], 'faculty' => '%Xalqaro%'],
            ['keywords' => ['xorijiy tillar'], 'faculty' => '%Xalqaro%'],
            ['keywords' => ['tibbiy biologiya', 'gistologiya'], 'faculty' => '%Xalqaro%'],
            ['keywords' => ['tibbiy va biologik kimyo'], 'faculty' => '%Xalqaro%'],
        ];

        foreach ($mappings as $mapping) {
            $allMatch = true;
            foreach ($mapping['keywords'] as $keyword) {
                if (!str_contains($kafedraName, mb_strtolower($keyword))) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return Department::where('structure_type_code', '11')
                    ->where('name', 'LIKE', $mapping['faculty'])
                    ->first();
            }
        }

        return null;
    }

    /**
     * Kafedra mudiri, dekan va registrator ma'lumotlarini olish
     */
    private function getApproverInfo(CurriculumSubject $cs): array
    {
        $info = [
            'kafedra_name' => '',
            'faculty_name' => '',
            'kafedra_mudiri' => null,
            'dekan' => null,
            'registrator' => null,
            'registrators' => [],
        ];

        try {
            // Kafedra (CurriculumSubject.department_id = kafedra HEMIS ID)
            $kafedra = Department::where('department_hemis_id', $cs->department_id)->first();
            if ($kafedra) {
                $info['kafedra_name'] = $kafedra->name;

                // Kafedra mudiri - Spatie role orqali
                $mudiri = Teacher::whereHas('roles', fn ($q) => $q->where('name', 'kafedra_mudiri'))
                    ->where('department_hemis_id', $kafedra->department_hemis_id)
                    ->where('is_active', true)
                    ->first();
                // Fallback: role ustuni orqali
                if (!$mudiri) {
                    $mudiri = Teacher::where('role', 'kafedra_mudiri')
                        ->where('department_hemis_id', $kafedra->department_hemis_id)
                        ->where('is_active', true)
                        ->first();
                }
                // Fallback: HEMIS staff_position ustuni orqali
                if (!$mudiri) {
                    $mudiri = Teacher::where('staff_position', 'LIKE', '%mudiri%')
                        ->where('department_hemis_id', $kafedra->department_hemis_id)
                        ->where('is_active', true)
                        ->first();
                }
                if ($mudiri) {
                    $info['kafedra_mudiri'] = ['id' => $mudiri->id, 'name' => $mudiri->full_name];
                }
            }

            // Fakultetni topish: kafedra nomi asosida aniq ro'yxat bo'yicha
            $faculty = null;
            if ($kafedra) {
                $faculty = $this->findFacultyByKafedraName($kafedra->name);
            }

            // Fallback: parent_id orqali
            if (!$faculty && $kafedra && $kafedra->parent_id) {
                $faculty = Department::where('department_hemis_id', $kafedra->parent_id)
                    ->where('structure_type_code', '11')
                    ->first();
                if (!$faculty) {
                    $parent = Department::find($kafedra->parent_id);
                    if ($parent && $parent->structure_type_code == '11') {
                        $faculty = $parent;
                    }
                }
            }

            if ($faculty) {
                $info['faculty_name'] = $faculty->name;

                // Dekan
                if (Schema::hasTable('dean_faculties')) {
                    $dekan = Teacher::whereHas('roles', fn ($q) => $q->where('name', 'dekan'))
                        ->whereHas('deanFaculties', fn ($q) => $q->where('dean_faculties.department_hemis_id', $faculty->department_hemis_id))
                        ->where('is_active', true)
                        ->first();
                    if ($dekan) {
                        $info['dekan'] = ['id' => $dekan->id, 'name' => $dekan->full_name];
                    }
                }
                // Fallback: role ustuni orqali
                if (!$info['dekan']) {
                    $dekan = Teacher::where('role', 'dekan')
                        ->where('department_hemis_id', $faculty->department_hemis_id)
                        ->where('is_active', true)
                        ->first();
                    if ($dekan) {
                        $info['dekan'] = ['id' => $dekan->id, 'name' => $dekan->full_name];
                    }
                }
                // Fallback: HEMIS staff_position ustuni orqali
                if (!$info['dekan']) {
                    $dekan = Teacher::where('staff_position', 'LIKE', '%ekan%')
                        ->where('department_hemis_id', $faculty->department_hemis_id)
                        ->where('is_active', true)
                        ->first();
                    if ($dekan) {
                        $info['dekan'] = ['id' => $dekan->id, 'name' => $dekan->full_name];
                    }
                }
            }

            // Registrator ofisi - HAMMA xodimlarni olish
            $registrators = Teacher::whereHas('roles', fn ($q) => $q->where('name', 'registrator_ofisi'))
                ->where('is_active', true)
                ->get();
            if ($registrators->isEmpty()) {
                $registrators = Teacher::where('role', 'registrator_ofisi')
                    ->where('is_active', true)
                    ->get();
            }
            if ($registrators->isNotEmpty()) {
                // Birinchisini asosiy tasdiqlash uchun saqlash (approval record uchun)
                $info['registrator'] = ['id' => $registrators->first()->id, 'name' => $registrators->first()->full_name];
                // Hammasini xabarnoma uchun saqlash
                $info['registrators'] = $registrators->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name])->toArray();
            }
        } catch (\Exception $e) {
            Log::warning('getApproverInfo xatolik', ['error' => $e->getMessage()]);
        }

        return $info;
    }

    /**
     * KTR o'zgartirish uchun ruxsat so'rash
     */
    public function requestChange($curriculumSubjectId)
    {
        $cs = CurriculumSubject::findOrFail($curriculumSubjectId);

        if (!$this->canEditSubjectKtr($cs)) {
            return response()->json(['success' => false, 'message' => 'Huquq yo\'q.'], 403);
        }

        // Faol so'rov bormi?
        if (Schema::hasTable('ktr_change_requests')) {
            $existing = KtrChangeRequest::where('curriculum_subject_id', $curriculumSubjectId)
                ->where('status', 'pending')
                ->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'So\'rov allaqachon jo\'natilgan.'], 422);
            }
        }

        // Jadvallarni yaratish (agar mavjud bo'lmasa)
        if (!Schema::hasTable('ktr_change_requests')) {
            Schema::create('ktr_change_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('curriculum_subject_id');
                $table->unsignedBigInteger('requested_by');
                $table->string('requested_by_guard')->default('teacher');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamps();
                $table->index('curriculum_subject_id');
                $table->index('requested_by');
            });
        }
        if (!Schema::hasTable('ktr_change_approvals')) {
            Schema::create('ktr_change_approvals', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('change_request_id')->constrained('ktr_change_requests')->onDelete('cascade');
                $table->string('role');
                $table->string('approver_name');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index('change_request_id');
            });
        }

        $user = auth()->user();
        $approverInfo = $this->getApproverInfo($cs);

        $cr = KtrChangeRequest::create([
            'curriculum_subject_id' => $curriculumSubjectId,
            'requested_by' => $user->id,
            'requested_by_guard' => $user instanceof Teacher ? 'teacher' : 'web',
        ]);

        // Har bir tasdiqlash uchun yozuv
        // Kafedra mudiri va dekan - aniq shaxsga
        foreach (['kafedra_mudiri' => $approverInfo['kafedra_mudiri'], 'dekan' => $approverInfo['dekan']] as $role => $data) {
            KtrChangeApproval::create([
                'change_request_id' => $cr->id,
                'role' => $role,
                'approver_name' => $data['name'] ?? 'Topilmadi',
                'approver_id' => $data['id'] ?? null,
            ]);
        }

        // Registrator ofisi - har qanday registrator xodimi tasdiqlashi mumkin
        KtrChangeApproval::create([
            'change_request_id' => $cr->id,
            'role' => 'registrator_ofisi',
            'approver_name' => 'Registrator ofisi xodimlari',
            'approver_id' => null,
        ]);

        $cr->load('approvals');

        // Xabarnoma yuborish (har bir tasdiqlash uchun, registrator - hammaga)
        $this->sendApproverNotifications($cr, $cs, $user, $approverInfo);

        return response()->json([
            'success' => true,
            'message' => 'O\'zgartirish uchun ruxsat so\'rovi jo\'natildi!',
            'change_request' => [
                'id' => $cr->id,
                'status' => $cr->status,
                'approvals' => $cr->approvals->map(fn ($a) => [
                    'id' => $a->id,
                    'role' => $a->role,
                    'approver_name' => $a->approver_name,
                    'status' => $a->status,
                    'responded_at' => null,
                ]),
                'is_approved' => false,
            ],
        ]);
    }

    /**
     * Tasdiqlash kerak bo'lgan xodimlarga xabarnoma yuborish
     * Registrator ofisi xodimlariga - HAMMAGA xabarnoma yuboriladi
     */
    private function sendApproverNotifications(KtrChangeRequest $cr, CurriculumSubject $cs, $requestedBy, array $approverInfo = []): void
    {
        try {
            $senderId = $requestedBy->id;
            $senderType = get_class($requestedBy);
            $requesterName = $requestedBy instanceof Teacher ? $requestedBy->full_name : ($requestedBy->name ?? 'Noma\'lum');
            $roleNames = [
                'kafedra_mudiri' => 'Kafedra mudiri',
                'dekan' => 'Dekan',
                'registrator_ofisi' => 'Registrator ofisi',
            ];

            // Qo'shimcha ma'lumotlarni olish: semestr, kurs, yo'nalish, fakultet
            $semesterName = $cs->semester_name ?? '';
            $levelName = '';
            $specialtyName = '';
            $facultyName = $approverInfo['faculty_name'] ?? '';

            $curriculum = Curriculum::where('curricula_hemis_id', $cs->curricula_hemis_id)->first();
            if ($curriculum) {
                $semester = DB::table('semesters')
                    ->where('curriculum_hemis_id', $curriculum->curricula_hemis_id)
                    ->where('code', $cs->semester_code)
                    ->first();
                if ($semester) {
                    $levelName = $semester->level_name ?? '';
                }
                $specialty = DB::table('specialties')
                    ->where('specialty_hemis_id', $curriculum->specialty_hemis_id)
                    ->first();
                if ($specialty) {
                    $specialtyName = $specialty->name ?? '';
                }
            }

            // Xabar tanasini tuzish
            $detailParts = [];
            $detailParts[] = "Fan: {$cs->subject_name}";
            if ($semesterName) {
                $detailParts[] = "Semestr: {$semesterName}";
            }
            if ($levelName) {
                $detailParts[] = "Kurs: {$levelName}";
            }
            if ($specialtyName) {
                $detailParts[] = "Yo'nalish: {$specialtyName}";
            }
            if ($facultyName) {
                $detailParts[] = "Fakultet: {$facultyName}";
            }
            $detailLine = implode(' | ', $detailParts);

            // O'zgarishlar diffini hisoblash
            $changeSummary = '';
            if ($cr->draft_plan_data) {
                $changeSummary = $this->buildChangeSummary($cs, $cr);
            }

            $hasTeacherNotifications = Schema::hasTable('teacher_notifications');

            // Bitta xodimga bitta xabarnoma (duplikatdan saqlash)
            $notifiedIds = [];

            foreach ($cr->approvals as $approval) {
                $roleName = $roleNames[$approval->role] ?? $approval->role;

                if ($approval->role === 'registrator_ofisi') {
                    // Registrator ofisi - HAMMA xodimlarga xabarnoma yuborish
                    $registrators = $approverInfo['registrators'] ?? [];
                    foreach ($registrators as $registrator) {
                        if (!$registrator['id'] || in_array($registrator['id'], $notifiedIds)) {
                            continue;
                        }

                        $bodyText = "{$requesterName} KTR o'zgartirish uchun ruxsat so'ramoqda.\n{$detailLine}\nSiz {$roleName} sifatida tasdiqlashingiz kerak.";
                        if ($changeSummary) {
                            $bodyText .= "\n\nO'zgarishlar:\n{$changeSummary}";
                        }

                        $notification = Notification::create([
                            'sender_id' => $senderId,
                            'sender_type' => $senderType,
                            'recipient_id' => $registrator['id'],
                            'recipient_type' => Teacher::class,
                            'subject' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                            'body' => $bodyText,
                            'type' => Notification::TYPE_ALERT,
                            'data' => [
                                'action' => 'ktr_change_approval',
                                'change_request_id' => $cr->id,
                                'approval_id' => $approval->id,
                                'curriculum_subject_id' => $cs->id,
                                'subject_name' => $cs->subject_name,
                                'role' => 'registrator_ofisi',
                            ],
                            'is_draft' => false,
                            'sent_at' => now(),
                        ]);

                        // Teacher notification panelida ham ko'rsatish (qaysi rolda tursa ham ko'radi)
                        if ($hasTeacherNotifications) {
                            TeacherNotification::create([
                                'teacher_id' => $registrator['id'],
                                'type' => 'ktr_approval',
                                'title' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                                'message' => "{$requesterName} KTR o'zgartirish ruxsatini so'ramoqda. {$detailLine}" . ($changeSummary ? "\n{$changeSummary}" : ''),
                                'link' => route('admin.notifications.show', $notification->id),
                                'data' => [
                                    'action' => 'ktr_change_approval',
                                    'approval_id' => $approval->id,
                                    'notification_id' => $notification->id,
                                ],
                            ]);
                        }

                        $notifiedIds[] = $registrator['id'];
                    }
                } else {
                    // Kafedra mudiri va dekan - faqat tegishli shaxsga
                    if (!$approval->approver_id || in_array($approval->approver_id, $notifiedIds)) {
                        continue;
                    }

                    $bodyText2 = "{$requesterName} KTR o'zgartirish uchun ruxsat so'ramoqda.\n{$detailLine}\nSiz {$roleName} sifatida tasdiqlashingiz kerak.";
                    if ($changeSummary) {
                        $bodyText2 .= "\n\nO'zgarishlar:\n{$changeSummary}";
                    }

                    $notification = Notification::create([
                        'sender_id' => $senderId,
                        'sender_type' => $senderType,
                        'recipient_id' => $approval->approver_id,
                        'recipient_type' => Teacher::class,
                        'subject' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                        'body' => $bodyText2,
                        'type' => Notification::TYPE_ALERT,
                        'data' => [
                            'action' => 'ktr_change_approval',
                            'change_request_id' => $cr->id,
                            'approval_id' => $approval->id,
                            'curriculum_subject_id' => $cs->id,
                            'subject_name' => $cs->subject_name,
                            'role' => $approval->role,
                        ],
                        'is_draft' => false,
                        'sent_at' => now(),
                    ]);

                    // Teacher notification panelida ham ko'rsatish (qaysi rolda tursa ham ko'radi)
                    if ($hasTeacherNotifications) {
                        TeacherNotification::create([
                            'teacher_id' => $approval->approver_id,
                            'type' => 'ktr_approval',
                            'title' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                            'message' => "{$requesterName} KTR o'zgartirish ruxsatini so'ramoqda. {$detailLine}" . ($changeSummary ? "\n{$changeSummary}" : ''),
                            'link' => route('admin.notifications.show', $notification->id),
                            'data' => [
                                'action' => 'ktr_change_approval',
                                'approval_id' => $approval->id,
                                'notification_id' => $notification->id,
                            ],
                        ]);
                    }

                    $notifiedIds[] = $approval->approver_id;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Xabarnoma yuborishda xatolik', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Eski va yangi KTR o'rtasidagi farqlarni hisoblash
     */
    private function buildChangeSummary(CurriculumSubject $cs, KtrChangeRequest $cr): string
    {
        $existingPlan = KtrPlan::where('curriculum_subject_id', $cs->id)->first();
        if (!$existingPlan) {
            return '';
        }

        $oldData = $existingPlan->plan_data;
        $newData = $cr->draft_plan_data;
        if (!$oldData || !$newData) {
            return '';
        }

        $oldHours = $oldData['hours'] ?? $oldData;
        $newHours = $newData['hours'] ?? $newData;

        // Training type nomlarini olish
        $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
        $typeNames = [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                $code = (string) ($detail['trainingType']['code'] ?? '');
                $name = $detail['trainingType']['name'] ?? $code;
                if ($code) {
                    $typeNames[$code] = $name;
                }
            }
        }

        $changes = [];

        // Hafta soni o'zgargan bo'lsa
        if ($existingPlan->week_count != $cr->draft_week_count) {
            $changes[] = "Hafta soni: {$existingPlan->week_count} → {$cr->draft_week_count}";
        }

        // Har bir hafta va tur bo'yicha soatlar farqini tekshirish
        $allWeeks = array_unique(array_merge(array_keys($oldHours), array_keys($newHours)));
        sort($allWeeks, SORT_NUMERIC);

        foreach ($allWeeks as $week) {
            $oldWeek = $oldHours[$week] ?? [];
            $newWeek = $newHours[$week] ?? [];
            $allCodes = array_unique(array_merge(array_keys($oldWeek), array_keys($newWeek)));

            foreach ($allCodes as $code) {
                $oldVal = (int) ($oldWeek[$code] ?? 0);
                $newVal = (int) ($newWeek[$code] ?? 0);
                if ($oldVal !== $newVal) {
                    $typeName = $typeNames[$code] ?? $code;
                    $changes[] = "{$week}-hafta {$typeName}: {$oldVal} → {$newVal} soat";
                }
            }
        }

        if (empty($changes)) {
            return 'O\'zgarish yo\'q';
        }

        // Maksimum 10 ta o'zgarishni ko'rsatish
        if (count($changes) > 10) {
            $shown = array_slice($changes, 0, 10);
            $remaining = count($changes) - 10;
            $shown[] = "... va yana {$remaining} ta o'zgarish";
            return implode("\n", $shown);
        }

        return implode("\n", $changes);
    }

    /**
     * KTR rejasini Word formatida eksport qilish
     */
    public function exportWord($curriculumSubjectId)
    {
        $cs = CurriculumSubject::findOrFail($curriculumSubjectId);

        $plan = KtrPlan::where('curriculum_subject_id', $curriculumSubjectId)->first();
        if (!$plan) {
            return back()->with('error', 'KTR rejasi topilmadi.');
        }

        // Mashg'ulot turlarini olish
        $trainingTypes = [];
        $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
        if (is_array($details)) {
            foreach ($details as $detail) {
                $code = (string) ($detail['trainingType']['code'] ?? '');
                $name = $detail['trainingType']['name'] ?? '';
                $hours = (int) ($detail['academic_load'] ?? 0);
                if ($code !== '' && $name !== '') {
                    $trainingTypes[$code] = ['name' => $name, 'hours' => $hours];
                }
            }
        }

        // Tartibda saralash
        $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];
        $normalize = function ($str) {
            return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        };
        if (count($trainingTypes) > 1) {
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
        }

        // Mustaqil va mustaqil bo'lmaganlarni ajratish
        $filteredTypes = [];
        $mustaqilTypes = [];
        foreach ($trainingTypes as $code => $type) {
            if (preg_match('/mustaqil/i', $normalize($type['name']))) {
                $mustaqilTypes[$code] = $type;
            } else {
                $filteredTypes[$code] = $type;
            }
        }
        $typeCodes = array_keys($filteredTypes);

        // Kafedra va fakultet ma'lumotlari
        $approverInfo = $this->getApproverInfo($cs);

        // Curriculum va semestr ma'lumotlari
        $curriculum = Curriculum::where('curricula_hemis_id', $cs->curricula_hemis_id)->first();
        $semesterName = $cs->semester_name ?? '';
        $levelName = '';
        $educationYear = '';
        $specialtyName = '';

        if ($curriculum) {
            $educationYear = $curriculum->education_year_name ?? '';
            $semester = DB::table('semesters')
                ->where('curriculum_hemis_id', $curriculum->curricula_hemis_id)
                ->where('code', $cs->semester_code)
                ->first();
            if ($semester) {
                $levelName = $semester->level_name ?? '';
            }
            $specialty = DB::table('specialties')
                ->where('specialty_hemis_id', $curriculum->specialty_hemis_id)
                ->first();
            if ($specialty) {
                $specialtyName = $specialty->name ?? '';
            }
        }

        $planData = $plan->plan_data;
        $hoursData = $planData['hours'] ?? $planData;
        $topicsData = $planData['topics'] ?? [];

        // ---- PhpWord hujjat yaratish ----
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginTop' => 800,
            'marginBottom' => 600,
            'marginLeft' => 800,
            'marginRight' => 600,
        ]);

        $center = ['alignment' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0];
        $left = ['spaceAfter' => 0, 'spaceBefore' => 0];

        // ---- SARLAVHA ----
        $section->addText('KALENDAR-TEMATIK REJA', ['bold' => true, 'size' => 14], $center);
        $section->addText($educationYear . " o'quv yili", ['size' => 12], $center);
        $section->addTextBreak(1);

        // Fan ma'lumotlari
        $section->addText('Fan: ' . $cs->subject_name, ['bold' => true, 'size' => 12], $left);
        $section->addText('Fakultet: ' . ($approverInfo['faculty_name'] ?: ''), ['size' => 12], $left);
        $section->addText("Yo'nalish: " . $specialtyName . '  ' . $levelName . '-kurs  ' . $semesterName, ['size' => 12], $left);
        $section->addTextBreak(0);

        // Semestr uchun ajratilgan soatlar
        $section->addText($semesterName . ' uchun ajratilgan soat:', ['bold' => true, 'size' => 12], $left);
        foreach ($filteredTypes as $code => $type) {
            $section->addText($type['name'] . ' - ' . $type['hours'] . ' soat.', ['size' => 12], $left);
        }
        foreach ($mustaqilTypes as $code => $type) {
            $section->addText($type['name'] . ' - ' . $type['hours'] . ' soat.', ['size' => 12], $left);
        }
        $section->addTextBreak(1);

        // ---- JADVAL SARLAVHASI ----
        $typeNames = [];
        foreach ($filteredTypes as $code => $type) {
            $typeNames[] = mb_strtoupper($type['name']);
        }
        $section->addText(
            implode(', ', $typeNames) . ' MASHG\'ULOTLAR MAVZULARI',
            ['bold' => true, 'size' => 12],
            $center
        );
        $section->addTextBreak(0);

        // ---- JADVAL ----
        $typeCount = count($typeCodes);
        $cellBorder = ['borderSize' => 6, 'borderColor' => '000000', 'valign' => 'center'];
        $hBold = ['bold' => true, 'size' => 10];
        $hNormal = ['size' => 10];
        $dStyle = ['size' => 10];
        $cp = ['alignment' => 'center', 'spaceAfter' => 20, 'spaceBefore' => 20];
        $lp = ['spaceAfter' => 20, 'spaceBefore' => 20];

        // Ustun kengliklari
        $haftaW = 700;
        $kunlariW = 1600;
        $soatEachW = 700;
        $soatTotalW = $soatEachW * $typeCount;
        $mavzuW = 14000 - $haftaW - $kunlariW - $soatTotalW;
        if ($mavzuW < 3000) $mavzuW = 3000;

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 40,
            'cellMarginRight' => 40,
        ]);

        // 1-qator sarlavha: Hafta | Hafta kunlari | Mashg'ulotlar mavzulari | Soat (gridSpan)
        $table->addRow(null, ['tblHeader' => true]);
        $table->addCell($haftaW, array_merge($cellBorder, ['vMerge' => 'restart']))
            ->addText('Hafta', $hBold, $cp);
        $table->addCell($kunlariW, array_merge($cellBorder, ['vMerge' => 'restart']))
            ->addText('Hafta kunlari', $hBold, $cp);
        $table->addCell($mavzuW, array_merge($cellBorder, ['vMerge' => 'restart']))
            ->addText('Mashg\'ulotlar mavzulari ' . $semesterName, $hBold, $cp);
        if ($typeCount > 0) {
            $table->addCell($soatTotalW, array_merge($cellBorder, ['gridSpan' => $typeCount]))
                ->addText('Soat', $hBold, $cp);
        }

        // 2-qator sarlavha: (merge) | (merge) | (merge) | Ma'ruza | Amaliy | ...
        // Vertikal yozuv: har bir harfni alohida qatorga qo'yish
        $table->addRow(null, ['tblHeader' => true]);
        $table->addCell($haftaW, array_merge($cellBorder, ['vMerge' => 'continue']));
        $table->addCell($kunlariW, array_merge($cellBorder, ['vMerge' => 'continue']));
        $table->addCell($mavzuW, array_merge($cellBorder, ['vMerge' => 'continue']));
        foreach ($typeCodes as $code) {
            $name = $filteredTypes[$code]['name'];
            $cell = $table->addCell($soatEachW, $cellBorder);
            // Har bir harfni alohida qator qilib vertikal yozish
            $chars = mb_str_split($name);
            foreach ($chars as $char) {
                $cell->addText(
                    $char,
                    ['bold' => true, 'size' => 9],
                    ['alignment' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 0.8]
                );
            }
        }

        // ---- MA'LUMOTLAR QATORLARI ----
        for ($w = 1; $w <= $plan->week_count; $w++) {
            $table->addRow();

            // Hafta raqami
            $table->addCell($haftaW, $cellBorder)->addText($w, $dStyle, $cp);

            // Hafta kunlari (bo'sh - foydalanuvchi to'ldiradi)
            $table->addCell($kunlariW, $cellBorder)->addText('', $dStyle, $cp);

            // Mashg'ulotlar mavzulari - barcha turlar bitta katakda
            $topicCell = $table->addCell($mavzuW, $cellBorder);
            $hasContent = false;
            foreach ($typeCodes as $code) {
                $hrs = (int) ($hoursData[$w][$code] ?? 0);
                $topic = $topicsData[$w][$code] ?? '';
                if ($hrs > 0 && $topic !== '') {
                    $textRun = $topicCell->addTextRun($lp);
                    $textRun->addText($filteredTypes[$code]['name'] . ': ', ['bold' => true, 'size' => 10]);
                    $textRun->addText($topic, ['size' => 10, 'underline' => 'single']);
                    $hasContent = true;
                } elseif ($hrs > 0) {
                    $textRun = $topicCell->addTextRun($lp);
                    $textRun->addText($filteredTypes[$code]['name'] . ': ', ['bold' => true, 'size' => 10]);
                    $textRun->addText('-', ['size' => 10]);
                    $hasContent = true;
                }
            }
            if (!$hasContent) {
                $topicCell->addText('', $dStyle, $lp);
            }

            // Soat ustunlari
            foreach ($typeCodes as $code) {
                $hrs = (int) ($hoursData[$w][$code] ?? 0);
                $table->addCell($soatEachW, $cellBorder)
                    ->addText($hrs > 0 ? $hrs : '', $dStyle, $cp);
            }
        }

        // Jami qator
        $table->addRow();
        $jamiCell = array_merge($cellBorder, ['bgColor' => 'D9D9D9']);
        $table->addCell($haftaW + $kunlariW + $mavzuW, array_merge($jamiCell, ['gridSpan' => 3]))
            ->addText('JAMI:', ['bold' => true, 'size' => 10], $cp);
        foreach ($typeCodes as $code) {
            $colTotal = 0;
            for ($w = 1; $w <= $plan->week_count; $w++) {
                $colTotal += (int) ($hoursData[$w][$code] ?? 0);
            }
            $table->addCell($soatEachW, $jamiCell)
                ->addText($colTotal, ['bold' => true, 'size' => 10], $cp);
        }

        // ---- IMZOLAR ----
        $section->addTextBreak(2);

        $user = auth()->user();
        $teacherName = ($user instanceof Teacher) ? ($user->full_name ?? $user->name) : '';

        $section->addText("Tuzuvchi o'qituvchi:  ______________  " . $teacherName, ['size' => 12], $left);
        $section->addTextBreak(1);
        $section->addText("Kafedra mudiri:  ______________  " . ($approverInfo['kafedra_mudiri']['name'] ?? ''), ['size' => 12], $left);

        // ---- FAYLNI YUKLASH ----
        $filename = 'KTR_' . preg_replace('/[^a-zA-Z0-9\x{0400}-\x{04FF}]/u', '_', $cs->subject_name) . '_' . date('Y-m-d') . '.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'ktr_') . '.docx';
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    public function approveChange(Request $request, $approvalId)
    {
        $approval = KtrChangeApproval::findOrFail($approvalId);
        $user = auth()->user();

        // Faqat tegishli rol egasi tasdiqlashi mumkin
        $canApprove = false;

        if ($approval->role === 'registrator_ofisi') {
            // Registrator ofisi - registrator_ofisi roli bor xodim tasdiqlashi mumkin
            if ($user->hasRole('registrator_ofisi')) {
                $canApprove = true;
            }
        } else {
            // Kafedra mudiri va dekan - faqat aniq tayinlangan shaxs
            if ($approval->approver_id && $user instanceof Teacher && $user->id == $approval->approver_id) {
                $canApprove = true;
            }
        }

        // Admin har doim tasdiqlashi mumkin
        if ($user->hasRole(['superadmin', 'admin'])) {
            $canApprove = true;
        }

        if (!$canApprove) {
            return response()->json(['success' => false, 'message' => 'Sizda tasdiqlash huquqi yo\'q.'], 403);
        }

        $status = $request->input('status', 'approved');
        if (!in_array($status, ['approved', 'rejected'])) {
            $status = 'approved';
        }

        $updateData = [
            'status' => $status,
            'responded_at' => now(),
        ];

        // Registrator ofisi tasdiqlaganda kim tasdiqlaganini saqlash
        if ($approval->role === 'registrator_ofisi' && !$approval->approver_id) {
            $updateData['approver_id'] = $user->id;
            $updateData['approver_name'] = $user->full_name ?? $user->name ?? 'Noma\'lum';
        }

        $approval->update($updateData);

        // Agar rad etilsa, butun so'rovni ham rad etish
        $cr = $approval->changeRequest;
        if ($status === 'rejected') {
            $cr->update(['status' => 'rejected']);
        }

        // Barcha approval holatini qaytarish (jadval yangilanishi uchun)
        $cr->load('approvals');

        $isFullyApproved = $cr->isFullyApproved();

        // Agar barcha tasdiqlar olingan bo'lsa va draft mavjud bo'lsa - KTR ni yangilash
        if ($isFullyApproved && $cr->draft_plan_data) {
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
                ['curriculum_subject_id' => $cr->curriculum_subject_id],
                [
                    'week_count' => $cr->draft_week_count,
                    'plan_data' => $cr->draft_plan_data,
                    'created_by' => $cr->requested_by,
                ]
            );

            $cr->update(['status' => 'approved']);
        }

        return response()->json([
            'success' => true,
            'message' => $status === 'approved' ? 'Tasdiqlandi!' : 'Rad etildi.',
            'approvals' => $cr->approvals->map(fn ($a) => [
                'id' => $a->id,
                'role' => $a->role,
                'approver_name' => $a->approver_name,
                'status' => $a->status,
                'responded_at' => $a->responded_at?->format('d.m.Y H:i'),
            ]),
            'is_approved' => $isFullyApproved,
            'ktr_applied' => $isFullyApproved && $cr->draft_plan_data ? true : false,
        ]);
    }
}
