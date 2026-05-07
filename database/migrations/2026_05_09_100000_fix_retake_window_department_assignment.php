<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qabul oynalarining fakultet biriktirilishini to'g'irlash.
 *
 * Avval oynaning `department_hemis_id` qiymati `Specialty.department_hemis_id`
 * dan olinardi. Bir xil yo'nalish (masalan, "Davolash ishi") bir nechta
 * fakultetda bo'lishi mumkin, shuning uchun bu yo'l noto'g'ri fakultet
 * nomini ko'rsatishga olib kelar edi.
 *
 * Endi haqiqiy manba — `students.department_id` (talabalarning real fakulteti).
 * Mavjud yozuvlar uchun: agar (specialty_id, level_code) bo'yicha aniq bitta
 * talabalar fakulteti bo'lsa, oynaning fakulteti shunga moslashtiriladi.
 * Agar bir nechta variant bor bo'lsa va joriy fakultet variantlarda bor bo'lsa
 * — joyida qoldiriladi; aks holda eng ko'p talabasi bor fakultetga ko'chiriladi.
 *
 * Shuningdek, unique indeks `department_hemis_id` ustunini ham qamrab oladi
 * shunda turli fakultetlar uchun bir xil yo'nalish/kurs/semestr alohida oyna
 * sifatida saqlanishi mumkin.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('retake_application_windows') ||
            !Schema::hasTable('students') ||
            !Schema::hasColumn('retake_application_windows', 'department_hemis_id')) {
            return;
        }

        // 1) Mavjud oynalarni real talabalar bo'yicha qayta hisoblash
        $facultyIds = DB::table('departments')
            ->where('structure_type_code', 11)
            ->pluck('department_hemis_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (!empty($facultyIds)) {
            $windows = DB::table('retake_application_windows')
                ->whereNull('deleted_at')
                ->select('id', 'specialty_id', 'level_code', 'department_hemis_id')
                ->get();

            foreach ($windows as $w) {
                // Shu yo'nalish va kurs uchun talabalar qaysi fakultetlarda?
                $studentFaculties = DB::table('students')
                    ->select('department_id', DB::raw('COUNT(*) as cnt'))
                    ->where('specialty_id', $w->specialty_id)
                    ->where('level_code', $w->level_code)
                    ->whereIn('department_id', $facultyIds)
                    ->whereNotNull('department_id')
                    ->groupBy('department_id')
                    ->orderByDesc('cnt')
                    ->get();

                if ($studentFaculties->isEmpty()) {
                    continue;
                }

                $current = (string) ($w->department_hemis_id ?? '');
                $validIds = $studentFaculties->pluck('department_id')->map(fn ($v) => (string) $v);

                // Joriy qiymat haqiqiy fakultetlar orasida bo'lsa — tegmaymiz
                if ($current !== '' && $validIds->contains($current)) {
                    continue;
                }

                // Aks holda — eng ko'p talabasi bor fakultetga moslashtiramiz
                $newDept = (string) $studentFaculties->first()->department_id;
                if ($newDept !== $current) {
                    DB::table('retake_application_windows')
                        ->where('id', $w->id)
                        ->update(['department_hemis_id' => $newDept]);
                }
            }
        }

        // 2) Unique indeksini yangilab, fakultet ham qamrab olinsin —
        //    bir xil yo'nalish/kurs/semestr turli fakultetlar uchun yaratilishi mumkin.
        Schema::table('retake_application_windows', function (Blueprint $table) {
            try {
                $table->dropUnique('retake_window_unique_idx');
            } catch (\Throwable $e) {
                // indeks yo'q bo'lsa o'tkazib yuboramiz
            }
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->unique(
                ['session_id', 'department_hemis_id', 'specialty_id', 'level_code', 'semester_code'],
                'retake_window_unique_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('retake_application_windows')) {
            return;
        }

        Schema::table('retake_application_windows', function (Blueprint $table) {
            try {
                $table->dropUnique('retake_window_unique_idx');
            } catch (\Throwable $e) {
                //
            }
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->unique(
                ['session_id', 'specialty_id', 'level_code', 'semester_code'],
                'retake_window_unique_idx'
            );
        });
    }
};
