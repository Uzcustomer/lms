<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_excuse_makeups', function (Blueprint $table) {
            $table->date('makeup_end_date')->nullable()->after('makeup_date');
        });
    }

    public function down(): void
    {
        Schema::table('absence_excuse_makeups', function (Blueprint $table) {
            $table->dropColumn('makeup_end_date');
        });
    }
};
