<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_excuse_makeups', function (Blueprint $table) {
            $table->boolean('jn_submitted')->default(false)->after('makeup_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('absence_excuse_makeups', function (Blueprint $table) {
            $table->dropColumn('jn_submitted');
        });
    }
};
