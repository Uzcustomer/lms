<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendStudentPhotoToMoodle;
use App\Models\Student;
use App\Models\StudentPhoto;
use App\Services\FaceIdService;
use App\Services\MoodleFaceDescriptorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registrator-office face check.
 *
 * The registrator captures the student's face on a webcam in the LMS browser
 * UI; the page loads Moodle's face-api.js + model files, so the descriptor it
 * extracts is byte-comparable to the descriptor the test centre will compute
 * later. The flow has two stages:
 *
 *   1. Anti-fraud verify (this controller's verify action):
 *      ArcFace 85% similarity webcam ↔ HEMIS photo AND webcam ↔ approved
 *      mark photo. Both must pass. On success we save a new student_photos
 *      row, persist the descriptor to face_id_descriptors, push the photo to
 *      Moodle and append the descriptor to auth_faceid_descriptors.
 *
 *   2. Moodle pre-check (precheck action):
 *      Sends the live descriptor to local_hemisexport_match_face_descriptor
 *      and reports back whether the test-centre login threshold (0.36) would
 *      have accepted this attempt.
 */
class RegistratorFaceCheckController extends Controller
{
    /** Strict anti-fraud similarity threshold (percent). */
    private const ANTI_FRAUD_THRESHOLD = 85.0;

    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $students = Student::query()
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('full_name', 'like', "%{$query}%")
                        ->orWhere('student_id_number', 'like', "%{$query}%")
                        ->orWhere('passport_number', 'like', "%{$query}%");
                });
            })
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.registrator-face-check.index', [
            'students' => $students,
            'query'    => $query,
        ]);
    }

    public function show(Request $request, string $studentIdNumber)
    {
        $student = Student::where('student_id_number', $studentIdNumber)->firstOrFail();
        // The page deliberately does NOT expose any reference photos or
        // similarity scores to the registrator UI — that data must stay
        // server-side so a registrator can't game the AI checks for someone
        // they recognise. The view only needs the student's identity.
        return view('admin.registrator-face-check.show', [
            'student' => $student,
        ]);
    }

    /**
     * Confirm that the username typed by the student during the recapture flow
     * matches the student selected from the registrator's list. Returns the
     * full name so the registrator can compare against the passport.
     *
     * The lookup deliberately echoes only the public full_name; nothing is
     * leaked about HEMIS photos, descriptor state or similarity scores.
     */
    public function lookup(Request $request, string $studentIdNumber): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:64',
        ]);

        $student = Student::where('student_id_number', $studentIdNumber)->firstOrFail();
        $typed = trim((string) $data['username']);

        if ($typed === '' || !hash_equals((string) $student->student_id_number, $typed)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Username mos kelmadi. Talaba o\'zining ID raqamini kiritsin.',
            ], 422);
        }

        return response()->json([
            'ok'        => true,
            'full_name' => (string) $student->full_name,
        ]);
    }

    /**
     * Stage 1: anti-fraud verify + save + sync.
     *
     * Body: snapshot (data URL), descriptor (128 floats), det_score (optional),
     *       username (must match the URL student — confirmed in the lookup step).
     */
    public function verify(Request $request, string $studentIdNumber): JsonResponse
    {
        $data = $request->validate([
            'snapshot'     => 'required|string',
            'descriptor'   => 'required|array|size:128',
            'descriptor.*' => 'numeric',
            'det_score'    => 'nullable|numeric',
            'username'     => 'required|string|max:64',
        ]);

        $student = Student::where('student_id_number', $studentIdNumber)->firstOrFail();

        // Re-verify the typed username server-side — a registrator with the URL
        // must not be able to skip the lookup confirmation step.
        if (!hash_equals((string) $student->student_id_number, trim((string) $data['username']))) {
            return response()->json([
                'ok'      => false,
                'message' => 'Username mos kelmadi.',
            ], 422);
        }

        if (empty($student->image)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Talabaning HEMIS rasmi topilmadi.',
            ], 422);
        }

        $approvedPhoto = FaceIdService::getApprovedStudentPhoto($student);
        if (!$approvedPhoto) {
            return response()->json([
                'ok'      => false,
                'message' => 'Talabaning markda tasdiqlangan rasmi topilmadi. '
                          . 'Avval tutor orqali rasm yuklash kerak.',
            ], 422);
        }

        $snapshot = FaceIdService::saveTemporarySnapshot($data['snapshot']);
        if (!$snapshot) {
            return response()->json([
                'ok'      => false,
                'message' => 'Webcam rasmini saqlab bo\'lmadi.',
            ], 422);
        }

        $cleanup = function () use ($snapshot) {
            FaceIdService::deleteTemporarySnapshot($snapshot['rel']);
        };

        try {
            $hemisRef = $student->image;
            if (!str_starts_with((string) $hemisRef, 'http')) {
                $hemisRef = rtrim((string) config('services.hemis.base_url', 'https://student.ttatf.uz'), '/')
                    . '/' . ltrim((string) $hemisRef, '/');
            }
            $markRef = asset($approvedPhoto->photo_path);

            $vsHemis = FaceIdService::compareViaArcFace($snapshot['url'], $hemisRef);
            $vsMark  = FaceIdService::compareViaArcFace($snapshot['url'], $markRef);

            if (!$vsHemis || !$vsMark) {
                Log::warning('[RegistratorFaceCheck] ArcFace compare returned null', [
                    'student_id_number' => $studentIdNumber,
                    'vs_hemis'          => $vsHemis,
                    'vs_mark'           => $vsMark,
                ]);
                return response()->json([
                    'ok'      => false,
                    'message' => 'Texnik xato. Birozdan keyin qayta urining.',
                ], 502);
            }

            $simHemis = (float) $vsHemis['similarity_percent'];
            $simMark  = (float) $vsMark['similarity_percent'];
            $passed   = $simHemis >= self::ANTI_FRAUD_THRESHOLD
                     && $simMark  >= self::ANTI_FRAUD_THRESHOLD;

            // Audit log: keep the actual scores server-side regardless of
            // what we tell the registrator.
            Log::info('[RegistratorFaceCheck] anti-fraud verify', [
                'student_id_number' => $studentIdNumber,
                'similarity_hemis'  => $simHemis,
                'similarity_mark'   => $simMark,
                'passed'            => $passed,
                'registrator_id'    => auth()->id(),
            ]);

            if (!$passed) {
                // Generic message — never reveal which reference failed or by
                // how much. The registrator must just take a sharper photo.
                return response()->json([
                    'ok'      => false,
                    'message' => 'Tasdiqlanmadi. Sifatliroq rasm oling: '
                              . 'webcam yorug\'roq joyga, yuz ramkaga to\'liq tushsin.',
                ], 422);
            }

            $newPhoto = $this->persistWebcamPhoto(
                $student, $approvedPhoto, $data['snapshot'],
                $simHemis, $simMark, $data['descriptor'], (int) auth()->id()
            );

            if (!$newPhoto) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Rasmni saqlab bo\'lmadi.',
                ], 500);
            }

            // Photo file → Moodle (existing pipeline).
            SendStudentPhotoToMoodle::dispatch($newPhoto->id);

            // Descriptor → Moodle (append to auth_faceid_descriptors).
            $descriptorService = app(MoodleFaceDescriptorService::class);
            $descriptorService->saveDescriptor(
                (string) $student->student_id_number,
                array_map('floatval', $data['descriptor']),
                isset($data['det_score']) ? (float) $data['det_score'] : null,
                StudentPhoto::SOURCE_REGISTRATOR_WEBCAM
            );

            return response()->json([
                'ok'      => true,
                'message' => 'Tasdiqlandi. Yuz Moodle\'ga yuborildi. '
                          . 'Endi yana bir bor "Test markazi simulyatsiyasi" tugmasini bosing.',
            ]);
        } finally {
            $cleanup();
        }
    }

    /**
     * Stage 2: Moodle pre-check. Captures a fresh descriptor and asks Moodle
     * whether the test-centre login threshold would accept it.
     *
     * Body: descriptor (128 floats).
     */
    public function precheck(Request $request, string $studentIdNumber): JsonResponse
    {
        $data = $request->validate([
            'descriptor'   => 'required|array|size:128',
            'descriptor.*' => 'numeric',
        ]);

        $student = Student::where('student_id_number', $studentIdNumber)->firstOrFail();

        $descriptorService = app(MoodleFaceDescriptorService::class);
        $result = $descriptorService->matchDescriptor(
            (string) $student->student_id_number,
            array_map('floatval', $data['descriptor'])
        );

        if (!$result['ok']) {
            return response()->json([
                'ok'      => false,
                'matched' => null,
                'message' => 'Texnik xato. Birozdan keyin qayta urining.',
            ], 502);
        }

        $body = is_array($result['response']) ? $result['response'] : [];
        $status = (string) ($body['status'] ?? '');
        $matched = (bool) ($body['matched'] ?? false);

        // Audit log only — registrator never sees distance/confidence/threshold.
        Log::info('[RegistratorFaceCheck] moodle precheck', [
            'student_id_number' => $studentIdNumber,
            'matched'           => $matched,
            'status'            => $status,
            'distance'          => $body['distance']  ?? null,
            'confidence'        => $body['confidence'] ?? null,
            'registrator_id'    => auth()->id(),
        ]);

        $message = match (true) {
            $matched                              => 'Test markazida muammosiz o\'tadi.',
            $status === 'no_descriptors'          => 'Talaba uchun descriptor hali yo\'q. Yangi rasm olishingiz kerak.',
            default                               => 'Test markazida o\'tmaydi. Yangi rasm olish tavsiya etiladi.',
        };

        return response()->json([
            'ok'      => true,
            'matched' => $matched,
            'status'  => $status,
            'message' => $message,
        ]);
    }

    /**
     * Persist the webcam capture as a NEW row in student_photos. Old photos
     * are kept untouched (they may be sharper studio shots and the test
     * centre's find_best_match will scan all stored descriptors anyway).
     *
     * @param array<int, float> $descriptor
     */
    private function persistWebcamPhoto(
        Student $student,
        StudentPhoto $referencePhoto,
        string $snapshotDataUrl,
        float $simHemis,
        float $simMark,
        array $descriptor,
        int $registratorUserId
    ): ?StudentPhoto {
        $base64 = $snapshotDataUrl;
        if (str_starts_with($base64, 'data:')) {
            $parts = explode(',', $base64, 2);
            if (count($parts) !== 2) {
                return null;
            }
            $base64 = $parts[1];
        }
        $bytes = base64_decode($base64, true);
        if ($bytes === false || strlen($bytes) < 200) {
            return null;
        }

        $relDir = 'uploads/student-photos/' . date('Y/m');
        $absDir = public_path($relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
            return null;
        }

        $filename = sprintf(
            'reg_%s_%s.jpg',
            preg_replace('/[^A-Za-z0-9_]/', '', (string) $student->student_id_number),
            uniqid()
        );
        $relPath = $relDir . '/' . $filename;
        $absPath = $absDir . '/' . $filename;
        if (file_put_contents($absPath, $bytes) === false) {
            return null;
        }

        return StudentPhoto::create([
            'student_id_number' => $student->student_id_number,
            'full_name'         => $student->full_name,
            'group_name'        => $student->group_name,
            'semester_name'     => $student->semester_name,
            'uploaded_by'       => 'registrator_ofisi',
            'uploaded_by_teacher_id' => $referencePhoto->uploaded_by_teacher_id,
            'photo_path'        => $relPath,
            'source'            => StudentPhoto::SOURCE_REGISTRATOR_WEBCAM,
            'status'            => StudentPhoto::STATUS_APPROVED,
            'reviewed_by_name'  => 'registrator_ofisi:' . $registratorUserId,
            'reviewed_at'       => now(),
            'similarity_score'  => min($simHemis, $simMark),
            'similarity_status' => 'passed',
            'similarity_checked_at' => now(),
            'similarity_hemis'  => $simHemis,
            'similarity_mark'   => $simMark,
            'captured_by_user_id' => $registratorUserId,
            'face_embedding'    => array_map('floatval', $descriptor),
            'embedding_extracted_at' => now(),
        ]);
    }
}
