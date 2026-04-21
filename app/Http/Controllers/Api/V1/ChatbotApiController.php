<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\StudentGrade;
use App\Models\StudentSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotApiController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'in:user,assistant',
            'history.*.content' => 'string|max:2000',
        ]);

        $student = $request->user();
        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'AI xizmati sozlanmagan',
            ], 503);
        }

        $context = $this->buildStudentContext($student);

        $systemPrompt = <<<PROMPT
Sen TDTU LMS tizimining AI yordamchisisan. Talabaga o'quv jarayoni haqida yordam berasan.

Talaba haqida ma'lumot:
{$context}

Qoidalar:
- Faqat o'quv jarayoniga oid savollarga javob ber
- Qisqa va aniq javob ber
- O'zbek tilida javob ber (agar talaba boshqa tilda yozsa, o'sha tilda javob ber)
- Talabaning shaxsiy ma'lumotlarini himoya qil, boshqa talabalar haqida ma'lumot berma
- Baholar, jadval, davomatga oid savollarga talabaning haqiqiy ma'lumotlari asosida javob ber
- Agar biror narsani bilmasang, to'g'ridan-to'g'ri ayt
PROMPT;

        $messages = [];

        if (!empty($request->history)) {
            foreach ($request->history as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $request->message,
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['content'][0]['text'] ?? '';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'reply' => $reply,
                    ],
                ]);
            }

            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI javob bera olmadi',
            ], 502);

        } catch (\Exception $e) {
            Log::error('Chatbot error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'AI xizmatiga ulanib bo\'lmadi',
            ], 500);
        }
    }

    private function buildStudentContext($student): string
    {
        $lines = [];

        $lines[] = "Ism: {$student->full_name}";
        $lines[] = "Talaba ID: {$student->student_id_number}";
        $lines[] = "GPA: " . ($student->avg_gpa ?? 'noma\'lum');

        if ($student->group) {
            $lines[] = "Guruh: {$student->group->name}";
        }
        if ($student->faculty) {
            $lines[] = "Fakultet: {$student->faculty->name}";
        }

        // Grades summary
        $subjects = StudentSubject::where('student_id', $student->id)
            ->with('curriculumSubject')
            ->get();

        if ($subjects->isNotEmpty()) {
            $lines[] = "\nFanlar va baholar:";
            foreach ($subjects as $subj) {
                $name = $subj->curriculumSubject?->name ?? 'Noma\'lum fan';
                $grades = StudentGrade::where('student_id', $student->id)
                    ->where('student_subject_id', $subj->id)
                    ->get();

                $jnAvg = $grades->where('training_type_code', 'jn')->avg('ball');
                $total = $subj->total_ball;

                $gradeInfo = "JN o'rtacha: " . ($jnAvg !== null ? round($jnAvg, 1) : '-');
                if ($total) {
                    $gradeInfo .= ", Jami: {$total}";
                }
                $lines[] = "- {$name}: {$gradeInfo}";
            }
        }

        // Attendance
        $totalAbsent = Attendance::where('student_id', $student->id)->count();
        $lines[] = "\nDavomatdan chetlashtirilgan darslar soni: {$totalAbsent}";

        return implode("\n", $lines);
    }
}
