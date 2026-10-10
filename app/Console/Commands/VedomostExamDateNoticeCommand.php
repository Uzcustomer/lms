<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Services\VedomostExamDateNotice;
use Illuminate\Console\Command;

class VedomostExamDateNoticeCommand extends Command
{
    protected $signature = 'vedomost:exam-notice
        {group : Guruh nomidan bo\'lak, masalan p/p25-07a}
        {subject : Fan nomidan bo\'lak, masalan Gistologiya}
        {--send : Haqiqatan yuborish (bo\'lmasa faqat ko\'rsatadi)}';

    protected $description = "YN sanasi bo'yicha vedomost xabarini sinash: kimga, qanday matn, qaysi muddat";

    public function handle(VedomostExamDateNotice $notice): int
    {
        $exams = ExamSchedule::query()
            ->whereNull('student_hemis_id')
            ->where('subject_name', 'like', '%' . $this->argument('subject') . '%')
            ->whereIn('group_hemis_id', function ($q) {
                $q->select('group_hemis_id')->from('groups')
                    ->where('name', 'like', '%' . $this->argument('group') . '%');
            })
            ->get();

        if ($exams->isEmpty()) {
            $this->warn('YN kunini belgilashda bunday guruh+fan uchun yozuv topilmadi.');
            return self::FAILURE;
        }

        $send = (bool) $this->option('send');

        foreach ($exams as $exam) {
            $this->line(str_repeat('─', 60));
            $this->info("{$exam->subject_name} | guruh {$exam->group_hemis_id} | semestr {$exam->semester_code}");
            $this->line('OSKI: ' . ($exam->oski_na ? 'N/A' : ($exam->oski_date?->format('d.m.Y') ?? '—'))
                . '   Test: ' . ($exam->test_na ? 'N/A' : ($exam->test_date?->format('d.m.Y') ?? '—')));

            foreach (['oski', 'test'] as $yn) {
                $r = $notice->handle($exam, $yn, $send);
                $this->line('');
                $this->comment(strtoupper($yn) . ' sanasi saqlanganda → ' . $r['reason']);

                if (isset($r['vedomost'])) {
                    $this->line("  Yopilish shakli: {$r['vedomost']->closing_form}, vedomost holati: {$r['vedomost']->status}");
                }
                if (isset($r['body'])) {
                    $this->line('  Muddat: ' . $r['deadline']);
                    foreach ($r['recipients'] as $p) {
                        $this->line('  → ' . $p['name'] . ($p['telegram'] ? ' (Telegram ✓)' : ' (Telegram ulanmagan — faqat saytda)'));
                    }
                    $this->line('');
                    $this->line('  📋 ' . $r['title']);
                    foreach (explode("\n", $r['body']) as $l) {
                        $this->line('  ' . $l);
                    }
                }
            }
        }

        if (!$send) {
            $this->line('');
            $this->warn("Sinov rejimi — hech narsa yuborilmadi. Yuborish uchun: --send");
        }

        return self::SUCCESS;
    }
}
