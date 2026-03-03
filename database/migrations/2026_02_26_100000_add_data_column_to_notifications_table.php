<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (!Schema::hasColumn('notifications', 'data')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->json('data')->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'data')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        }
    }
};
