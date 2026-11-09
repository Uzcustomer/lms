<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dekan YN qaydnomani rad etgani (sababi bilan): o'qituvchi tasdig'i bekor bo'ladi, o'qituvchi
 * qayta ko'rib chiqib tasdiqlaganda resolved_at to'ldiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yn_qaydnoma_rejections', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('group_hemis_id');
            $table->unsignedBigInteger('dean_id');
            $table->string('dean_name');
            $table->text('reason');
            $table->json('weights')->nullable();
            $table->timestamp('rejected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'semester_code', 'group_hemis_id'], 'ynqr_vedomost_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_qaydnoma_rejections');
    }
};
