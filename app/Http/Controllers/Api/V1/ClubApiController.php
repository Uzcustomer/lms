<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClubMembership;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubApiController extends Controller
{
    private static array $sections = [
        [
            'title' => "O'zbek va xorijiy tillar kafedrasi",
            'clubs' => [
                ['name' => '"Yosh tilshunoslar"', 'place' => "1-o'quv bino, 412-xona", 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                ['name' => '"Русское слово"', 'place' => "1-o'quv bino, 332-xona", 'day' => 'Payshanba', 'time' => '15:00-16:00'],
                ['name' => '"We learn English"', 'place' => "1-o'quv bino, 411-xona", 'day' => 'Juma', 'time' => '15:00-16:00'],
                ['name' => '"English atmosphere"', 'place' => "1-o'quv bino, 406-xona", 'day' => 'Chorshanba', 'time' => '15:00-16:00'],
                ['name' => '"Medicus"', 'place' => "1-o'quv bino, 334-xona", 'day' => 'Juma', 'time' => '15:00-16:00'],
            ],
        ],
        [
            'title' => "Travmatologiya-ortopediya, harbiy dala jarrohligi, neyrojarrohlik, anesteziologiya va tex tibbiy yordam kafedrasi",
            'clubs' => [
                ['name' => 'Yosh Travmatolog-ortoped', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi", 'day' => 'Shanba', 'time' => '14:30-16:30'],
            ],
        ],
        [
            'title' => "Otorinolaringologiya, oftalmologiya, onkologiya va tibbiy radiologiya kafedrasi",
            'clubs' => [
                ['name' => 'Yosh onkologlar', 'place' => 'Viloyat Onkologiya shifoxonasi', 'day' => 'Chorshanba, Shanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Normal va patologik fiziologiya kafedrasi",
            'clubs' => [
                ['name' => 'Tibbiyot falsafasi', 'place' => "Asosiy o'quv bino, 5-qavat", 'day' => 'Chorshanba, Juma', 'time' => '16:30-17:30'],
                ['name' => 'Yosh fiziologlar', 'place' => "Asosiy o'quv bino, 5-qavat", 'day' => 'Chorshanba, Shanba', 'time' => '16:30-17:30'],
            ],
        ],
        [
            'title' => "Mikrobiologiya, jamoat salomatligi, gigiyena va menejment kafedrasi",
            'clubs' => [
                ['name' => '"Yosh mikrobiologlar"', 'place' => "Asosiy o'quv bino, 4-qavat 408-xona", 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh gigiyenistlar', 'place' => "1-o'quv bino, 308-xona", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Ichki kasalliklar, HDT, gematologiya va oilaviy shifokorlikda terapiya kafedrasi",
            'clubs' => [
                ['name' => 'Oilaviy shifokorlikda terapiya', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh allergologlar', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi", 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh revmatologlar', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi", 'day' => 'Juma', 'time' => '15:00-17:00'],
                ['name' => 'Harbiy terapevtlar', 'place' => '4-oilaviy poliklinika', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh terapevtlar', 'place' => '4-oilaviy poliklinika', 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                ['name' => 'Kardiologlar avlodi', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh kardiologlar', 'place' => 'RIKIATM Surxondaryo mintaqaviy filiali', 'day' => 'Juma', 'time' => '15:00-17:00'],
                ['name' => 'Tibbiyotda zamonaviy diagnostika', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                ['name' => '"SMART DOCTOR" ichki kasalliklar klubi', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi, 8-o'quv xona", 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                ['name' => 'Kardiologiya', 'place' => 'RShTTYoIM Surxondaryo filiali', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Farmakologiya va klinik farmakologiya kafedrasi",
            'clubs' => [
                ['name' => 'Mediator', 'place' => "1-o'quv binosi, 333-xona", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh tabobatchi', 'place' => "1-o'quv binosi, 326-xona", 'day' => 'Payshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Bolalar kasalliklari propedevtikasi, bolalar kasalliklari va oilaviy shifokorlikda pediatriya kafedrasi",
            'clubs' => [
                ['name' => 'Yosh pediatr', 'place' => "Viloyat bolalar ko'p tarmoqli tibbiyot markazi", 'day' => 'Shanba', 'time' => '15:00-17:00'],
                ['name' => 'Pediatriya bilimdonlari', 'place' => "Viloyat bolalar ko'p tarmoqli tibbiyot markazi", 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '15:00-17:00'],
                ['name' => 'Ustoz va shogird', 'place' => "Viloyat bolalar ko'p tarmoqli tibbiyot markazi", 'day' => 'Seshanba, Payshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Anatomiya va klinik anatomiya kafedrasi",
            'clubs' => [
                ['name' => 'Skalpel', 'place' => "1-o'quv bino, 224-xona", 'day' => 'Shanba', 'time' => '15:00-17:00'],
                ['name' => 'Moxir anatomlar', 'place' => "1-o'quv bino, 217-xona", 'day' => 'Chorshanba, Juma', 'time' => '15:00-17:00'],
                ['name' => 'MAXAON', 'place' => "1-o'quv bino, 222-xona", 'day' => 'Seshanba, Payshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Tibbiy biologiya va gistologiya kafedrasi",
            'clubs' => [
                ['name' => '"Yosh biologlar"', 'place' => "1-o'quv bino, 115-xona", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                ['name' => '"Yosh gistologlar"', 'place' => "1-o'quv bino, 106-xona", 'day' => 'Payshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Akusherlik va ginekologiya va oilaviy shifokorlikda ginekologiya kafedrasi",
            'clubs' => [
                ['name' => '"Yosh akusher-ginekologlar"', 'place' => "RIO va BSIATMSF Reproduktiv salomatlik bo'limi", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Umumiy xirurgiya, bolalar xirurgiyasi, urologiya va bolalar urologiyasi kafedrasi",
            'clubs' => [
                ['name' => 'Surgeon', 'place' => "Viloyat bolalar ko'p tarmoqli tibbiyot markazi", 'day' => 'Dushanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh jarrohlar', 'place' => "Viloyat bolalar ko'p tarmoqli tibbiyot markazi", 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Nefroclub', 'place' => "Viloyat ko'p tarmoqli tibbiyot markazi", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Ijtimoiy-gumanitar fanlar kafedrasi",
            'clubs' => [
                ['name' => 'Yosh tarixchi', 'place' => "1-o'quv bino, 416-xona", 'day' => 'Shanba', 'time' => '15:00-17:00'],
                ['name' => 'Kompyuter bilimdoni', 'place' => "o'quv bino, 430-xona", 'day' => 'Shanba', 'time' => '15:00-17:00'],
                ['name' => 'Yosh biofiziklar', 'place' => "1-o'quv bino, 424-xona", 'day' => 'Shanba', 'time' => '15:00-17:00'],
                ['name' => "Qalb shifokorlari", 'place' => "1-o'quv bino, 416-xona", 'day' => 'Shanba', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Tibbiy va biologik kimyo kafedrasi",
            'clubs' => [
                ['name' => 'Yosh kimyogarlar', 'place' => "Asosiy o'quv bino, 301-xona", 'day' => 'Dushanba', 'time' => '15:00-16:00'],
                ['name' => 'Yosh biokimyogarlar', 'place' => "Asosiy o'quv bino, 313-xona", 'day' => 'Payshanba', 'time' => '15:00-16:00'],
            ],
        ],
        [
            'title' => "Patologik anatomiya, sud tibbiyoti huquqi kafedrasi",
            'clubs' => [
                ['name' => 'Buyuk patologoanatomlar', 'place' => "Asosiy o'quv bino, 2-qavat", 'day' => 'Seshanba, Payshanba', 'time' => '16:30-17:30'],
                ['name' => 'Adolatli sud-tibbiy ekspertlar', 'place' => 'RSTYIAM Surxondaryo viloyati filiali binosi', 'day' => 'Chorshanba, Juma', 'time' => '16:30-17:30'],
            ],
        ],
        [
            'title' => "Tibbiy psixologiya, nevrologiya va psixiatriya kafedrasi",
            'clubs' => [
                ['name' => 'Yosh nevrologlar', 'place' => "Ko'p tarmoqli markaziy poliklinika, 403-xona", 'day' => 'Seshanba, Payshanba, Shanba', 'time' => '16:00-18:00'],
                ['name' => 'Yosh Psixiatrlar', 'place' => 'Viloyat Ruhiy asab kasalliklar shifoxonasi, 3-xona', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '16:00-18:00'],
            ],
        ],
        [
            'title' => "Yuqumli kasalliklar, dermatovenerologiya, ftiziatriya va pulmonologiya kafedrasi",
            'clubs' => [
                ['name' => 'Dermatovenerologlar', 'place' => 'Surxondaryo viloyati Teri tanosil kasalliklari dispanseri', 'day' => 'Shanba', 'time' => '16:00-18:00'],
                ['name' => 'Infeksionistlar', 'place' => 'Viloyat yuqumli kasalliklar shifoxonasi', 'day' => 'Dushanba, Chorshanba, Juma', 'time' => '16:00-18:00'],
                ['name' => 'Ftiziatrlar', 'place' => 'Viloyat Ftiziatriya va Pulmonologiya shifoxonasi', 'day' => 'Seshanba, Payshanba', 'time' => '16:00-18:00'],
            ],
        ],
        [
            'title' => "Ichki kasalliklar propedevtikasi, reabilitologiya, xalq tabobati va endokrinologiya kafedrasi",
            'clubs' => [
                ['name' => 'Yosh endokrinologlar', 'place' => 'Mashxura klinikasi', 'day' => 'Dushanba, Juma', 'time' => '15:00-17:00'],
            ],
        ],
        [
            'title' => "Xirurgik kasalliklar va oilaviy shifokorlikda xirurgiya kafedrasi",
            'clubs' => [
                ['name' => 'Torakal va yurak-qon tomir xirurgiyasi', 'place' => "O'tan polvon DDM, Kafedra xonasi", 'day' => 'Seshanba', 'time' => '15:00-17:00'],
                ['name' => 'Kardioxirurgiyada zamonaviy tekshirish usullari', 'place' => "O'tan polvon DDM, Kafedra xonasi", 'day' => 'Chorshanba', 'time' => '15:00-17:00'],
                ['name' => "Kardioxirurgiyada anesteziologiya, reanimatsiya va perfuziologiya masalalari", 'place' => "O'tan polvon DDM, Kafedra xonasi", 'day' => 'Payshanba', 'time' => '15:00-17:00'],
                ['name' => 'Tibbiyotda nemis tili', 'place' => "O'tan polvon DDM, Kafedra xonasi", 'day' => 'Seshanba, Payshanba, Shanba', 'time' => '15:00-17:00'],
            ],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $myClubNames = ClubMembership::where('student_id', $student->id)
            ->pluck('club_name')
            ->toArray();

        $sections = collect(self::$sections)->map(function ($section) use ($myClubNames) {
            $clubs = collect($section['clubs'])->map(function ($club) use ($myClubNames) {
                $club['applied'] = in_array($club['name'], $myClubNames);
                return $club;
            })->toArray();

            return [
                'title' => $section['title'],
                'clubs' => $clubs,
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $sections,
        ]);
    }

    public function myClubs(Request $request): JsonResponse
    {
        $student = $request->user();
        $memberships = ClubMembership::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'club_name' => $m->club_name,
                'club_place' => $m->club_place,
                'club_day' => $m->club_day,
                'club_time' => $m->club_time,
                'kafedra_name' => $m->kafedra_name,
                'status' => $m->status,
                'reject_reason' => $m->reject_reason,
                'created_at' => $m->created_at->format('d.m.Y H:i'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $memberships,
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'club_name' => 'required|string|max:255',
            'club_place' => 'nullable|string|max:255',
            'club_day' => 'nullable|string|max:255',
            'club_time' => 'nullable|string|max:255',
            'kafedra_name' => 'nullable|string|max:500',
        ]);

        $student = $request->user();

        $existing = ClubMembership::where('student_id', $student->id)
            ->where('club_name', $request->club_name)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Siz bu to'garakka allaqachon ariza yuborgansiz.",
            ], 422);
        }

        $departmentHemisId = null;
        if ($request->kafedra_name) {
            $kafedraTitle = mb_strtolower($request->kafedra_name);
            $department = Department::where('active', true)
                ->where('name', 'LIKE', '%kafedra%')
                ->get()
                ->first(function ($dept) use ($kafedraTitle) {
                    $deptName = mb_strtolower($dept->name);
                    $deptCore = trim(preg_replace('/\s*kafedras?i?\s*/ui', ' ', $deptName));
                    $words = array_filter(explode(' ', $deptCore), fn($w) => mb_strlen($w) > 3);
                    if (empty($words)) return false;
                    $matched = 0;
                    foreach ($words as $w) {
                        if (mb_stripos($kafedraTitle, $w) !== false) $matched++;
                    }
                    return $matched >= count($words) * 0.5;
                });
            $departmentHemisId = $department?->department_hemis_id;
        }

        $data = [
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_name' => $student->full_name,
            'group_name' => $student->group_name,
            'club_name' => $request->club_name,
            'club_place' => $request->club_place,
            'club_day' => $request->club_day,
            'club_time' => $request->club_time,
            'kafedra_name' => $request->kafedra_name,
            'status' => 'pending',
        ];

        if (\Schema::hasColumn('club_memberships', 'department_hemis_id')) {
            $data['department_hemis_id'] = $departmentHemisId;
        }

        ClubMembership::create($data);

        return response()->json([
            'success' => true,
            'message' => "To'garakka a'zo bo'lish uchun ariza yuborildi!",
        ]);
    }
}
