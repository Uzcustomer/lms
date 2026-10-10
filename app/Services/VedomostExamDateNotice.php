<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\ExamSchedule;
use App\Models\VedomostSubmission;
use App\Support\WorkdayCalculator;
use Carbon\Carbon;

/**
 * YN kunini belgilashda imtihon sanasi saqlanganda — vedomost muddatini
 * darhol hisoblab, o'qituvchi / fan mas'uli / kafedra mudiriga xabar beradi.
 *
 *  - yopilish shakli OSKI          → OSKI sanasi saqlanganda
 *  - test yoki OSKI+test           → test sanasi saqlanganda
 *    (OSKI+test da test N/A bo'lsa — OSKI sanasi saqlanganda)
 *
 * Muddat: imtihon sanasidan keyin VedomostSubmissionService::DEADLINE_WORKDAYS ish kuni.
 */
class VedomostExamDateNotice
{
    public function __construct(private VedomostSubmissionNotifier $notifier)
    {
    }

    /**
     * @param string $yn   'oski' | 'test' — qaysi sana saqlandi
     * @param bool   $send false — hech narsa yozmaydi/yubormaydi (sinov uchun)
     * @return array{sent:bool, reason:string, vedomost?:VedomostSubmission, title?:string, body?:string, deadline?:string, recipients?:array}
     */
    public function handle(ExamSchedule $exam, string $yn, bool $send = true): array
    {
        if ($exam->student_hemis_id) {
            return ['sent' => false, 'reason' => 'Individual (talaba) qatori — guruh vedomostiga tegishli emas'];
        }

        $v = $this->findVedomost($exam);
        if (!$v) {
            return ['sent' => false, 'reason' => "Vedomost (12-shakl) topilmadi — avval vedomost:sync ishlashi kerak"];
        }

        $cf = $v->closing_form;
        $trigger = match ($cf) {
            'oski' => 'oski',
            'test' => 'test',
            'oski_test' => $exam->test_na ? 'oski' : 'test',
            default => null,
        };
        if ($trigger !== $yn) {
            return ['sent' => false, 'vedomost' => $v,
                'reason' => "Yopilish shakli '{$cf}' — " . strtoupper($yn) . " sanasi bo'yicha xabar yuborilmaydi"];
        }

        $date = $yn === 'oski'
            ? (!$exam->oski_na ? $exam->oski_date : null)
            : (!$exam->test_na ? $exam->test_date : null);
        if (!$date) {
            return ['sent' => false, 'vedomost' => $v, 'reason' => strtoupper($yn) . ' sanasi yo\'q yoki N/A'];
        }

        $examDate = Carbon::parse($date)->startOfDay();
        $deadline = WorkdayCalculator::addWorkdays($examDate, VedomostSubmissionService::DEADLINE_WORKDAYS);

        [$title, $body] = $this->text($v, $yn, $examDate, $deadline);
        $recipients = $this->notifier->recipientNames($v);

        $result = [
            'sent' => false,
            'vedomost' => $v,
            'title' => $title,
            'body' => $body,
            'deadline' => $deadline->toDateString(),
            'recipients' => $recipients,
        ];

        if (!in_array($v->status, [VedomostSubmission::STATUS_PENDING, VedomostSubmission::STATUS_REJECTED], true)) {
            return $result + ['reason' => "Vedomost allaqachon topshirilgan ({$v->status}) — xabar kerak emas"];
        }

        if (!$send) {
            return $result + ['reason' => 'Sinov rejimi — yuborilmadi'];
        }

        // Muddatni darhol yangilaymiz (06:00 dagi sync kutilmaydi). Muddat
        // o'zgarsa model ogohlantirish bosqichini o'zi tiklaydi.
        $v->forceFill([
            'base_type' => 'exam',
            'base_date' => $examDate->toDateString(),
            'deadline' => $deadline->toDateString(),
        ])->save();

        if (!VedomostSubmissionNotifier::enabled()) {
            return array_merge($result, ['reason' => "Vedomost xabarlari sozlamada o'chirilgan — muddat yangilandi, xabar yuborilmadi"]);
        }

        $this->notifier->notifyCustom($v, $title, $body);

        return array_merge($result, ['sent' => true, 'reason' => 'Yuborildi']);
    }

    private function findVedomost(ExamSchedule $exam): ?VedomostSubmission
    {
        // exam_schedules.subject_id — HEMIS subject_id YOKI curriculum_subject_hemis_id
        $sid = $exam->subject_id;

        return VedomostSubmission::where('group_hemis_id', $exam->group_hemis_id)
            ->where('semester_code', $exam->semester_code)
            ->where('form_type', VedomostSubmission::FORM_12)
            ->where(function ($q) use ($sid) {
                $q->where('subject_id', $sid)
                    ->orWhereIn('curriculum_subject_id', CurriculumSubject::where('curriculum_subject_hemis_id', $sid)->select('id'));
            })
            ->first();
    }

    private function text(VedomostSubmission $v, string $yn, Carbon $examDate, Carbon $deadline): array
    {
        $label = $yn === 'oski' ? 'OSKI' : 'Test';
        $toLabel = $yn === 'oski' ? 'OSKIga' : 'testga';

        $title = 'Vedomost topshirish muddati belgilandi';
        $body = "🗓 {$v->group_name} — {$v->subject_name}\n"
            . "{$label} sanasi: {$examDate->format('d.m.Y')}.\n"
            . "Vedomostni {$deadline->format('d.m.Y')} gacha topshiring.\n"
            . "{$label} kunigacha {$toLabel} kiradiganlar ro'yxatini test markaziga topshiring.";

        return [$title, $body];
    }
}
