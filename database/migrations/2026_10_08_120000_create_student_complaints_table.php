<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xalqaro ta'lim fakulteti talabalarining shikoyatlari. Registrator ofisi
     * ko'rib chiqadi: "hal etildi" deb belgilaydi yoki rasmlari bilan o'chiradi.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_complaints')) {
            return;
        }

        Schema::create('student_complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('student_hemis_id')->nullable();
            $table->string('student_id_number')->nullable();
            $table->string('student_name');
            $table->string('group_name')->nullable();
            $table->string('faculty_name')->nullable();
            $table->string('phone', 32);
            $table->text('message');
            // Rasmlar storage/app (yopiq disk) dagi yo'llari
            $table->json('images')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_complaints');
    }
};
