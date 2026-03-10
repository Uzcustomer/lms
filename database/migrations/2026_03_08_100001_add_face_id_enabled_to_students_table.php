<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('face_id_enabled')->default(true)->after('login_code_expires_at')
                ->comment('Talaba uchun Face ID yoqilgan/o\'chirilgan');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('face_id_enabled');
        });
    }
};
