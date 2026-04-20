<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_memberships', function (Blueprint $table) {
            $table->string('masul_name')->nullable()->after('kafedra_name');
        });
    }

    public function down(): void
    {
        Schema::table('club_memberships', function (Blueprint $table) {
            $table->dropColumn('masul_name');
        });
    }
};
