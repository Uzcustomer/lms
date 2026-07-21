<?php

use App\Models\OqimSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mavjud dars kartochkalariga fakultet nomini SNAPSHOT bloklaridan to'ldirish.
 *
 * Har snapshot bloki `department_name` = HAQIQIY fakultet; blokdagi oqimlarning
 * guruhlari o'sha fakultetга tegishli. Shundan guruh → fakultet xaritasi
 * quriladi va kartochkalar to'ldiriladi (amaliy — group_name; ma'ruza —
 * group_names ichidagi birinchi tanilgan guruh). Faqat NULL yangilanadi;
 * kartalar qayta yaratilmaydi (joylashuvlar saqlanadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_cards', 'faculty_name')) {
            return;
        }

        try {
            foreach (DB::table('timetable_boards')->get() as $board) {
                $groupFac = [];
                foreach ($this->boardSnapshots($board) as $snap) {
                    foreach ($snap->data ?? [] as $bl) {
                        $fac = trim((string) ($bl['department_name'] ?? ''));
                        if ($fac === '') {
                            continue;
                        }
                        foreach ($bl['courses'] ?? [] as $co) {
                            foreach ($co['oqims'] ?? [] as $oq) {
                                foreach ($oq['rows'] ?? [] as $gr) {
                                    $gn = trim((string) ($gr['name'] ?? ''));
                                    if ($gn !== '') {
                                        $groupFac[$gn] = $fac;
                                    }
                                }
                            }
                        }
                    }
                }
                if (empty($groupFac)) {
                    continue;
                }

                // Amaliy — guruh nomi bo'yicha
                foreach ($groupFac as $gn => $fac) {
                    DB::table('timetable_cards')->where('board_id', $board->id)
                        ->where('group_name', $gn)->whereNull('faculty_name')
                        ->update(['faculty_name' => $fac]);
                }

                // Ma'ruza — group_names ichidagi birinchi tanilgan guruh bo'yicha
                $lecs = DB::table('timetable_cards')->where('board_id', $board->id)
                    ->whereNull('faculty_name')->whereNotNull('group_names')
                    ->select('id', 'group_names')->get();
                $idsByFac = [];
                foreach ($lecs as $c) {
                    $names = json_decode($c->group_names, true) ?: [];
                    foreach ($names as $gn) {
                        $gn = trim((string) $gn);
                        if (isset($groupFac[$gn])) {
                            $idsByFac[$groupFac[$gn]][] = $c->id;
                            break;
                        }
                    }
                }
                foreach ($idsByFac as $fac => $ids) {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        DB::table('timetable_cards')->whereIn('id', $chunk)
                            ->update(['faculty_name' => $fac]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Snapshot department faculty backfill o\'tkazib yuborildi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Data backfill — orqaga qaytarilmaydi.
    }

    /** TimetableController::boardSnapshots bilan bir xil mantiq. */
    private function boardSnapshots(object $board): array
    {
        $q = OqimSnapshot::where('status', 'approved');
        if (($board->kind ?? null) === 'plan') {
            $q->where('context->projection', 1)
                ->where('context->academic_year', $board->academic_year);
        } else {
            $q->where(function ($w) {
                $w->whereNull('context->projection')->orWhere('context->projection', 0);
            });
        }
        if (!empty($board->faculty_id)) {
            $q->where('context->faculty', (string) $board->faculty_id);
        }
        $byFaculty = [];
        foreach ($q->get() as $snap) {
            $fk = (string) ($snap->context['faculty'] ?? '');
            if (!isset($byFaculty[$fk]) || $snap->approved_at > $byFaculty[$fk]->approved_at) {
                $byFaculty[$fk] = $snap;
            }
        }
        if (count($byFaculty) > 1) {
            unset($byFaculty['']);
        }
        return $byFaculty;
    }
};
