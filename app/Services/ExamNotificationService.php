<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\Student;
use App\Models\StudentNotification;

class ExamNotificationService
{
    public function __construct(private TelegramService $telegram) {}

    /**
     * Notify the student that their assigned computer is now revealed
     * (called {reveal_minutes_before} minutes before planned_start).
     */
    public function notifyReveal(ComputerAssignment $assignment): void
    {
        $student = $this->resolveStudent($assignment->student_hemis_id);
        if (!$student) {
            return;
        }

        $when = optional($assignment->planned_start)->format('H:i') ?? '';
        $title = 'Test boshlanishiga oz qoldi';
        $messageHtml = "🖥️ <b>Sizning kompyuter raqamingiz: #{$assignment->computer_number}</b>\n\n"
            . "⏰ Boshlanish vaqti: <b>{$when}</b>\n"
            . "📍 Test markaziga kelib, faqat <b>#{$assignment->computer_number}</b> kompyuterga o'tiring.";
        $messagePlain = "Kompyuter raqami: #{$assignment->computer_number}. Boshlanish: {$when}. Faqat shu kompyuterga o'tiring.";

        $this->push($student, $title, $messagePlain, [
            'computer_number' => $assignment->computer_number,
            'planned_start' => optional($assignment->planned_start)->toIso8601String(),
            'event' => 'reveal',
        ]);
        $this->sendTelegram($student, $messageHtml);
    }

    /**
     * Warn the next-in-line student that the seat will free up soon
     * (current occupant has reached the warn threshold).
     */
    public function notifyApproaching(ComputerAssignment $assignment): void
    {
        $student = $this->resolveStudent($assignment->student_hemis_id);
        if (!$student) {
            return;
        }

        $title = 'Tayyorlaning, tez orada test boshlanadi';
        $messageHtml = "⏳ <b>Tayyorlaning!</b>\n\n"
            . "Sizdan oldin #{$assignment->computer_number} kompyuterda test yechayotgan talaba tugatish arafasida.\n"
            . "Iltimos, test markaziga yaqinlashing — tez orada o'tirishingiz mumkin bo'ladi.";
        $messagePlain = "Tayyorlaning. #{$assignment->computer_number} kompyuter tez orada bo'shaydi.";

        $this->push($student, $title, $messagePlain, [
            'computer_number' => $assignment->computer_number,
            'event' => 'approaching',
        ]);
        $this->sendTelegram($student, $messageHtml);
    }

    /**
     * Tell the student the seat is free and they may begin.
     */
    public function notifyReady(ComputerAssignment $assignment): void
    {
        $student = $this->resolveStudent($assignment->student_hemis_id);
        if (!$student) {
            return;
        }

        $title = 'Kompyuter bo\'shadi — kirsangiz bo\'ladi';
        $messageHtml = "✅ <b>Kompyuter #{$assignment->computer_number} bo'shadi.</b>\n\n"
            . "Endi kelib o'tirib testni boshlashingiz mumkin.";
        $messagePlain = "#{$assignment->computer_number} kompyuter bo'shadi. Kirib testni boshlang.";

        $this->push($student, $title, $messagePlain, [
            'computer_number' => $assignment->computer_number,
            'event' => 'ready',
        ]);
        $this->sendTelegram($student, $messageHtml);
    }

    /**
     * Tell the student their computer has been changed (reserve fallback).
     */
    public function notifyMoved(ComputerAssignment $assignment, int $fromComputer, string $reason): void
    {
        $student = $this->resolveStudent($assignment->student_hemis_id);
        if (!$student) {
            return;
        }

        $reasonText = match ($reason) {
            'overflow' => 'Oldingi talaba testni vaqtida tugatmadi',
            'broken' => 'Texnik nosozlik',
            'no_show' => 'Texnik sabab',
            default => 'Texnik sabab',
        };

        $title = 'Kompyuter raqamingiz o\'zgartirildi';
        $messageHtml = "🔄 <b>Kompyuter raqamingiz o'zgartirildi.</b>\n\n"
            . "Eski: <s>#{$fromComputer}</s>\n"
            . "Yangi: <b>#{$assignment->computer_number}</b> (zahira)\n\n"
            . "Sabab: {$reasonText}";
        $messagePlain = "Kompyuter #{$fromComputer} → #{$assignment->computer_number} ga ko'chirildi. Sabab: {$reasonText}";

        $this->push($student, $title, $messagePlain, [
            'computer_number' => $assignment->computer_number,
            'moved_from' => $fromComputer,
            'reason' => $reason,
            'event' => 'moved',
        ]);
        $this->sendTelegram($student, $messageHtml);
    }

    private function push(Student $student, string $title, string $message, array $data = []): void
    {
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'exam_seat',
            'title' => $title,
            'message' => $message,
            'link' => '/student/exam-schedule',
            'data' => $data,
            'read_at' => null,
        ]);
    }

    private function sendTelegram(Student $student, string $messageHtml): void
    {
        if (empty($student->telegram_chat_id) || empty($student->telegram_verified_at)) {
            return;
        }
        $this->telegram->sendToUser((string) $student->telegram_chat_id, $messageHtml);
    }

    private function resolveStudent(?string $hemisId): ?Student
    {
        if (!$hemisId) {
            return null;
        }
        return Student::where('hemis_id', $hemisId)->first();
    }
}
