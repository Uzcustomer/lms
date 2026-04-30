<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_groups', function (Blueprint $table) {
            $table->string('pdf_signature', 255)->nullable()->after('pdf_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_groups', function (Blueprint $table) {
            $table->dropColumn('pdf_signature');
        });
    }
};
