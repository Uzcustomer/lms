<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasdiqlangan oqimlar TARIXI. oqim_snapshots har context_key uchun bitta
 * yozuvni ustidan yozadi (eski tasdiq yo'qoladi). Bu jadval esa har
 * tasdiqda yangi versiya saqlaydi — real yoki reja (kelasi yil), vaqti va
 * mas'uli bilan, keyinchalik ko'rish uchun.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('oqim_snapshot_versions', function (Blueprint $table) {
            $table->id();
            $table->string('context_key', 64)->index();
            $table->json('context')->nullable();
            $table->string('kind', 10)->default('real');       // real | plan (reja)
            $table->string('academic_year', 20)->nullable()->index();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->string('faculty_name')->nullable();
            $table->string('education_type')->nullable();
            $table->longText('data')->nullable();              // oqim bloklari (JSON)
            $table->json('summary')->nullable();               // talaba/oqim/guruhcha soni
            $table->string('note')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oqim_snapshot_versions');
    }
};
