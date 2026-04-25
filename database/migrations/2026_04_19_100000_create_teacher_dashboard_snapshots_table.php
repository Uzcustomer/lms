<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teacher_dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->string('teacher_hemis_id')->nullable();
            $table->json('payload');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['scope', 'teacher_hemis_id'], 'uniq_tds_scope_teacher');
            $table->index('teacher_hemis_id', 'idx_tds_teacher_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_dashboard_snapshots');
    }
};
