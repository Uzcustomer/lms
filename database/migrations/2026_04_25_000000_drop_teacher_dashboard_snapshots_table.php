<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('teacher_dashboard_snapshots');
    }

    public function down(): void
    {
        // Snapshot funksiyasi olib tashlandi — qayta yaratish kerak emas.
    }
};
