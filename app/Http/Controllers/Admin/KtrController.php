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

        // Mavjud reja bo'lsa, o'zgartirish uchun tasdiqlangan so'rov kerak (adminlar bundan mustasno)
        $user = auth()->user();
        $isAdmin = $user->hasRole(['superadmin', 'admin', 'kichik_admin']);
        $existingPlan = Schema::hasTable('ktr_plans')
            ? KtrPlan::where('curriculum_subject_id', $curriculumSubjectId)->exists()
            : false;

        if ($existingPlan && !$isAdmin && Schema::hasTable('ktr_change_requests')) {
            $approvedRequest = KtrChangeRequest::where('curriculum_subject_id', $curriculumSubjectId)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$approvedRequest || !$approvedRequest->isFullyApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'KTR o\'zgartirish uchun barcha tasdiqlar olinmagan.',
                ], 403);
            }

            // Tasdiqlangan so'rovni yakunlash
            $approvedRequest->update(['status' => 'approved']);
        }

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

            // Xabarnoma yuborilgan ID larni kuzatish (duplikatdan saqlash)
            $notifiedIds = [];

            foreach ($cr->approvals as $approval) {
                if ($approval->role === 'registrator_ofisi') {
                    // Registrator ofisi - HAMMA xodimlarga xabarnoma yuborish
                    $registrators = $approverInfo['registrators'] ?? [];
                    foreach ($registrators as $registrator) {
                        if (!$registrator['id'] || in_array($registrator['id'], $notifiedIds)) {
                            continue;
                        }
                        Notification::create([
                            'sender_id' => $senderId,
                            'sender_type' => $senderType,
                            'recipient_id' => $registrator['id'],
                            'recipient_type' => Teacher::class,
                            'subject' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                            'body' => "{$requesterName} \"{$cs->subject_name}\" fani uchun KTR o'zgartirish uchun ruxsat so'ramoqda. Siz Registrator ofisi sifatida tasdiqlashingiz kerak.",
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
                        $notifiedIds[] = $registrator['id'];
                    }
                } else {
                    // Kafedra mudiri va dekan - faqat tegishli shaxsga
                    if (!$approval->approver_id || in_array($approval->approver_id, $notifiedIds)) {
                        continue;
                    }

                    $roleName = $roleNames[$approval->role] ?? $approval->role;

                    Notification::create([
                        'sender_id' => $senderId,
                        'sender_type' => $senderType,
                        'recipient_id' => $approval->approver_id,
                        'recipient_type' => Teacher::class,
                        'subject' => 'KTR o\'zgartirish uchun ruxsat so\'raldi',
                        'body' => "{$requesterName} \"{$cs->subject_name}\" fani uchun KTR o'zgartirish uchun ruxsat so'ramoqda. Siz {$roleName} sifatida tasdiqlashingiz kerak.",
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
                    $notifiedIds[] = $approval->approver_id;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Xabarnoma yuborishda xatolik', ['error' => $e->getMessage()]);
        }
    }

    /**
     * KTR o'zgartirish so'rovini tasdiqlash/rad etish
     */
    public function approveChange(Request $request, $approvalId)
    {
        $approval = KtrChangeApproval::findOrFail($approvalId);
        $user = auth()->user();

        // Faqat tegishli rol egasi tasdiqlashi mumkin
        $canApprove = false;
        if ($approval->approver_id && $user instanceof Teacher && $user->id == $approval->approver_id) {
            $canApprove = true;
        }
        if ($user->hasRole(['superadmin', 'admin'])) {
            $canApprove = true;
        }
        if ($user->hasRole($approval->role)) {
            $canApprove = true;
        }

        if (!$canApprove) {
            return response()->json(['success' => false, 'message' => 'Sizda tasdiqlash huquqi yo\'q.'], 403);
        }

        $status = $request->input('status', 'approved');
        if (!in_array($status, ['approved', 'rejected'])) {
            $status = 'approved';
        }

        $approval->update([
            'status' => $status,
            'responded_at' => now(),
        ]);

        // Agar rad etilsa, butun so'rovni ham rad etish
        $cr = $approval->changeRequest;
        if ($status === 'rejected') {
            $cr->update(['status' => 'rejected']);
        }

        return response()->json([
            'success' => true,
            'message' => $status === 'approved' ? 'Tasdiqlandi!' : 'Rad etildi.',
        ]);
    }
}
