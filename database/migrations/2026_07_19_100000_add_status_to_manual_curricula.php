<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_curricula', function (Blueprint $table) {
            // 'active'  — odatiy (HEMIS rejaga bog'langan yoki bog'lanadigan) reja
            // 'planned' — rejalashtirilgan (HEMIS'da hali reja yo'q): sentyabrdagi
            //             1-kurschilar yoki yangi yo'nalishlar uchun. Keyin HEMIS
            //             reja paydo bo'lganda bog'lanadi va 'active' bo'ladi.
            $table->string('status', 20)->default('active')->index()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('manual_curricula', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
