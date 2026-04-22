<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatApiController extends Controller
{
    public function contacts(Request $request): JsonResponse
    {
        $student = $request->user();

        $groupMembers = Student::where('group_id', $student->group_id)
            ->where('id', '!=', $student->id)
            ->where('student_status_code', 11)
            ->orderBy('short_name')
            ->get(['id', 'hemis_id', 'short_name', 'full_name', 'image']);

        $contacts = [];
        foreach ($groupMembers as $member) {
            $lastMsg = ChatMessage::where(function ($q) use ($student, $member) {
                $q->where('sender_id', $student->id)->where('receiver_id', $member->id);
            })->orWhere(function ($q) use ($student, $member) {
                $q->where('sender_id', $member->id)->where('receiver_id', $student->id);
            })->orderByDesc('created_at')->first();

            $unread = ChatMessage::where('sender_id', $member->id)
                ->where('receiver_id', $student->id)
                ->whereNull('read_at')
                ->count();

            $contacts[] = [
                'id' => $member->id,
                'hemis_id' => $member->hemis_id,
                'name' => $member->short_name ?? $member->full_name,
                'image' => $member->image,
                'last_message' => $lastMsg?->message,
                'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
                'last_message_is_me' => $lastMsg && $lastMsg->sender_id == $student->id,
                'unread_count' => $unread,
            ];
        }

        usort($contacts, function ($a, $b) {
            if ($a['last_message_at'] && $b['last_message_at']) {
                return strcmp($b['last_message_at'], $a['last_message_at']);
            }
            return $a['last_message_at'] ? -1 : ($b['last_message_at'] ? 1 : 0);
        });

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function messages(Request $request, int $contactId): JsonResponse
    {
        $student = $request->user();

        ChatMessage::where('sender_id', $contactId)
            ->where('receiver_id', $student->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::where(function ($q) use ($student, $contactId) {
            $q->where('sender_id', $student->id)->where('receiver_id', $contactId);
        })->orWhere(function ($q) use ($student, $contactId) {
            $q->where('sender_id', $contactId)->where('receiver_id', $student->id);
        })->orderBy('created_at')->get();

        $list = $messages->map(fn($m) => [
            'id' => $m->id,
            'message' => $m->message,
            'is_me' => $m->sender_id == $student->id,
            'read' => $m->read_at !== null,
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        $student = $request->user();
        $receiverId = $request->input('receiver_id');

        $receiver = Student::where('id', $receiverId)
            ->where('group_id', $student->group_id)
            ->first();

        if (!$receiver) {
            return response()->json([
                'success' => false,
                'message' => 'Faqat guruhingiz a\'zolariga xabar yuborishingiz mumkin',
            ], 403);
        }

        $msg = ChatMessage::create([
            'sender_id' => $student->id,
            'receiver_id' => $receiverId,
            'message' => $request->input('message'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'is_me' => true,
                'read' => false,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ]);
    }
}
