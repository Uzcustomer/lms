<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Test markazi paneli — qayta o'qish guruhlaridan kelgan vedomostlar:
 * OSKE va TEST natijalarini kiritish.
 */
class RetakeTestMarkaziController extends Controller
{
    public function __construct(
        private RetakeJournalService $service,
    ) {}

    public function index()
    {
        $this->authorize();

        // Yuborilgan guruhlar — eng yangi avval
        $groups = RetakeGroup::query()
            ->whereNotNull('sent_to_test_markazi_at')
            ->whereIn('assessment_type', ['oske', 'test', 'oske_test'])
            ->with('teacher')
            ->withCount('applications as students_count')
            ->orderByDesc('sent_to_test_markazi_at')
            ->paginate(30);

        return view('teacher.retake-test-markazi.index', [
            'groups' => $groups,
        ]);
    }

    public function show(int $groupId)
    {
        $this->authorize();

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);
        if (!$group->sent_to_test_markazi_at) {
            abort(404, 'Bu guruh test markaziga yuborilmagan');
        }

        $applications = $this->service->applications($group);
        $gradesMap = $this->service->gradesMap($group);
        $mustaqilMap = $this->service->mustaqilMap($group);

        return view('teacher.retake-test-markazi.show', [
            'group' => $group,
            'applications' => $applications,
            'gradesMap' => $gradesMap,
            'mustaqilMap' => $mustaqilMap,
        ]);
    }

    public function saveScore(Request $request, int $groupId): JsonResponse
    {
        $this->authorize();

        $data = $request->validate([
            'application_id' => 'required|integer',
            'oske_score' => 'nullable|numeric|min:0|max:100',
            'test_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $group = RetakeGroup::findOrFail($groupId);
        if (!$group->sent_to_test_markazi_at) {
            return response()->json(['success' => false, 'message' => 'Guruh test markaziga yuborilmagan'], 403);
        }

        $app = RetakeApplication::query()
            ->where('id', $data['application_id'])
            ->where('retake_group_id', $group->id)
            ->firstOrFail();

        $actor = RetakeAccess::currentStaff();

        try {
            $this->service->saveOskeTestScore(
                $app,
                $data['oske_score'] !== null && $data['oske_score'] !== '' ? (float) $data['oske_score'] : null,
                $data['test_score'] !== null && $data['test_score'] !== '' ? (float) $data['test_score'] : null,
                $actor,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        $fresh = $app->refresh();
        return response()->json([
            'success' => true,
            'oske_score' => $fresh->oske_score,
            'test_score' => $fresh->test_score,
            'final_grade' => $fresh->final_grade_value,
        ]);
    }

    private function authorize(): void
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $allowed = $actor->hasAnyRole([
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
            ProjectRole::TEST_CENTER->value,
        ]);
        if (!$allowed) {
            abort(403, 'Sizda test markaziga ruxsat yo\'q');
        }
    }
}
