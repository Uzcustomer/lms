<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 2026_02_25_300000 migratsiyasi bu FK ni allaqachon o'chirgan bo'lishi mumkin
        // (bo'sh bazada shunday) — faqat mavjud bo'lsa o'chiramiz.
        $hasForeign = collect(Schema::getForeignKeys('yn_submissions'))
            ->contains(fn ($fk) => $fk['columns'] === ['submitted_by']);

        Schema::table('yn_submissions', function (Blueprint $table) use ($hasForeign) {
            if ($hasForeign) {
                $table->dropForeign(['submitted_by']);
            }
            $table->unsignedBigInteger('submitted_by')->nullable()->change();
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->unsignedBigInteger('submitted_by')->nullable(false)->change();
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
