<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->string('address_type')->default('dormitory')->after('registration_end_date');
            $table->string('current_address')->nullable()->after('address_type');
        });
    }

    public function down(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->dropColumn(['address_type', 'current_address']);
        });
    }
};
