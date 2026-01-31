<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vedomost_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("vedomost_id");
            $table->unsignedBigInteger("group_hemis_id");
            $table->unsignedBigInteger("subject_hemis_id");
            $table->unsignedBigInteger("subject_hemis_id_secend");
            $table->json("student_hemis_ids");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vedomost_groups');
    }
};