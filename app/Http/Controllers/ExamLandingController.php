<?php

namespace App\Http\Controllers;

use App\Jobs\BookMoodleGroupExam;
use App\Models\ExamSchedule;
use App\Services\ExamLandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Trilingual exam landing page that sits between a successful FaceID login
 * and the actual Moodle quiz. The Moodle auth_faceid plugin redirects the
 * student here with a short-lived signed token; we render the picker, and
 * once they tap a language for an exam we (a) persist that choice on
 * `students.exam_language_code`, (b) push a fresh booking via
 * BookMoodleGroupExam::dispatchSync so the language-specific Moodle quiz
 * (YN test (uzb)_… / (rus)_… / (eng)_…) exists for that student, and
 * (c) return the Moodle quiz URL the browser should navigate to.
 *
 * Public route — token possession is the only proof of identity. Tokens
 * expire after ~15 minutes; without one the controller bounces to /.
 */
class ExamLandingController extends Controller
{
    public function __construct(private ExamLandingService $landing) {}

    /**
     * Render the picker. Token comes from the URL, no admin auth needed.
     */
    public function show(Request $request, string $token): mixed
    {
        $student = $this->landing->resolveToken($token);
        if (!$student) {
            // Trilingual "session expired / invalid token" view. Reuse the
            // same blade with an empty exams list and an explicit flag, so
            // the operator only has to translate this once.
            return response()->view('exam.landing', [
                'student'       => null,
                'exams'         => [],
                'token'         => $token,
                'invalidToken'  => true,
            ], 410);
        }

        $exams = $this->landing->examsForStudent($student);

        return view('exam.landing', [
            'student'      => $student,
            'exams'        => $exams,
            'token'        => $token,
            'invalidToken' => false,
        ]);
    }

    /**
     * Persist the chosen language, push a fresh booking, return the Moodle
     * redirect URL. JSON in / JSON out — the picker view's inline JS calls
     * this and then sets window.location to data.redirect_url.
     */
    public function choose(Request $request, string $token): JsonResponse
    {
        $student = $this->landing->resolveToken($token);
        if (!$student) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_token',
                'message' => 'Token noto\'g\'ri yoki muddati o\'tgan.',
            ], 410);
        }

        $validator = Validator::make($request->all(), [
            'exam_schedule_id' => ['required', 'integer'],
            'yn_type'          => ['required', 'string', 'in:oski,test'],
            'attempt'          => ['required', 'integer', 'in:1,2,3'],
            'lang'             => ['required', 'string', 'in:uz,ru,en'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $scheduleId = (int) $request->input('exam_schedule_id');
        $ynType     = strtolower((string) $request->input('yn_type'));
        $attempt    = (int) $request->input('attempt');
        $lang       = strtolower((string) $request->input('lang'));

        $schedule = ExamSchedule::find($scheduleId);
        if (!$schedule) {
            return response()->json([
                'ok' => false,
                'error' => 'schedule_not_found',
            ], 404);
        }

        // Sanity: the schedule the picker is acting on must actually be one
        // of "today's exams" for this student. Stops a stale browser tab
        // re-firing yesterday's choice and accidentally re-pushing a booking.
        $matches = collect($this->landing->examsForStudent($student))
            ->contains(function ($row) use ($scheduleId, $ynType, $attempt) {
                return $row['schedule_id'] === $scheduleId
                    && $row['yn_type'] === $ynType
                    && $row['attempt'] === $attempt;
            });
        if (!$matches) {
            return response()->json([
                'ok' => false,
                'error' => 'not_a_current_exam',
                'message' => 'Bu imtihon bugungi ro\'yxatdan topilmadi.',
            ], 422);
        }

        // (a) Persist the chosen language on the student. Using the HEMIS
        // numeric code so MoodleExamBookingService::normalizeLang (which
        // consults services.moodle.lang_map) picks it up unchanged.
        $hemisCode = ExamLandingService::HEMIS_LANG_CODES[$lang] ?? null;
        if ($hemisCode === null) {
            return response()->json(['ok' => false, 'error' => 'invalid_lang'], 422);
        }
        if ((string) $student->exam_language_code !== $hemisCode) {
            $student->forceFill(['exam_language_code' => $hemisCode])->save();
        }

        // (b) Push a fresh booking synchronously so the right-language Moodle
        // quiz has the cutoffs / overrides row for this student before the
        // browser hops to view.php. The booking push is idempotent — re-calling
        // it just updates the existing row.
        try {
            BookMoodleGroupExam::dispatchSync($scheduleId, $ynType, false, $attempt);
        } catch (\Throwable $e) {
            Log::warning('exam_landing.book_dispatch_failed', [
                'schedule_id' => $scheduleId,
                'yn_type'     => $ynType,
                'attempt'     => $attempt,
                'lang'        => $lang,
                'student'     => $student->student_id_number,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'booking_push_failed',
                'message' => 'Imtihon bookingni yangilab bo\'lmadi. Iltimos proctor bilan bog\'laning.',
            ], 502);
        }

        // (c) Resolve the Moodle redirect URL. The picker hands the browser a
        // tiny Moodle resolver endpoint that takes quiz_idnumber and 302s to
        // the real view.php?id=NN so the LMS never has to know Moodle's
        // internal cmid numbering.
        $idnumber = $this->landing->quizIdnumberFor($ynType, $lang, $attempt);
        if ($idnumber === null) {
            return response()->json([
                'ok' => false,
                'error' => 'no_quiz_idnumber',
                'message' => 'Test idnumber sozlamasi topilmadi.',
            ], 500);
        }
        $redirect = $this->landing->moodleQuizRedirectUrl($idnumber);
        if ($redirect === '') {
            return response()->json([
                'ok' => false,
                'error' => 'moodle_wwwroot_missing',
                'message' => 'Moodle URL sozlanmagan.',
            ], 500);
        }

        Log::info('exam_landing.choice', [
            'student'     => $student->student_id_number,
            'schedule_id' => $scheduleId,
            'yn_type'     => $ynType,
            'attempt'     => $attempt,
            'lang'        => $lang,
            'idnumber'    => $idnumber,
        ]);

        return response()->json([
            'ok'           => true,
            'redirect_url' => $redirect,
            'idnumber'     => $idnumber,
        ]);
    }
}
