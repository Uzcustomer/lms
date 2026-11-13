<?php

namespace App\Console\Commands;

use App\Services\MtDeadline;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Guruhning mustaqil ta'lim (MT) holati: har fan bo'yicha talabalar — muddat (dars jadvalidan,
 * MtDeadline), fayl yuklangan vaqt, vaqtida / kech / yuklamagan va MT bahosi.
 *
 *   php artisan mt:status p/p24-04a --subject=171 --subject=141 --subject=142
 */
class MtStatusReport extends Command
{
    protected $signature = 'mt:status
        {group : Guruh nomi (masalan p/p24-04a)}
        {--subject=* : Fan subject_id (bir nechta bo\'lishi mumkin); bo\'lmasa — guruhning barcha fanlari}
        {--from=2026-09-01 : Faqat muddati shu sanadan keyingi topshiriqlar}';

    protected $description = "Guruh bo'yicha MT: kim qachon yukladi, muddat, holat va baho";

    public function handle(): int
    {
        $group = DB::table('groups')->where('name', (string) $this->argument('group'))->first(['group_hemis_id', 'name']);
        if (!$group) {
            $this->error('Guruh topilmadi');

            return self::FAILURE;
        }
        $subjects = array_filter(array_map('strval', (array) $this->option('subject')));
        $from = Carbon::parse((string) $this->option('from'))->toDateString();

        $independents = DB::table('independents as i')
            ->join('curriculum_subjects as cs', 'cs.curriculum_subject_hemis_id', '=', 'i.subject_hemis_id')
            ->where('i.group_hemis_id', $group->group_hemis_id)
            ->when($subjects, fn ($q) => $q->whereIn('cs.subject_id', $subjects))
            ->orderBy('cs.subject_id')
            ->get(['i.id', 'i.subject_name', 'i.semester_code', 'cs.subject_id'])
            ->unique('id');
        $students = DB::table('students')->where('group_id', $group->group_hemis_id)->orderBy('full_name')->get(['id', 'hemis_id', 'full_name']);

        foreach ($independents as $i) {
            $deadline = MtDeadline::date((string) $group->group_hemis_id, $i->subject_id, (string) $i->semester_code);
            if (!$deadline || $deadline < $from) {
                continue;
            }
            $closes = MtDeadline::closesAt($deadline);
            $subs = DB::table('independent_submissions')->where('independent_id', $i->id)->get()->keyBy('student_id');
            $grades = DB::table('student_grades')
                ->whereIn('student_hemis_id', $students->pluck('hemis_id'))
                ->where('subject_id', $i->subject_id)
                ->where('semester_code', $i->semester_code)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->whereNull('deleted_at')
                ->pluck('grade', 'student_hemis_id');

            $rows = [];
            $counts = ['vaqtida' => 0, 'KECH' => 0, 'yuklamagan' => 0];
            foreach ($students as $n => $s) {
                $sub = $subs->get($s->id);
                $at = $sub ? Carbon::parse($sub->submitted_at) : null;
                $status = !$at ? 'yuklamagan' : ($at->gt($closes) ? 'KECH' : 'vaqtida');
                $counts[$status]++;
                $grade = $grades->get($s->hemis_id);
                $rows[] = [$n + 1, $s->full_name, $at ? $at->format('d.m.Y H:i') : '—', $status, $grade === null ? '—' : rtrim(rtrim((string) $grade, '0'), '.')];
            }

            $this->newLine();
            $this->info("{$group->name} · {$i->subject_name} (subject_id {$i->subject_id}) · muddat: {$closes->format('d.m.Y H:i')}");
            $this->table(['#', 'Talaba', 'Yuklagan', 'Holat', 'MT baho'], $rows);
            $this->line("Vaqtida: {$counts['vaqtida']} · Kech: {$counts['KECH']} · Yuklamagan: {$counts['yuklamagan']}");
        }

        return self::SUCCESS;
    }
}
