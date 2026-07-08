<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostikada "Qayta o'qish arizasi topilmadi" qatorda operator fanni
 * talabaning qayta o'qish arizasidagi fanga qo'lda almashtirganda, AYNAN
 * qaysi ariza tanlangani (id) shu ustunga yoziladi.
 *
 * Buni saqlashning sababi: quiz natijasi semestri bilan tanlangan ariza
 * semestri HAR XIL bo'lishi mumkin (mas. 3-sem natija talabaning 2-sem
 * arizasiga almashtiriladi). matchRetakeApp() odatda semestr bo'yicha
 * cheklaydi va bunday almashtirishni rad etardi. Endi bu ustun to'lgan
 * bo'lsa — operator tanlovi aniq ariza sifatida hurmat qilinadi (semestr
 * guardi chetlab o'tiladi) va Moodle qayta sync qilganda ham almashtirish
 * izi yo'qolmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->unsignedBigInteger('reassigned_retake_app_id')->nullable()->after('fan_reassigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->dropColumn('reassigned_retake_app_id');
        });
    }
};
