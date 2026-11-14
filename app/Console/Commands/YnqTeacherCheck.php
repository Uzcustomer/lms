<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\YnQaydnomaListController;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * "Vedomostlarim" tekshiruvi: o'qituvchining dars jadvalidagi guruh × fanlari va sahifadagi qatorlar
 * (holat, fan tugaganmi, YN yuborilganmi) — sahifa bo'sh chiqsa sababini topish uchun.
 *
 *   php artisan ynq:teacher "ALLAMURATOVA"     (ism bo'lagi, teachers.id yoki HEMIS id)
 */
class YnqTeacherCheck extends Command
{
    protected $signature = 'ynq:teacher {teacher : Ism bo\'lagi, teachers.id yoki HEMIS id}';

    protected $description = "Vedomostlarim: o'qituvchining fanlari va qaydnoma holati (tekshiruv)";

    public function handle(): int
    {
        $q = (string) $this->argument('teacher');
        $teachers = Teacher::query()
            ->where(fn ($w) => ctype_digit($q) ? $w->where('id', $q)->orWhere('hemis_id', $q) : $w->where('full_name', 'like', '%' . $q . '%'))
            ->limit(5)->get(['id', 'hemis_id', 'full_name']);
        if ($teachers->count() !== 1) {
            $this->warn($teachers->isEmpty() ? "O'qituvchi topilmadi" : 'Bir nechta topildi — aniqroq yozing:');
            $this->table(['id', 'hemis_id', 'F.I.Sh.'], $teachers->map(fn ($t) => [$t->id, $t->hemis_id, $t->full_name])->all());

            return self::FAILURE;
        }
        $teacher = $teachers->first();
        $this->info("{$teacher->full_name} · teachers.id {$teacher->id} · HEMIS id {$teacher->hemis_id}");

        $base = fn () => DB::table('schedules')->whereNull('deleted_at');
        $this->table(['Dars jadvali', 'Soni'], [
            ['employee_id bo\'yicha — hammasi', $base()->where('employee_id', $teacher->hemis_id)->count()],
            ['  joriy o\'quv yili', $base()->where('employee_id', $teacher->hemis_id)->where('education_year_current', true)->count()],
            ['  joriy yil, ma\'ruza / nazoratsiz', $base()->where('employee_id', $teacher->hemis_id)->where('education_year_current', true)
                ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])->count()],
            ['employee_name bo\'yicha (familiya) — joriy yil', $base()->where('employee_name', 'like', strtok((string) $teacher->full_name, ' ') . '%')
                ->where('education_year_current', true)->count()],
        ]);
        $ids = $base()->where('employee_name', 'like', strtok((string) $teacher->full_name, ' ') . '%')->where('education_year_current', true)
            ->distinct()->pluck('employee_id')->all();
        if ($ids && $ids !== [(int) $teacher->hemis_id] && $ids !== [(string) $teacher->hemis_id]) {
            $this->warn('Dars jadvalida shu familiya bilan employee_id lar: ' . implode(', ', $ids) . " (o'qituvchi HEMIS id si: {$teacher->hemis_id})");
        }

        $rows = app(YnQaydnomaListController::class)->teacherVedomosts($teacher);
        $this->table(['Guruh', 'Fan', 'Shakl', 'Holat', 'Fan tugagan', 'YN yuborilgan', 'Tasdiqlash kerak'], $rows->map(fn ($r) => [
            $r->group, mb_substr((string) $r->subject, 0, 40), $r->form, $r->status,
            $r->exams_finished ? 'ha' : ('yo\'q' . ($r->finish_reason ? ' — ' . $r->finish_reason : '')),
            $r->yn_submitted ? 'ha' : 'yo\'q', $r->needs_action ? 'HA' : '',
        ])->all());
        $this->line('Jami qator: ' . $rows->count() . ' · tasdiqlash kerak: ' . $rows->where('needs_action', true)->count());

        return self::SUCCESS;
    }
}
