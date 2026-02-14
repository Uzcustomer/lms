<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marking_system_scores', function (Blueprint $table) {
            $table->id();
            $table->string('marking_system_code')->unique();
            $table->string('marking_system_name');
            $table->integer('minimum_limit')->default(60);
            $table->float('gpa_limit')->default(2.0);

            $table->integer('jn_limit')->default(60);
            $table->boolean('jn_active')->default(true);

            $table->integer('mt_limit')->default(60);
            $table->boolean('mt_active')->default(true);

            $table->integer('on_limit')->default(60);
            $table->boolean('on_active')->default(false);

            $table->integer('oski_limit')->default(60);
            $table->boolean('oski_active')->default(true);

            $table->integer('test_limit')->default(60);
            $table->boolean('test_active')->default(true);

            $table->integer('total_limit')->default(60);
            $table->boolean('total_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marking_system_scores');
    }
};
