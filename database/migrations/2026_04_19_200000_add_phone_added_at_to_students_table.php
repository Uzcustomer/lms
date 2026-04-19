<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('phone_added_at')->nullable()->after('phone');
        });

        DB::table('students')
            ->whereNotNull('phone')
            ->whereNull('phone_added_at')
            ->update(['phone_added_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('phone_added_at');
        });
    }
};
