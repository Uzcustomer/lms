<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O'qituvchi lavozimi bo'yicha yillik yuklama me'yori (soat). Stavka
 * (o'qituvchilar soni) = jami soat / shu me'yor. Qo'lda tahrirlanadi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('teacher_norms', function (Blueprint $table) {
            $table->id();
            $table->string('position');
            $table->unsignedInteger('annual_hours')->default(900);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('teacher_norms')->insert([
            ['position' => 'Assistent',        'annual_hours' => 900, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['position' => "Katta o'qituvchi", 'annual_hours' => 800, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['position' => 'Dotsent',          'annual_hours' => 750, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['position' => 'Professor',        'annual_hours' => 700, 'sort' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_norms');
    }
};
