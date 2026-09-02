<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taqsimlanadigan guruhlar — o'ng tomonda belgilanadigan guruhlar ro'yxati.
 *
 * Guruhlarning o'zi `groups` jadvalida turadi, bu yerda faqat "qaysi guruh
 * taqsimlanadi" degan belgi saqlanadi. Guruh nomi va o'sha paytdagi talabalar
 * soni nusxa sifatida yoziladi — HEMIS importidan keyin guruh o'zgarsa ham
 * belgilash paytidagi holat ko'rinib tursin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_source_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id')->index();
            $table->string('group_name');
            $table->string('faculty_name')->nullable()->index();
            $table->string('specialty_name')->nullable();
            $table->string('level_code')->nullable()->index();
            $table->unsignedInteger('student_count')->default(0);
            $table->unsignedBigInteger('selected_by')->nullable();
            $table->timestamps();

            $table->unique('group_hemis_id', 'dsg_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_source_groups');
    }
};
