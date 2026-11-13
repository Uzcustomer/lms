<?php

namespace App\Console\Commands;

use App\Services\ActivityLogService;
use App\Services\MtDeadline;
use App\Services\SababliMtWindow;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Muddatdan keyin yuklangan mustaqil ta'lim (MT) ishlari: fayli va yuklash yozuvi o'chiriladi,
 * jurnaldagi MT bahosi 0 qilinadi, talabaga Telegram orqali sababi yuboriladi.
 *
 * Muddat — dars jadvalidagi haqiqiy oxirgi dars (MtDeadline, sozlama bo'yicha), bazadagi
 * independents.deadline emas: uni 06:00 dagi eski buyruq kelgusi darslarni taxmin qilib surib,
 * muddat o'tgandan keyin ham yuklashni ochib qo'yardi. Tasdiqlangan sababli ariza oynasida
 * yuklanganlar (SababliMtWindow) tegilmaydi; YN yuborilgan (qulflangan) baholar sukut bo'yicha
 * o'tkazib yuboriladi (--include-locked). Sukut bo'yicha faqat hisobot; o'zgartirish --force bilan.
 */
class ZeroLateMtSubmissions extends Command
{
    protected $signature = 'mt:zero-late
        {--from=2026-09-01 : Faqat muddati shu sanadan keyingi topshiriqlar (joriy semestr)}
        {--include-locked : YN yuborilgan (qulflangan) baholarni ham 0 qilish}
        {--no-notify : Talabalarga Telegram xabari yubormaslik}
        {--force : Haqiqatan bajarish (bo\'lmasa faqat hisobot)}';

    protected $description = "Muddatdan keyin yuklangan MT: fayl o'chiriladi, baho 0, talabaga Telegram xabari";

    public function handle(TelegramService $telegram): int
    {
        $from = Carbon::parse((string) $this->option('from'))->toDateString();
        $force = (bool) $this->option('force');
        $includeLocked = (bool) $this->option('include-locked');
        $notify = !$this->option('no-notify');

        $submissions = DB::table('independent_submissions as sub')
            ->join('independents as i', 'i.id', '=', 'sub.independent_id')
            ->join('students as s', 's.id', '=', 'sub.student_id')
            ->where('sub.submitted_at', '>=', $from)
            ->orderBy('i.group_name')
            ->orderBy('sub.submitted_at')
            ->get([
                'sub.id', 'sub.file_path', 'sub.file_original_name', 'sub.submitted_at', 'sub.student_id',
                's.hemis_id as student_hemis_id', 's.full_name', 's.telegram_chat_id', 's.telegram_verified_at',
                'i.id as independent_id', 'i.group_hemis_id', 'i.group_name', 'i.subject_hemis_id', 'i.subject_name', 'i.semester_code',
            ]);

        $subjectIds = [];
        $deadlines = [];
        $rows = [];
        $late = [];
        $stats = ['sababli' => 0, 'locked' => 0];
        foreach ($submissions as $sub) {
            $subjectId = $subjectIds[$sub->subject_hemis_id] ??= DB::table('curriculum_subjects')
                ->where('curriculum_subject_hemis_id', $sub->subject_hemis_id)->value('subject_id');
            if (!$subjectId) {
                continue;
            }
            $key = $sub->group_hemis_id . '|' . $subjectId . '|' . $sub->semester_code;
            $deadline = $deadlines[$key] ??= (MtDeadline::date((string) $sub->group_hemis_id, $subjectId, (string) $sub->semester_code) ?? false);
            if (!$deadline || $deadline < $from) {
                continue;
            }
            $closes = MtDeadline::closesAt($deadline);
            $submittedAt = Carbon::parse($sub->submitted_at);
            if ($submittedAt->lte($closes)) {
                continue;
            }

            // Tasdiqlangan sababli ariza oynasida yuklangan — qonuniy
            $excuse = SababliMtWindow::excuse((string) $sub->student_hemis_id, $subjectId, $deadline);
            if ($excuse && $submittedAt->lte(SababliMtWindow::closesAt($excuse))) {
                $stats['sababli']++;
                $rows[] = [$sub->group_name, mb_substr($sub->subject_name, 0, 35), $sub->full_name, $closes->format('d.m H:i'),
                    $submittedAt->format('d.m H:i'), '—', 'sababli ariza — tegilmaydi'];
                continue;
            }

            $grade = DB::table('student_grades')
                ->where('student_hemis_id', $sub->student_hemis_id)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $sub->semester_code)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->whereNull('deleted_at')
                ->first(['id', 'grade', 'is_yn_locked']);
            $locked = $grade && $grade->is_yn_locked;
            if ($locked && !$includeLocked) {
                $stats['locked']++;
                $rows[] = [$sub->group_name, mb_substr($sub->subject_name, 0, 35), $sub->full_name, $closes->format('d.m H:i'),
                    $submittedAt->format('d.m H:i'), $grade->grade, 'YN yuborilgan — o\'tkazildi'];
                continue;
            }

            $late[] = compact('sub', 'subjectId', 'deadline', 'closes', 'submittedAt', 'grade');
            $rows[] = [$sub->group_name, mb_substr($sub->subject_name, 0, 35), $sub->full_name, $closes->format('d.m H:i'),
                $submittedAt->format('d.m H:i'), $grade ? $grade->grade : 'yo\'q',
                ($force ? 'fayl o\'chdi' : 'fayl o\'chadi') . ($grade ? ', baho 0' : ' (baho yo\'q)')];
        }

        $this->table(['Guruh', 'Fan', 'Talaba', 'Muddat', 'Yuklagan', 'Baho', 'Amal'], $rows);
        $this->info('Muddatdan keyin (sababsiz): ' . count($late) . " · sababli: {$stats['sababli']} · YN yuborilgan: {$stats['locked']}");
        if (!$force) {
            $this->warn("Faqat hisobot. Bajarish uchun: php artisan mt:zero-late --force");

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($late as $item) {
            ['sub' => $sub, 'subjectId' => $subjectId, 'closes' => $closes, 'submittedAt' => $submittedAt, 'grade' => $grade] = $item;
            DB::transaction(function () use ($sub, $grade, $closes, $submittedAt) {
                if ($grade) {
                    DB::table('student_grades')->where('id', $grade->id)->update([
                        'grade' => 0,
                        'grade_comment' => 'Muddatdan keyin yuklangan (' . $submittedAt->format('d.m.Y H:i')
                            . ', muddat ' . $closes->format('d.m.Y H:i') . ') — 0',
                        'updated_at' => now(),
                    ]);
                }
                DB::table('independent_submissions')->where('id', $sub->id)->delete();
            });
            if ($sub->file_path) {
                Storage::disk('public')->delete($sub->file_path);
            }
            ActivityLogService::log('update', 'mt_grade',
                "MT muddatdan keyin yuklangan — fayl o'chirildi, baho 0: {$sub->full_name}, {$sub->group_name}, {$sub->subject_name}"
                . " (yuklagan {$submittedAt->format('d.m.Y H:i')}, muddat {$closes->format('d.m.Y H:i')})",
                null, ['grade' => $grade?->grade, 'file' => $sub->file_original_name], ['grade' => $grade ? 0 : null, 'file' => null]);

            if ($notify && $sub->telegram_chat_id && $sub->telegram_verified_at
                && $telegram->sendToUser((string) $sub->telegram_chat_id, $this->message($sub, $closes, $submittedAt))) {
                $sent++;
            }
        }
        $this->info('Bajarildi: ' . count($late) . ' ta; Telegram xabari: ' . $sent . ' ta.');

        return self::SUCCESS;
    }

    private function message(object $sub, Carbon $closes, Carbon $submittedAt): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return "Hurmatli <b>{$e($sub->full_name)}</b>!\n\n"
            . "<b>{$e($sub->subject_name)}</b> fanidan mustaqil ta'lim (MT) ishingiz "
            . "<b>{$submittedAt->format('d.m.Y H:i')}</b> da yuklangan, topshirish muddati esa "
            . "<b>{$closes->format('d.m.Y H:i')}</b> da tugagan edi.\n\n"
            . "Qoidaga ko'ra muddat tugagandan keyin yuklangan ishlar qabul qilinmaydi: faylingiz o'chirildi "
            . "va MT bahosi <b>0</b> qilindi.\n\n"
            . "Agar shu davrda tasdiqlangan sababli arizangiz bo'lgan bo'lsa, dekanatga murojaat qiling.";
    }
}
