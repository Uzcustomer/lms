<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('independent_submissions', function (Blueprint $table) {
            $table->unsignedInteger('submission_count')->default(1)->after('file_original_name');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'mt_max_resubmissions'],
            ['value' => '2']
        );
    }

    public function down(): void
    {
        Schema::table('independent_submissions', function (Blueprint $table) {
            $table->dropColumn('submission_count');
        });

        DB::table('settings')->where('key', 'mt_max_resubmissions')->delete();
    }
};
