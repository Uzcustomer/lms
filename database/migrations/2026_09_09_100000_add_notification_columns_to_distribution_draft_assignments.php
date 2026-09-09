<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taqsimot rejasi bo'yicha xabardor qilish holati.
 *
 * notified_at        — Telegramga xabar yuborilgan vaqt. Bir talabaga bir marta
 *                      yuboriladi; guruh keyin o'zgarsa qayta yuboriladi
 *                      (registrator tugmani qayta bosganda).
 * seen_at            — talaba profilida popupni ko'rgan vaqt. Popup shundan
 *                      keyin qayta chiqmaydi.
 * notified_group_id  — xabar qaysi guruh uchun yuborilgani. Reja o'zgarsa
 *                      (boshqa guruhga ko'chirilsa) yangi xabar kerak bo'ladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('distribution_draft_assignments')) {
            return;
        }

        Schema::table('distribution_draft_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('distribution_draft_assignments', 'notified_at')) {
                $table->timestamp('notified_at')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('distribution_draft_assignments', 'notified_group_id')) {
                $table->unsignedBigInteger('notified_group_id')->nullable()->after('notified_at');
            }
            if (!Schema::hasColumn('distribution_draft_assignments', 'seen_at')) {
                $table->timestamp('seen_at')->nullable()->after('notified_group_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('distribution_draft_assignments')) {
            return;
        }

        Schema::table('distribution_draft_assignments', function (Blueprint $table) {
            foreach (['notified_at', 'notified_group_id', 'seen_at'] as $column) {
                if (Schema::hasColumn('distribution_draft_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
