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
        Schema::create('vedomosts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_hemis_id');
            $table->unsignedBigInteger('department_id');
            $table->string('file_path')->nullable();
            $table->string('deportment_name');
            $table->string('semester_name');
            $table->string('semester_code');
            $table->string('group_name');
            $table->string('subject_name');
            $table->float("independent_percent")->default(0);
            $table->float("jb_percent")->default(0);
            $table->float("independent_percent_secend")->default(0);
            $table->float("jb_percent_secend")->default(0);
            $table->float("oski_percent")->default(0);
            $table->float("test_percent")->default(0);
            $table->float("oraliq_percent")->default(0);
            $table->smallInteger("type")->default(1);
            $table->smallInteger("shakl")->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vedomosts');
    }
};