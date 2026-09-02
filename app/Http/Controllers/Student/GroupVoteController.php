<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DistributionDraftAssignment;
use App\Models\DistributionVote;
use App\Models\DistributionVotingGroup;
use App\Models\DistributionVotingStudent;
use App\Services\DistributionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talabaning guruh tanlash ovozi.
 *
 * Popup faqat ovoz berish ochilgan guruh talabasiga ko'rsatiladi
 * (student-app layoutdagi partial hal qiladi). Bu kontroller ovozni qabul
 * qiladi: har talaba faqat bir marta, faqat o'ziga mos va bo'sh joyli
 * guruhga. LMS dagi guruhga tegilmaydi — qaror registrator tasdig'idan
 * keyin reja (draft) bo'lib saqlanadi.
 */
class GroupVoteController extends Controller
{
    public function __construct(private DistributionCatalog $catalog)
    {
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_votes') && Schema::hasTable('distribution_voting_groups'),
            503,
            'Ovoz berish hali sozlanmagan.'
        );

        $student = Auth::guard('student')->user();
        abort_unless($student && $student->group_id, 403);

        $data = $request->validate([
            'to_group_hemis_id' => ['required', 'integer'],
        ]);

        $votingOpen = DistributionVotingGroup::query()
                ->where('group_hemis_id', (int) $student->group_id)
                ->exists()
            || (Schema::hasTable('distribution_voting_students')
                && DistributionVotingStudent::query()->where('student_id', $student->id)->exists());

        if (!$votingOpen) {
            return response()->json(['message' => 'Guruhingiz uchun ovoz berish ochiq emas.'], 422);
        }

        if (DistributionVote::query()->where('student_id', $student->id)->exists()) {
            return response()->json(['message' => 'Siz allaqachon ovoz bergansiz. Ovoz faqat bir marta beriladi.'], 422);
        }

        $target = $this->catalog
            ->targetsFor((int) $student->group_id)
            ->firstWhere('group_hemis_id', (int) $data['to_group_hemis_id']);

        if (!$target) {
            return response()->json([
                'message' => 'Tanlangan guruh mavjud emas yoki bo\'sh joyi qolmagan. Sahifani yangilab qayta urinib ko\'ring.',
            ], 422);
        }

        // Ovoz darrov kuchga kiradi: reja yoziladi va joy band bo'ladi.
        // Registrator tasdig'i talab qilinmaydi.
        DB::transaction(function () use ($student, $target) {
            DistributionVote::create([
                'student_id' => $student->id,
                'from_group_hemis_id' => (int) $student->group_id,
                'to_group_hemis_id' => (int) $target['group_hemis_id'],
                'student_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'from_group_name' => $student->group_name,
                'to_group_name' => $target['group_name'],
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            if (Schema::hasTable('distribution_draft_assignments')) {
                DistributionDraftAssignment::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'from_group_hemis_id' => (int) $student->group_id,
                        'to_group_hemis_id' => (int) $target['group_hemis_id'],
                        'student_name' => $student->full_name,
                        'student_id_number' => $student->student_id_number,
                        'from_group_name' => $student->group_name,
                        'to_group_name' => $target['group_name'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Ovozingiz qabul qilindi. Siz ' . $target['group_name'] . ' guruhiga qo\'shildingiz.',
        ]);
    }
}
