<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absence_excuses', 'doc_number')) {
            return;
        }

        Schema::table('absence_excuses', function (Blueprint $table) {
            $table->string('doc_number')->nullable()->after('department_name');
        });
    }

    public function down(): void
    {
        Schema::table('absence_excuses', function (Blueprint $table) {
            $table->dropColumn('doc_number');
        });
    }
};
