<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            // Propiska jarayoni holati
            $table->string('registration_process_status')->default('none')->after('registration_end_date');
            // Viza jarayoni holati
            $table->string('visa_process_status')->default('none')->after('visa_end_date');
            // Talaba ma'lumotlarni qachon to'ldirishi kerak
            $table->timestamp('visa_info_deadline')->nullable()->after('agreement_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->dropColumn(['registration_process_status', 'visa_process_status', 'visa_info_deadline']);
        });
    }
};
