<?php

use App\Models\OqimSnapshot;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fakultet nomini mavjud (avval yaratilgan) dars kartochkalariga to'ldirish.
 *
 * `faculty_name` ustuni bugun qo'shildi; undan oldin yaratilgan kartochkalarda
 * qiymat NULL. Shu sabab panjaradagi "Fakultet · yo'nalish · kurs" selektori va
 * Excel ko'rinishidagi fakultet super-sarlavhasi ko'rinmaydi. Kartochkalarni
 * qayta yaratmasdan (joylashuvlarni yo'qotmasdan) qiymatni tasdiqlangan oqim
 * snapshotining fakultet kontekstidan yo'nalish+kurs bo'yicha moslab tiklaymiz.
 *
 * Xavfsiz: faqat NULL bo'lgan kartochkalarni yangilaydi, snapshot topilmasa
 * yoki xato yuzaga kelsa deploy'ni buzmaydi (try/catch).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_cards', 'faculty_name')) {
            return;
        }

        try {
            // Fakultet id → nomi (strukturaviy bo'linma turi 11 — fakultet)
            $facMap = Department::where('structure_type_code', 11)->pluck('name', 'id')->all();

            foreach (DB::table('timetable_boards')->get() as $board) {
                foreach ($this->boardSnapshots($board) as $fk => $snap) {
                    $facName = $facMap[(int) $fk] ?? ($facMap[$fk] ?? null);
                    if (!$facName) {
                        continue;
                    }
                    foreach ($snap->data ?? [] as $bl) {
                        $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                        if ($specName === '') {
                            continue;
                        }
                        foreach ($bl['courses'] ?? [] as $co) {
                            $lvl = (int) ($co['level_code'] ?? 0);
                            $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                            DB::table('timetable_cards')
                                ->where('board_id', $board->id)
                                ->where('specialty_name', $specName)
                                ->where('course', $course)
                                ->whereNull('faculty_name')
                                ->update(['faculty_name' => $facName]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Timetable faculty_name backfill o\'tkazib yuborildi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Data backfill — orqaga qaytarilmaydi.
    }

    /**
     * Doska uchun tasdiqlangan oqim snapshotlari (fakultet bo'yicha eng so'nggisi).
     * TimetableController::boardSnapshots bilan bir xil mantiq.
     */
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
