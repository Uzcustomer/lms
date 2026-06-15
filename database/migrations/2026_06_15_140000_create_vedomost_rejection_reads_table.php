<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost rad etilish bildirgilari "inbox" uchun o'qilgan/o'qilmagan holati.
 *
 * Gmail uslubidagi inbox: har bir foydalanuvchi (o'quv prorektori / superadmin)
 * uchun qaysi rad etilgan vedomostni qachon "o'qigani" saqlanadi. Vedomost qayta
 * rad etilsa (reviewed_at yangilanadi) — read_at undan eski bo'lib qoladi va
 * yozuv yana "o'qilmagan" hisoblanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vedomost_rejection_reads')) {
            return;
        }

        Schema::create('vedomost_rejection_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vedomost_submission_id')->index();
            $table->string('viewer_type');
            $table->unsignedBigInteger('viewer_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['vedomost_submission_id', 'viewer_type', 'viewer_id'],
                'vedomost_rej_read_unique'
            );
            $table->index(['viewer_type', 'viewer_id'], 'vedomost_rej_read_viewer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vedomost_rejection_reads');
    }
};
