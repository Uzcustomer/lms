<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ayrim talabalarga berilgan ovoz ruxsati.
 *
 * Guruh darajasidagi ruxsat (distribution_voting_groups) butun guruhga
 * tegishli; bu jadval esa modal ichida tanlab berilgan talabalar uchun.
 * Popup ikkalasidan birortasi bo'lsa chiqadi. "Ovoz berishni yopish"
 * ikkala jadvalni ham tozalaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_voting_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique('dvs_student_unique');
            $table->unsignedBigInteger('group_hemis_id')->nullable()->index();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamps();

            $table->foreign('student_id', 'dvs_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_voting_students');
    }
};
