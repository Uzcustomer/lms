<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guruh tanlash ovozi.
 *
 * distribution_voting_groups — qaysi guruh talabalari ovoz bera olishini
 * belgilaydi (yozuv bor = ochiq). Registrator ochadi va yopadi.
 *
 * distribution_votes — talabaning tanlovi. Talaba faqat bir marta ovoz
 * beradi (student_id unique). Registrator tasdiqlagach reja (draft) ga
 * aylanadi — LMS dagi guruhga bu yerda ham tegilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_voting_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id')->unique('dvg_group_unique');
            $table->string('group_name')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamps();
        });

        Schema::create('distribution_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique('dv_student_unique');
            $table->unsignedBigInteger('from_group_hemis_id')->index();
            $table->unsignedBigInteger('to_group_hemis_id')->index();

            // Ovoz berilgan paytdagi nusxa.
            $table->string('student_name');
            $table->string('student_id_number')->nullable();
            $table->string('from_group_name')->nullable();
            $table->string('to_group_name')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id', 'dv_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_votes');
        Schema::dropIfExists('distribution_voting_groups');
    }
};
