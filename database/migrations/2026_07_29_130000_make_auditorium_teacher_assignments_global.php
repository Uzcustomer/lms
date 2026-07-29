<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('auditorium_teacher')) {
            return;
        }

        // Eski doska-kesimidagi yozuvlardan har xona uchun eng so'nggi biriktirishni saqlaymiz.
        $keeperIds = DB::table('auditorium_teacher')
            ->orderBy('auditorium_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'auditorium_id'])
            ->unique('auditorium_id')
            ->pluck('id');

        if ($keeperIds->isNotEmpty()) {
            DB::table('auditorium_teacher')->whereNotIn('id', $keeperIds)->delete();
        }

        Schema::table('auditorium_teacher', function (Blueprint $table) {
            $table->unsignedBigInteger('board_id')->nullable()->change();
        });

        DB::table('auditorium_teacher')->update(['board_id' => null]);

        Schema::table('auditorium_teacher', function (Blueprint $table) {
            $table->unique('auditorium_id', 'auditorium_teacher_auditorium_global_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('auditorium_teacher')) {
            return;
        }

        Schema::table('auditorium_teacher', function (Blueprint $table) {
            $table->dropUnique('auditorium_teacher_auditorium_global_unique');
        });

        $boardId = DB::table('timetable_boards')->orderBy('id')->value('id');
        if ($boardId) {
            DB::table('auditorium_teacher')->whereNull('board_id')->update(['board_id' => $boardId]);

            Schema::table('auditorium_teacher', function (Blueprint $table) {
                $table->unsignedBigInteger('board_id')->nullable(false)->change();
            });
        }
    }
};
